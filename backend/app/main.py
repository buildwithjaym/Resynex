# app/main.py
import json
import threading
import time
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, UploadFile, File, Form, Depends, Header, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy.exc import OperationalError
from sqlalchemy.orm import Session

from app.deps import get_db
from app.models import Project, Document, EvaluationJob, Evaluation
from app.storage import save_upload
from app.evaluator import evaluate_document


app = FastAPI(title="Resynex V1 API")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.get("/api/health")
def health():
    return {"ok": True, "service": "resynex-api"}


def parse_user_id(x_user_id: Optional[str], form_user_id: Optional[int]) -> Optional[int]:
    if form_user_id and int(form_user_id) > 0:
        return int(form_user_id)
    if x_user_id:
        try:
            v = int(x_user_id)
            return v if v > 0 else None
        except Exception:
            return None
    return None


def _operational_retryable(err: OperationalError) -> bool:
    msg = str(err).lower()
    if "1213" in msg or "deadlock found" in msg:
        return True
    if "1205" in msg or "lock wait timeout" in msg:
        return True
    if "server has gone away" in msg:
        return True
    if "lost connection" in msg:
        return True
    return False


def commit_with_retry(db: Session, tries: int = 6, base_sleep: float = 0.15) -> None:
    last = None
    for i in range(max(1, int(tries))):
        try:
            db.commit()
            return
        except OperationalError as e:
            db.rollback()
            last = e
            if _operational_retryable(e):
                time.sleep(base_sleep * (2 ** i))
                continue
            raise
    raise RuntimeError(f"db_commit_failed_after_retries:{last}")


def _set_job_status(db: Session, job_id: int, status: str, error: str | None = None) -> None:
    job = db.get(EvaluationJob, job_id)
    if not job:
        return
    job.status = status
    job.error = error
    db.add(job)
    commit_with_retry(db)


def _assert_job_owner(db: Session, job: EvaluationJob, x_user_id: Optional[str]) -> None:
    if not x_user_id:
        raise HTTPException(status_code=401, detail="401 Unauthorized (X-User-Id missing)")
    try:
        uid = int(x_user_id)
    except Exception:
        raise HTTPException(status_code=401, detail="401 Unauthorized (X-User-Id invalid)")
    if uid <= 0:
        raise HTTPException(status_code=401, detail="401 Unauthorized (X-User-Id invalid)")

    proj = db.get(Project, job.project_id)
    if not proj:
        raise HTTPException(status_code=404, detail="project_not_found")

    if proj.user_id is None:
        return

    if int(proj.user_id) != int(uid):
        raise HTTPException(status_code=403, detail="403 Forbidden (job does not belong to you)")


def _run_job(job_id: int, saved_path: str, fmt: str) -> None:
    gen = get_db()
    db = next(gen)
    try:
        p = Path(saved_path)

        _set_job_status(db, job_id, "scanning")
        time.sleep(0.03)

        if not p.exists():
            raise RuntimeError(f"job_file_missing:{saved_path}")
        if p.stat().st_size <= 0:
            raise RuntimeError(f"job_file_empty:{saved_path}")

        _set_job_status(db, job_id, "analyzing")

        t0 = time.time()
        result = evaluate_document(saved_path, fmt)
        eval_secs = time.time() - t0

        _set_job_status(db, job_id, "finalizing")

        job = db.get(EvaluationJob, job_id)
        if not job:
            return

        summary = result.get("summary") or {}
        checks = result.get("checks") or []
        connections = result.get("connections") or {}
        fix_plan = result.get("fix_plan") or {}

        if isinstance(summary, dict):
            dbg = summary.get("debug") if isinstance(summary.get("debug"), dict) else {}
            dbg["timing_seconds"] = {"evaluate": round(eval_secs, 3)}
            summary["debug"] = dbg

        ev = Evaluation(
            project_id=job.project_id,
            document_id=job.document_id,
            rubric_id=job.rubric_id,
            job_id=job.id,
            score=int(summary.get("score") or 0),
            level=str(summary.get("level") or "—"),
            decision=str(summary.get("decision") or "Revise"),
            summary_json=json.dumps(summary, ensure_ascii=False),
            checks_json=json.dumps(checks, ensure_ascii=False),
            connections_json=json.dumps(connections, ensure_ascii=False),
            fix_plan_json=json.dumps(fix_plan, ensure_ascii=False),
        )
        db.add(ev)

        job.status = "done"
        job.error = None
        db.add(job)

        commit_with_retry(db)

    except Exception as e:
        try:
            _set_job_status(db, job_id, "failed", str(e))
        except Exception:
            pass
    finally:
        try:
            db.close()
        except Exception:
            pass
        try:
            gen.close()
        except Exception:
            pass


@app.post("/api/projects/upload")
def upload_project(
    title: str = Form("Untitled Project"),
    rubric_id: int = Form(1),
    format: str = Form("auto"),
    user_id: Optional[int] = Form(None),
    file: UploadFile = File(...),
    rubric_file: UploadFile | None = File(None),
    x_user_id: Optional[str] = Header(default=None),
    db: Session = Depends(get_db),
):
    if not file or not file.filename:
        return {"ok": False, "error": "missing_file"}

    ext = (file.filename.split(".")[-1] or "").lower()
    if ext not in ("pdf", "docx"):
        return {"ok": False, "error": "invalid_file_type"}

    uid = parse_user_id(x_user_id, user_id)

    fmt = (format or "").strip().lower()
    if not fmt:
        fmt = "auto"

    project = Project(
        title=(title or "Untitled Project").strip() or "Untitled Project",
        user_id=uid,
    )
    db.add(project)
    commit_with_retry(db)
    db.refresh(project)

    try:
        file.file.seek(0)
    except Exception:
        pass

    saved_path = save_upload(file.file, project.id, file.filename)

    p = Path(saved_path)
    if not p.exists():
        return {"ok": False, "error": "upload_saved_missing"}
    if p.stat().st_size <= 0:
        return {"ok": False, "error": "upload_saved_empty"}

    doc = Document(
        project_id=project.id,
        original_filename=file.filename,
        file_path=saved_path,
        file_type=("docx" if ext == "docx" else "pdf"),
    )
    db.add(doc)
    commit_with_retry(db)
    db.refresh(doc)

    job = EvaluationJob(
        project_id=project.id,
        document_id=doc.id,
        rubric_id=rubric_id,
        status="queued",
        error=None,
    )
    db.add(job)
    commit_with_retry(db)
    db.refresh(job)

    threading.Thread(target=_run_job, args=(job.id, saved_path, fmt), daemon=True).start()

    return {
        "ok": True,
        "project_id": project.id,
        "document_id": doc.id,
        "job_id": job.id,
        "user_id": project.user_id,
        "format_used": fmt,
    }


@app.get("/api/jobs/{job_id}")
def job_status(job_id: int, x_user_id: Optional[str] = Header(default=None), db: Session = Depends(get_db)):
    job = db.get(EvaluationJob, job_id)
    if not job:
        return {"ok": False, "error": "job_not_found"}

    _assert_job_owner(db, job, x_user_id)

    return {"ok": True, "status": job.status, "error": job.error}


@app.get("/api/evaluations/by-job/{job_id}")
def evaluation_by_job(job_id: int, x_user_id: Optional[str] = Header(default=None), db: Session = Depends(get_db)):
    job = db.get(EvaluationJob, job_id)
    if not job:
        return {"ok": False, "error": "job_not_found"}

    _assert_job_owner(db, job, x_user_id)

    ev = (
        db.query(Evaluation)
        .filter(Evaluation.job_id == job.id)
        .order_by(Evaluation.id.desc())
        .first()
    )
    if not ev:
        return {"ok": False, "error": "evaluation_not_ready"}

    return {
        "ok": True,
        "job_id": job.id,
        "evaluation_id": ev.id,
        "status": job.status,
        "score": ev.score,
        "level": ev.level,
        "decision": ev.decision,
        "summary": json.loads(ev.summary_json),
        "checks": json.loads(ev.checks_json),
        "connections": json.loads(ev.connections_json),
        "fix_plan": json.loads(ev.fix_plan_json),
    }
