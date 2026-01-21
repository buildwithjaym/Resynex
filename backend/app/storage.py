import shutil
from pathlib import Path
from typing import BinaryIO

UPLOAD_ROOT = Path(__file__).resolve().parent / "uploads"

def save_upload(fileobj: BinaryIO, project_id: int, filename: str) -> str:
    """
    Save the uploaded file stream to disk and return absolute path.
    Hard-fails if the saved file is empty.
    """
    UPLOAD_ROOT.mkdir(parents=True, exist_ok=True)

    safe_name = (filename or "upload").strip().replace("\\", "_").replace("/", "_")
    if not safe_name:
        safe_name = "upload"

    project_dir = UPLOAD_ROOT / f"project_{int(project_id)}"
    project_dir.mkdir(parents=True, exist_ok=True)

    out_path = project_dir / safe_name


    try:
        fileobj.seek(0)
    except Exception:
        pass

    with open(out_path, "wb") as f:
        shutil.copyfileobj(fileobj, f, length=1024 * 1024)

    if not out_path.exists():
        raise RuntimeError(f"save_upload_failed:file_not_created:{out_path}")

    size = out_path.stat().st_size
    if size <= 0:
        raise RuntimeError(f"save_upload_failed:empty_file_saved:{out_path}")

    return str(out_path.resolve())
