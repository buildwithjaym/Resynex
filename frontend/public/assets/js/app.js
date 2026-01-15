const uploadUrl =
    window.RESYNEX_UPLOAD_URL || "http://127.0.0.1:8000/api/projects/upload";

const USER_ID = (window.RESYNEX_USER_ID || "").toString();

/* DOM */
const dropzone = document.getElementById("dropzone");
const researchInput = document.getElementById("researchFile");
const rubricInput = document.getElementById("rubricFile");

const researchName = document.getElementById("researchName");
const researchInfo = document.getElementById("researchInfo");
const removeResearch = document.getElementById("removeResearch");

const formatSelect = document.getElementById("formatSelect");
const formatHint = document.getElementById("formatHint");
const rubricHint = document.getElementById("rubricHint");

const titleInput = document.getElementById("projectTitle");

const evaluateBtn = document.getElementById("evaluateBtn");
const resetBtn = document.getElementById("resetBtn");

const errorBox = document.getElementById("errorBox");
const okBox = document.getElementById("okBox");

const progress = document.getElementById("progress");
const progressLabel = document.getElementById("progressLabel");
const progressPct = document.getElementById("progressPct");
const progressSub = document.getElementById("progressSub");
const fill = document.getElementById("fill");
const shimmer = document.querySelector(".shimmer");

/* STATE */
let file = null;

/* HELPERS */
const humanSize = (b) => {
    const u = ["B", "KB", "MB", "GB"];
    let i = 0;
    while (b >= 1024 && i < u.length - 1) {
        b /= 1024;
        i++;
    }
    return `${b.toFixed(i ? 1 : 0)} ${u[i]}`;
};

const toast = (el, msg) => {
    el.textContent = msg;
    el.hidden = false;
    el.classList.remove("toastIn");
    void el.offsetWidth;
    el.classList.add("toastIn");
};

const clearMsgs = () => {
    errorBox.hidden = true;
    okBox.hidden = true;
};

const setProgress = (on, pct = 0, label = "", sub = "") => {
    progress.hidden = !on;
    if (!on) {
        shimmer?.classList.remove("on");
        fill.style.width = "0%";
        progressPct.textContent = "";
        return;
    }
    progressLabel.textContent = label;
    progressSub.textContent = sub;
    progressPct.textContent = `${pct}%`;
    fill.style.width = `${pct}%`;
    shimmer?.classList.add("on");
};

const safeJsonParse = (txt) => {
    try {
        return { ok: true, value: JSON.parse(txt || "{}") };
    } catch {
        return { ok: false, value: null };
    }
};

const apiErrorMessage = (status, body) => {
    if (body?.error) return body.error;
    if (body?.detail) return body.detail;
    return `HTTP_${status}`;
};

/* VALIDATION */
const formatProvided = () => (formatSelect.value || "").trim() !== "";
const rubricProvided = () => rubricInput.files && rubricInput.files.length > 0;
const needsRubric = () => !formatProvided() || formatSelect.value === "custom_upload";

const updateHints = () => {
    const v = formatSelect.value;
    if (v === "custom_upload") {
        formatHint.textContent = "Custom selected. Uploading a rubric is required.";
        rubricHint.textContent = "Required.";
    } else if (!v) {
        formatHint.textContent = "Select a format or upload a rubric to continue.";
        rubricHint.textContent = "Required.";
    } else {
        formatHint.textContent = "Format selected. Rubric upload optional.";
        rubricHint.textContent = "Optional.";
    }
};

const updateState = () => {
    updateHints();
    const ok =
        !!file &&
        (formatProvided() || rubricProvided()) &&
        (!needsRubric() || rubricProvided());
    evaluateBtn.disabled = !ok;
    removeResearch.disabled = !file;
};

const setFile = (f) => {
    file = f || null;
    if (!file) {
        researchName.textContent = "No file selected";
        researchInfo.textContent = "Upload a PDF or DOCX";
    } else {
        researchName.textContent = file.name;
        researchInfo.textContent = humanSize(file.size);
    }
    updateState();
};

/* EVENTS */
dropzone.onclick = () => researchInput.click();

researchInput.onchange = () => {
    if (researchInput.files[0]) setFile(researchInput.files[0]);
    clearMsgs();
};

removeResearch.onclick = () => {
    researchInput.value = "";
    setFile(null);
    setProgress(false);
};

resetBtn.onclick = () => {
    researchInput.value = "";
    rubricInput.value = "";
    formatSelect.value = "";
    titleInput.value = "";
    setFile(null);
    setProgress(false);
    clearMsgs();
};

formatSelect.onchange = updateState;
rubricInput.onchange = updateState;

/* UPLOAD */
const uploadWithProgress = (url, fd, onProgress) =>
    new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", url, true);
        xhr.timeout = 60000;

        if (USER_ID) xhr.setRequestHeader("X-User-Id", USER_ID);

        xhr.upload.onprogress = (e) => {
            if (!e.lengthComputable) return;
            const pct = Math.round((e.loaded / e.total) * 100);
            onProgress(pct);
        };

        xhr.onload = () => {
            const status = xhr.status || 0;
            const txt = xhr.responseText || "";
            const parsed = safeJsonParse(txt);

            if (!parsed.ok) {
                const excerpt = txt.slice(0, 180).replace(/\s+/g, " ").trim();
                return reject(new Error(`Server returned non-JSON (HTTP ${status}): ${excerpt || "empty response"}`));
            }

            if (status < 200 || status >= 300) {
                return reject(new Error(apiErrorMessage(status, parsed.value)));
            }

            resolve(parsed.value);
        };

        xhr.ontimeout = () => reject(new Error("Upload timed out"));
        xhr.onerror = () => reject(new Error("Network error (server unreachable or CORS)"));

        xhr.send(fd);
    });

/* MAIN FLOW */
evaluateBtn.addEventListener("click", async (e) => {
    e.preventDefault();
    e.stopPropagation();

    clearMsgs();

    if (!USER_ID) return toast(errorBox, "Missing user session (RESYNEX_USER_ID). Please login again.");
    if (!file) return toast(errorBox, "Upload a file first.");

    if (evaluateBtn.dataset.busy === "1") return;
    evaluateBtn.dataset.busy = "1";

    evaluateBtn.disabled = true;
    resetBtn.disabled = true;

    const MIN_TOTAL_MS = 3000;
    const startTime = Date.now();

    let visual = 0;
    setProgress(true, visual, "Preparing…", "Starting upload");

    const fd = new FormData();
    fd.append("title", titleInput.value || "Untitled Project");
    fd.append("rubric_id", "1");
    fd.append("format", formatSelect.value || "");
    fd.append("file", file);
    if (rubricInput.files[0]) fd.append("rubric_file", rubricInput.files[0]);

    let smoothTimer = null;
    const smoothStart = Date.now();
    smoothTimer = setInterval(() => {
        const elapsed = Date.now() - smoothStart;
        const t = Math.min(1, elapsed / MIN_TOTAL_MS);
        const target = Math.floor(t * 90);
        if (target > visual) {
            visual = target;
            setProgress(true, visual, "Uploading…", "Sending document");
        }
        if (t >= 1) clearInterval(smoothTimer);
    }, 33);

    try {
        const res = await uploadWithProgress(uploadUrl, fd, (pct) => {
            const realScaled = Math.floor((pct / 100) * 90);
            if (realScaled > visual) {
                visual = realScaled;
                setProgress(true, visual, "Uploading…", "Sending document");
            }
        });

        if (!res || !res.ok) throw new Error(res?.error || "Upload failed");

        toast(okBox, "Upload successful. Preparing status…");

        const elapsedTotal = Date.now() - startTime;
        const remaining = Math.max(0, MIN_TOTAL_MS - elapsedTotal);
        if (remaining) {
            setProgress(true, Math.min(95, Math.max(visual, 90)), "Starting…", `Finalizing (${Math.ceil(remaining / 1000)}s)`);
            await new Promise((r) => setTimeout(r, remaining));
        }

        clearInterval(smoothTimer);
        visual = Math.max(visual, 90);
        setProgress(true, visual, "Done", "Redirecting…");

        const finishTimer = setInterval(() => {
            if (visual >= 100) {
                clearInterval(finishTimer);
                window.location.href = `status.php?job_id=${encodeURIComponent(res.job_id)}`;
                return;
            }
            visual += 2;
            if (visual > 100) visual = 100;
            setProgress(true, visual, "Done", "Redirecting…");
        }, 20);

    } catch (err) {
        clearInterval(smoothTimer);
        toast(errorBox, err?.message || "Upload failed");
        setProgress(false);
        evaluateBtn.disabled = false;
        resetBtn.disabled = false;
        evaluateBtn.dataset.busy = "0";
        updateState();
    }
});

/* INIT */
setFile(null);
updateState();
