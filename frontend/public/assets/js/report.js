(() => {
    const API = window.RESYNEX_API_BASE || "http://127.0.0.1:8000";
    const JOB_ID = String(window.RESYNEX_JOB_ID || "");
    const USER_ID = String(window.RESYNEX_USER_ID || "");

    const $ = (id) => document.getElementById(id);
    const safeArray = (x) => (Array.isArray(x) ? x : []);
    const normStatus = (s) => String(s || "").trim().toUpperCase();
    const normCat = (s) => (String(s || "General").trim() || "General");

    // ONLY ONE esc
    const esc = (s) =>
        String(s ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");

    const el = {
        loading: $("loading"),
        report: $("report"),
        errorState: $("errorState"),

        scoreBig: $("scoreBig"),
        decisionTitle: $("decisionTitle"),
        decisionSub: $("decisionSub"),
        levelBadge: $("levelBadge"),
        thresholdText: $("thresholdText"),

        blockers: $("blockers"),
        nextActions: $("nextActions"),
        strengths: $("strengths"),
        weakCategories: $("weakCategories"),

        checksBody: $("checksBody"),
        checksWrap: $("checksWrap"),
        toggleChecksBtn: $("toggleChecksBtn"),
        downloadBtn: $("downloadBtn"),

        resultCard: $("resultCard"),
        donutCard: $("donutCard"),
        statusPillText: $("statusPillText"),

        donutValue: $("donutValue"),
        donutPct: $("donutPct"),
        donutLabel: $("donutLabel"),

        calloutTitle: $("calloutTitle"),
        calloutBody: $("calloutBody"),

        nlpBadge: $("nlpBadge"),
        nlpBadgeText: $("nlpBadgeText"),

        nlpModal: $("nlpModal"),
        nlpModalBackdrop: $("nlpModalBackdrop"),
        nlpModalClose: $("nlpModalClose"),
        nlpModalOk: $("nlpModalOk"),
        nlpModalSub: $("nlpModalSub"),
        nlpModalBody: $("nlpModalBody"),
    };

    let lastPreflight = null;

    function hideNode(n) {
        if (!n) return;
        n.hidden = true;
        n.style.display = "none";
    }

    function showNode(n) {
        if (!n) return;
        n.hidden = false;
        n.style.display = "";
    }

    function showError(msg) {
        hideNode(el.loading);
        hideNode(el.report);
        showNode(el.errorState);
        el.errorState.textContent = msg;
    }

    function showReport() {
        hideNode(el.loading);
        hideNode(el.errorState);
        showNode(el.report);
    }

    function themeForScore(score) {
        const s = Math.max(0, Math.min(100, Math.round(Number(score) || 0)));
        if (s >= 75) return { s, theme: "rThemeOk", label: "Good" };
        if (s < 55) return { s, theme: "rThemeBad", label: "Critical" };
        return { s, theme: "rThemeWarn", label: "Needs work" };
    }

    function applyTheme(theme) {
        [el.resultCard, el.donutCard].forEach((node) => {
            if (!node) return;
            node.classList.remove("rThemeOk", "rThemeWarn", "rThemeBad");
            node.classList.add(theme);
        });
    }

    function statusTag(status) {
        const s = normStatus(status);
        const cls = s === "PASS" ? "pass" : s === "WEAK" ? "weak" : "fail";
        return `<span class="tag ${cls}">${esc(s || "—")}</span>`;
    }

    function summarizeChecks(checks) {
        return safeArray(checks).map((c) => ({
            name: c?.name || "Check",
            status: normStatus(c?.status || "WEAK"),
            why: String(c?.why || ""),
            evidence: String(c?.evidence || ""),
            category: normCat(c?.category),
            weight: Number(c?.weight ?? 0),
        }));
    }

    function buildFixFirst(items, max = 3) {
        const prio = (s) => (s === "FAIL" ? 2 : s === "WEAK" ? 1 : 0);
        return [...items]
            .filter((x) => x.status !== "PASS")
            .sort((a, b) => prio(b.status) - prio(a.status))
            .slice(0, max)
            .map((x) => ({
                title: x.name,
                reason: x.why.trim(),
                evidence: x.evidence.trim(),
                status: x.status,
            }));
    }

    function buildStrengths(items, max = 4) {
        return items.filter((x) => x.status === "PASS").slice(0, max).map((x) => x.name);
    }

    function topWeakCategories(items, topN = 3) {
        const pts = (s) => (s === "FAIL" ? 2 : s === "WEAK" ? 1 : 0);
        const byCat = new Map();

        for (const c of items) {
            const cat = c.category || "General";
            const cur = byCat.get(cat) || { cat, score: 0, fail: 0, weak: 0 };
            cur.score += pts(c.status);
            if (c.status === "FAIL") cur.fail++;
            if (c.status === "WEAK") cur.weak++;
            byCat.set(cat, cur);
        }

        return [...byCat.values()]
            .sort((a, b) => b.score - a.score)
            .slice(0, topN)
            .map((x) => `${x.cat}: ${x.fail} FAIL, ${x.weak} WEAK`);
    }

    function renderActionsList(container, items, emptyMsg) {
        if (!container) return;
        container.innerHTML = "";

        const list = safeArray(items);
        if (!list.length) {
            container.innerHTML = `<div class="emptyMini">${esc(emptyMsg)}</div>`;
            return;
        }

        list.forEach((text, i) => {
            const row = document.createElement("div");
            row.className = "actionRow animIn";
            row.style.animationDelay = `${i * 60}ms`;
            row.innerHTML = `<div class="checkDot"></div><div class="actionText">${esc(text)}</div>`;
            container.appendChild(row);
        });
    }

    function renderBlockers(blockers) {
        if (!el.blockers) return;
        el.blockers.innerHTML = "";

        const list = safeArray(blockers);
        if (!list.length) {
            el.blockers.innerHTML = `<div class="emptyMini">No blockers returned by evaluator.</div>`;
            return;
        }

        list.forEach((b, i) => {
            const card = document.createElement("div");
            card.className = "blockerCard animIn";
            card.style.animationDelay = `${i * 80}ms`;

            const reason = String(b?.reason || "").trim();
            const evidence = String(b?.evidence || "").trim();

            card.innerHTML = `
        <div class="blockerTop">
          <div class="blockerNum">${i + 1}</div>
          <div class="blockerTitle">${esc(b?.title || "Blocker")}</div>
        </div>
        ${reason ? `<div class="blockerReason">${esc(reason)}</div>` : `<div class="blockerReason tdMuted">No reason returned by evaluator.</div>`}
        ${evidence ? `<div class="blockerEv">${esc(evidence)}</div>` : `<div class="blockerEv tdMuted">No evidence returned by evaluator.</div>`}
      `;
            el.blockers.appendChild(card);
        });
    }

    function renderChecks(items) {
        if (!el.checksBody) return;
        el.checksBody.innerHTML = "";

        safeArray(items).forEach((c) => {
            const tr = document.createElement("tr");
            tr.className = "rowAnim";

            const why = c.why.trim();
            const evidence = c.evidence.trim();

            tr.innerHTML = `
        <td class="tdStrong">
          ${esc(c.name)}
          <div class="tdMuted tdTiny">${esc(c.category)}</div>
        </td>
        <td>${statusTag(c.status)}</td>
        <td class="tdMuted">${why ? esc(why) : "—"}</td>
        <td class="tdMuted">${evidence ? esc(evidence) : "—"}</td>
      `;
            el.checksBody.appendChild(tr);
        });
    }

    function setCallout(pass, blockersCount) {
        if (!el.calloutTitle || !el.calloutBody) return;
        el.calloutTitle.textContent = "Next step";
        el.calloutBody.textContent = pass
            ? "If needed, do a light revision and re-upload to confirm. Otherwise you can proceed."
            : blockersCount
                ? "Start with the top blocker. Re-upload after fixing the first 1–2 FAIL items."
                : "No blockers returned. Re-check rubric settings and re-upload.";
    }

    // SCORE micro animation
    function animateNumber(elNode, toValue, opts = {}) {
        if (!elNode) return;

        const duration = Math.max(280, Math.min(1400, Number(opts.duration || 720)));
        const fromValue = Number(opts.from ?? 0);
        const end = Math.max(0, Math.min(100, Math.round(Number(toValue) || 0)));

        elNode.classList.remove("scorePop", "scoreGlow");
        void elNode.offsetWidth;
        elNode.classList.add("scorePop");

        const start = performance.now();

        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            const val = Math.round(fromValue + (end - fromValue) * eased);
            elNode.textContent = String(val);

            if (t < 1) requestAnimationFrame(tick);
            else {
                elNode.classList.add("scoreGlow");
                setTimeout(() => elNode.classList.remove("scoreGlow"), 900);
            }
        };

        requestAnimationFrame(tick);
    }

    // DONUT anim (always animates on refresh)
    function animateDonut(score) {
        if (!el.donutValue || !el.donutPct || !el.donutLabel) return;

        const { s, theme, label } = themeForScore(score);
        applyTheme(theme);

        if (el.statusPillText) el.statusPillText.textContent = label;

        el.donutPct.textContent = `${s}%`;
        el.donutLabel.textContent = label;

        const r = 46;
        const C = 2 * Math.PI * r;

        // reset to 0, then animate to target
        el.donutValue.style.strokeDasharray = `0 ${C}`;
        el.donutValue.classList.remove("donutDraw");
        void el.donutValue.offsetWidth;
        el.donutValue.classList.add("donutDraw");

        const start = performance.now();
        const duration = 850;

        const tick = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            const cur = (s / 100) * C * eased;
            el.donutValue.style.strokeDasharray = `${cur} ${C}`;
            if (t < 1) requestAnimationFrame(tick);
        };

        requestAnimationFrame(tick);
    }

    // SMART badge + modal
    function setSmartBadgeFromPreflight(pre) {
        if (!el.nlpBadge || !el.nlpBadgeText) return;
        if (!pre) { hideNode(el.nlpBadge); return; }

        lastPreflight = pre;
        const verdict = String(pre.verdict || "").toLowerCase();

        let cls = "nlpOk";
        let label = "Smart • OK";

        if (verdict === "too_short") { cls = "nlpWarn"; label = "Smart • Short"; }
        else if (verdict === "scanned") { cls = "nlpBad"; label = "Smart • OCR"; }
        else if (verdict === "empty_or_nonsense") { cls = "nlpBad"; label = "Smart • Empty"; }

        el.nlpBadge.classList.remove("nlpOk", "nlpWarn", "nlpBad");
        el.nlpBadge.classList.add(cls);
        el.nlpBadgeText.textContent = label;

        showNode(el.nlpBadge);
    }

    function kvRow(k, v) {
        const val = (v === null || v === undefined || v === "") ? "—" : String(v);
        const muted = val === "—" ? " muted" : "";
        return `<div class="kvRow"><div class="kvKey">${esc(k)}</div><div class="kvVal${muted}">${esc(val)}</div></div>`;
    }

    function openSmartModal() {
        if (!el.nlpModal || !lastPreflight) return;
        const pre = lastPreflight;

        const msg = pre.message || "Smart content validation signals.";
        if (el.nlpModalSub) el.nlpModalSub.textContent = msg;

        const rows = [];
        rows.push(kvRow("Verdict", pre.verdict || "—"));
        rows.push(kvRow("Kind", pre.kind || "—"));
        rows.push(kvRow("Word count", pre.word_count));
        rows.push(kvRow("Unique words", pre.unique_word_count));
        rows.push(kvRow("Alphanumeric", pre.alpha_num_count));
        rows.push(kvRow("Symbol ratio", pre.symbol_ratio));
        rows.push(kvRow("Units detected", pre.units_count));
        rows.push(kvRow("Confidence", pre.confidence));

        if (el.nlpModalBody) el.nlpModalBody.innerHTML = rows.join("");

        el.nlpModal.hidden = false;
        el.nlpModal.style.display = "block";
        document.body.classList.add("modalOpen");
    }

    function closeSmartModal() {
        if (!el.nlpModal) return;
        el.nlpModal.hidden = true;
        el.nlpModal.style.display = "none";
        document.body.classList.remove("modalOpen");
    }

    async function fetchReport() {
        if (!USER_ID || USER_ID === "0") throw new Error("missing_user");

        const res = await fetch(`${API}/api/evaluations/by-job/${encodeURIComponent(JOB_ID)}`, {
            method: "GET",
            headers: {
                "X-User-Id": USER_ID,
                "Accept": "application/json",
                "Cache-Control": "no-cache",
            },
            cache: "no-store",
        });

        const json = await res.json().catch(() => ({}));

        if (res.status === 401) throw new Error("unauthorized");
        if (res.status === 403) throw new Error("forbidden");
        if (!res.ok) throw new Error(json?.detail || json?.error || `HTTP_${res.status}`);
        if (!json.ok) throw new Error(json.error || "report_not_ready");

        return json;
    }

    async function init() {
        if (!JOB_ID) return showError("Missing job_id. Upload your document again.");

        // ensure skeleton visible at start
        showNode(el.loading);
        hideNode(el.report);
        hideNode(el.errorState);

        const start = Date.now();
        const MAX_WAIT_MS = 120000;

        while (true) {
            try {
                const data = await fetchReport();

                const summary = data.summary || {};
                const score = Number(data.score ?? summary.score ?? 0);
                const threshold = Number(summary.threshold ?? 75);
                const decision = String(data.decision || summary.decision || "Revise");
                const level = String(data.level || summary.level || "—");

                const pass = score >= threshold || decision.toLowerCase() === "clear";

                // Title + Sub
                if (el.decisionTitle) el.decisionTitle.textContent = pass ? "You’re ready to submit." : "Not ready yet.";
                if (el.decisionSub) {
                    el.decisionSub.textContent = pass
                        ? "Your document meets the evaluator threshold. Review weak items if you want a stronger version."
                        : "Fix the FAIL items first, then re-upload. The report below only shows what the evaluator found in your document.";
                }

                if (el.levelBadge) el.levelBadge.textContent = level;
                if (el.thresholdText) el.thresholdText.textContent = `Threshold: ${threshold}`;

                // score + donut animation every refresh
                if (el.scoreBig) animateNumber(el.scoreBig, score, { from: 0, duration: 760 });
                animateDonut(score);

                const items = summarizeChecks(data.checks);

                const strengths = safeArray(data.strengths).length
                    ? safeArray(data.strengths).slice(0, 6)
                    : buildStrengths(items, 4);

                const blockers = safeArray(data.blockers).length
                    ? safeArray(data.blockers).slice(0, 4)
                    : buildFixFirst(items, 3);

                const nextActions = safeArray(data.fix_plan?.next_actions).length
                    ? safeArray(data.fix_plan.next_actions).slice(0, 6)
                    : blockers.map((b) => b.title).filter(Boolean).map((t) => `Fix: ${t}`);

                const weakCats = topWeakCategories(items, 3);

                setCallout(pass, safeArray(blockers).length);

                renderBlockers(blockers);
                renderActionsList(el.nextActions, nextActions, "No actions returned by evaluator.");
                renderActionsList(el.strengths, strengths, "No strengths returned by evaluator.");
                renderActionsList(el.weakCategories, weakCats, "No weak categories detected.");
                renderChecks(items);

                // SMART badge from preflight
                setSmartBadgeFromPreflight(summary.preflight || null);

                showReport();
                return;

            } catch (e) {
                const code = String(e?.message || "");

                if (code === "report_not_ready" || code === "evaluation_not_ready") {
                    if (Date.now() - start > MAX_WAIT_MS) {
                        return showError("This is taking longer than usual. Please refresh or re-upload a cleaner DOCX.");
                    }
                    await new Promise((r) => setTimeout(r, 1100));
                    continue;
                }

                if (code === "unauthorized") return showError("401 Unauthorized. Please log in again.");
                if (code === "forbidden") return showError("403 Forbidden. This job does not belong to your account.");
                if (code === "missing_user") return showError("Missing user id. Please log in again.");

                return showError(code || "Unable to load report.");
            }
        }
    }

    // UI listeners
    el.toggleChecksBtn?.addEventListener("click", () => {
        if (!el.checksWrap) return;
        const hidden = !el.checksWrap.hidden;
        el.checksWrap.hidden = hidden;
        el.checksWrap.style.display = hidden ? "none" : "";
        el.toggleChecksBtn.textContent = hidden ? "Show details" : "Hide details";
        if (!hidden) el.checksWrap.scrollIntoView({ behavior: "smooth", block: "start" });
    });

    el.downloadBtn?.addEventListener("click", () => {
        alert("Download export is next. For now, screenshot or copy your results.");
    });

    // modal open/close
    el.nlpBadge?.addEventListener("click", openSmartModal);
    el.nlpModalClose?.addEventListener("click", closeSmartModal);
    el.nlpModalOk?.addEventListener("click", closeSmartModal);
    el.nlpModalBackdrop?.addEventListener("click", closeSmartModal);

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && el.nlpModal && !el.nlpModal.hidden) closeSmartModal();
    });

    init();
})();
