const API = String(window.RESYNEX_API_BASE || "http://127.0.0.1:8000");
const JOB_ID = String(window.RESYNEX_JOB_ID || "");
const UID = String(window.RESYNEX_USER_ID || "");

console.log("DBG vars:", { API, JOB_ID, UID });

const statusBadge = document.getElementById("statusBadge");
const statusNow = document.getElementById("statusNow");
const statusSmall = document.getElementById("statusSmall");
const pulseDot = document.getElementById("pulseDot");

const stepQueued = document.getElementById("stepQueued");
const stepParsing = document.getElementById("stepParsing");
const stepEvaluating = document.getElementById("stepEvaluating");
const stepReady = document.getElementById("stepReady");

const openReportBtn = document.getElementById("openReportBtn");
const errorBox = document.getElementById("errorBox");

const progressLabel = document.getElementById("progressLabel");
const progressPct = document.getElementById("progressPct");
const progressSub = document.getElementById("progressSub");
const fill = document.getElementById("fill");

let stopped = false;
let backendFailed = false;
let backendDone = false;
let lastBackendStatus = "queued";
let lastBackendError = null;

const STATUS_MIN_MS = 15000;
const AUTO_REDIRECT_MS = 5000;
const startTs = Date.now();

let autoRedirectTimer = null;

function showError(msg) {
    if (errorBox) {
        errorBox.textContent = msg;
        errorBox.hidden = false;
    }
    pulseDot?.classList.add("dead");
}

function setBadge(t, mode) {
    if (!statusBadge) return;
    statusBadge.textContent = t;
    statusBadge.dataset.mode = mode || "";
}

function setProgress(pct, label, sub) {
    const clamped = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
    if (fill) fill.style.width = `${clamped}%`;
    if (progressPct) progressPct.textContent = `${clamped}%`;
    if (progressLabel) progressLabel.textContent = label || "";
    if (progressSub) progressSub.textContent = sub || "";
}

function setStep(el, state) {
    if (!el) return;
    el.classList.remove("on", "done");
    if (state === "on") el.classList.add("on");
    if (state === "done") el.classList.add("done");
}

function uiStateAt(elapsedMs) {
    const t = Math.max(0, Math.min(1, elapsedMs / STATUS_MIN_MS));
    const pct = Math.floor(20 + (95 - 20) * t);

    if (t < 0.20) {
        return { pct, badge: "Queued", mode: "warn", now: "Queued", sub: "Waiting for evaluator", step: "queued" };
    }
    if (t < 0.60) {
        return { pct, badge: "Parsing", mode: "warn", now: "Parsing", sub: "Reading chapters and extracting text", step: "parsing" };
    }
    if (t < 0.90) {
        return { pct, badge: "Evaluating", mode: "warn", now: "Evaluating", sub: "Scoring structure and alignment", step: "evaluating" };
    }
    return { pct: Math.max(pct, 90), badge: "Finalizing", mode: "warn", now: "Finalizing", sub: "Preparing the report", step: "ready" };
}

function applySteps(step) {
    if (step === "queued") {
        setStep(stepQueued, "on");
        setStep(stepParsing, "");
        setStep(stepEvaluating, "");
        setStep(stepReady, "");
        return;
    }
    if (step === "parsing") {
        setStep(stepQueued, "done");
        setStep(stepParsing, "on");
        setStep(stepEvaluating, "");
        setStep(stepReady, "");
        return;
    }
    if (step === "evaluating") {
        setStep(stepQueued, "done");
        setStep(stepParsing, "done");
        setStep(stepEvaluating, "on");
        setStep(stepReady, "");
        return;
    }
    if (step === "ready") {
        setStep(stepQueued, "done");
        setStep(stepParsing, "done");
        setStep(stepEvaluating, "done");
        setStep(stepReady, "on");
    }
}

function backendUiFor(status) {
    const map = {
        queued: { badge: "Queued", mode: "warn", now: "Queued", sub: "Waiting for evaluator", pct: 70, step: "queued" },
        scanning: { badge: "Scanning", mode: "warn", now: "Scanning", sub: "Checking your file", pct: 78, step: "parsing" },
        analyzing: { badge: "Analyzing", mode: "warn", now: "Analyzing", sub: "Extracting and scoring", pct: 85, step: "evaluating" },
        finalizing: { badge: "Finalizing", mode: "warn", now: "Finalizing", sub: "Preparing report output", pct: 92, step: "ready" },
        done: { badge: "Ready", mode: "ok", now: "Ready", sub: "Report is ready", pct: 100, step: "ready" },
        failed: { badge: "Failed", mode: "bad", now: "Failed", sub: "Evaluation failed", pct: 100, step: "" },
    };
    return map[status] || map.queued;
}

function scheduleAutoRedirect() {
    if (autoRedirectTimer) return;
    autoRedirectTimer = setTimeout(() => {
        window.location.href = `report.php?job_id=${encodeURIComponent(JOB_ID)}`;
    }, AUTO_REDIRECT_MS);
}

function cancelAutoRedirect() {
    if (!autoRedirectTimer) return;
    clearTimeout(autoRedirectTimer);
    autoRedirectTimer = null;
}

function applyTimelineUI() {
    if (stopped) return;

    const elapsed = Date.now() - startTs;

    if (backendFailed) {
        setBadge("Failed", "bad");
        if (statusNow) statusNow.textContent = "Failed";
        if (statusSmall) statusSmall.textContent = "Evaluation failed";
        setProgress(100, "Failed", lastBackendError || "Please try again with DOCX or a clearer file.");
        applySteps("");
        pulseDot?.classList.add("dead");
        openReportBtn && (openReportBtn.disabled = true);
        stopped = true;
        return;
    }

    if (elapsed < STATUS_MIN_MS) {
        const u = uiStateAt(elapsed);
        setBadge(u.badge, u.mode);
        if (statusNow) statusNow.textContent = u.now;
        if (statusSmall) statusSmall.textContent = backendDone ? "Report is ready. Waiting to open…" : u.sub;

        setProgress(u.pct, `${u.badge}…`, backendDone ? "Report is finalizing. Please wait..." : u.sub);
        applySteps(u.step);

        requestAnimationFrame(applyTimelineUI);
        return;
    }

    if (backendDone) {
        setBadge("Ready", "ok");
        if (statusNow) statusNow.textContent = "Ready";
        if (statusSmall) statusSmall.textContent = "Report is ready";
        setProgress(100, "Ready", `Auto-opening in ${Math.ceil(AUTO_REDIRECT_MS / 1000)}s…`);
        applySteps("ready");

        if (openReportBtn) openReportBtn.disabled = false;

        scheduleAutoRedirect();
        requestAnimationFrame(applyTimelineUI);
        return;
    }

    const s = backendUiFor(lastBackendStatus);
    setBadge(s.badge, s.mode);
    if (statusNow) statusNow.textContent = s.now;
    if (statusSmall) statusSmall.textContent = s.sub;

    setProgress(s.pct, `${s.badge}…`, `${s.sub} (still working)`);
    applySteps(s.step);

    requestAnimationFrame(applyTimelineUI);
}

async function fetchStatus() {
    if (!UID || UID === "0") throw new Error("Missing RESYNEX_USER_ID (not logged in).");
    if (!JOB_ID) throw new Error("Missing job_id. Please upload again.");

    const r = await fetch(`${API}/api/jobs/${encodeURIComponent(JOB_ID)}`, {
        method: "GET",
        headers: {
            "X-User-Id": UID,
            "Accept": "application/json",
            "Cache-Control": "no-cache",
        },
        cache: "no-store",
    });

    const j = await r.json().catch(() => ({}));

    if (r.status === 401) throw new Error("401 Unauthorized (X-User-Id missing/invalid)");
    if (r.status === 403) throw new Error("403 Forbidden (job does not belong to you)");
    if (!r.ok) throw new Error(j?.detail || j?.error || `HTTP_${r.status}`);
    if (!j.ok) throw new Error(j?.error || "job_not_found");

    return { status: String(j.status || "queued").toLowerCase(), error: j.error || null };
}

async function pollBackend() {
    if (stopped) return;

    try {
        const res = await fetchStatus();
        lastBackendStatus = res.status;
        lastBackendError = res.error;

        if (res.status === "done") backendDone = true;
        if (res.status === "failed") {
            backendFailed = true;
            showError(res.error || "Evaluation failed.");
            return;
        }

        setTimeout(pollBackend, 1200);
    } catch (e) {
        showError(e.message || "Error polling job status.");
        stopped = true;
    }
}

openReportBtn?.addEventListener("click", () => {
    if (!JOB_ID) return;
    cancelAutoRedirect();
    window.location.href = `report.php?job_id=${encodeURIComponent(JOB_ID)}`;
});

if (errorBox) errorBox.hidden = true;
if (openReportBtn) openReportBtn.disabled = true;

applyTimelineUI();
pollBackend();
