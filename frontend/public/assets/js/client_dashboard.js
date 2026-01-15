(function () {
    var API = window.RESYNEX_DASH_API || "api_dashboard.php";
    var DISPLAY_TZ = window.RESYNEX_TZ || "Asia/Manila";

    var grid = document.getElementById("grid");
    var subline = document.getElementById("subline");
    var busyDot = document.getElementById("busyDot");

    var searchInp = document.getElementById("searchInp");
    var searchWrap = document.getElementById("searchWrap");
    var clearBtn = document.getElementById("clearBtn");

    var sortBtn = document.getElementById("sortBtn");
    var sortBtnText = document.getElementById("sortBtnText");
    var sortMenu = document.getElementById("sortMenu");
    var sortItems = document.querySelectorAll(".sortItem");

    var chipsWrap = document.getElementById("chips");
    var pager = document.getElementById("pager");
    var pagerLeft = document.getElementById("pagerLeft");
    var prevBtn = document.getElementById("prevBtn");
    var nextBtn = document.getElementById("nextBtn");

    var state = {
        q: "",
        sort: "newest",
        flt: "",
        page: 1,
        limit: 18
    };

    function setBusy(on) {
        if (busyDot) busyDot.style.display = on ? "inline-block" : "none";
    }

    function setSearchState() {
        if (!searchWrap || !searchInp) return;
        if (searchInp.value && searchInp.value.trim() !== "") searchWrap.classList.add("hasText");
        else searchWrap.classList.remove("hasText");
    }

    function sortLabel(val) {
        if (val === "oldest") return "Oldest first";
        if (val === "filename_az") return "Alphabetical (A–Z)";
        if (val === "filename_za") return "Alphabetical (Z–A)";
        if (val === "score_high") return "Score (high → low)";
        if (val === "score_low") return "Score (low → high)";
        if (val === "decision") return "Decision";
        return "Newest first";
    }

    function openMenu() {
        if (!sortMenu || !sortBtn) return;
        sortMenu.classList.add("open");
        sortBtn.setAttribute("aria-expanded", "true");
    }

    function closeMenu() {
        if (!sortMenu || !sortBtn) return;
        sortMenu.classList.remove("open");
        sortBtn.setAttribute("aria-expanded", "false");
    }

    function toggleMenu() {
        if (!sortMenu) return;
        if (sortMenu.classList.contains("open")) closeMenu();
        else openMenu();
    }

    function setActiveSort() {
        for (var i = 0; i < sortItems.length; i++) {
            var it = sortItems[i];
            var s = it.getAttribute("data-sort");
            if (s === state.sort) it.classList.add("active");
            else it.classList.remove("active");
        }
        if (sortBtnText) sortBtnText.textContent = sortLabel(state.sort);
    }

    function setActiveChip() {
        if (!chipsWrap) return;
        var btns = chipsWrap.querySelectorAll(".chipBtn");
        for (var i = 0; i < btns.length; i++) {
            var b = btns[i];
            var f = b.getAttribute("data-flt");
            if (f === state.flt) b.classList.add("active");
            else b.classList.remove("active");
        }
    }

    function qs(obj) {
        var parts = [];
        for (var k in obj) {
            if (!obj.hasOwnProperty(k)) continue;
            var v = obj[k];
            if (v === null || v === undefined) continue;
            parts.push(encodeURIComponent(k) + "=" + encodeURIComponent(String(v)));
        }
        return parts.join("&");
    }

    function renderSkeleton() {
        if (!grid) return;
        grid.innerHTML = "";
        for (var i = 0; i < state.limit; i++) {
            var d = document.createElement("div");
            d.className = "skeleton";
            grid.appendChild(d);
        }
        if (pager) pager.style.display = "none";
    }

    function pillClass(decision) {
        var d = (decision || "").toString().trim().toUpperCase();
        if (d === "CLEAR") return "ok";
        if (d === "REVISE") return "warn";
        return "muted";
    }

    function esc(s) {
        return (s === null || s === undefined) ? "" : String(s)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;");
    }

    // ✅ Correct time formatting using Asia/Manila (or whatever is set)
    function formatEpochUTC(epochSec) {
        if (!epochSec || epochSec <= 0) return "—";
        var d = new Date(epochSec * 1000);

        try {
            return new Intl.DateTimeFormat(undefined, {
                timeZone: DISPLAY_TZ,
                year: "numeric",
                month: "short",
                day: "numeric",
                hour: "numeric",
                minute: "2-digit"
            }).format(d);
        } catch (e) {
            // fallback: browser local
            return d.toLocaleString();
        }
    }

    function render(items, meta) {
        if (!grid) return;

        if (!items || !items.length) {
            grid.innerHTML = '<div class="empty">No evaluations found.</div>';
            if (pager) pager.style.display = "none";
            if (subline) subline.textContent = (meta.total || 0) + " total • filtered by your account";
            return;
        }

        var html = "";
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var score = (it.score || 0);
            var bucket = it.bucket || "work";
            var jobId = it.job_id || 0;

            var createdLabel = formatEpochUTC(it.created_epoch_utc || 0);

            html += '<div class="ev">';
            html += '  <div class="evTop">';
            html += '    <div style="min-width:0;">';
            html += '      <div class="file">' + esc(it.filename) + '</div>';
            html += '      <div class="meta">' + esc(it.project_title) + '</div>';
            html += '      <div class="meta">' + esc(createdLabel) + '</div>';
            html += '    </div>';
            html += '    <div class="score ' + esc(bucket) + '">' + (score | 0) + '/100</div>';
            html += '  </div>';

            html += '  <div class="chips">';
            html += '    <span class="pill ' + esc(pillClass(it.decision)) + '">' + esc(it.decision) + '</span>';
            html += '    <span class="tag">' + esc(it.rubric_label) + '</span>';
            html += '  </div>';

            html += '  <div class="evActions">';
            if (jobId > 0) html += '    <a class="viewBtn" href="report.php?job_id=' + (jobId | 0) + '">View report</a>';
            else html += '    <span class="tag">No job id</span>';
            html += '  </div>';
            html += '</div>';
        }

        grid.innerHTML = html;

        // pager
        if (pager && pagerLeft && prevBtn && nextBtn) {
            var page = meta.page || 1;
            var totalPages = meta.total_pages || 1;
            pagerLeft.textContent = "Page " + page + " / " + totalPages + " • Showing " + items.length + " of " + (meta.total || 0);
            pager.style.display = totalPages > 1 ? "flex" : "none";

            prevBtn.disabled = page <= 1;
            nextBtn.disabled = page >= totalPages;
        }

        if (subline) {
            var parts = [];
            parts.push((meta.total || 0) + " total");
            if (state.flt) parts.push(state.flt === "work" ? "Needs work" : (state.flt.charAt(0).toUpperCase() + state.flt.slice(1)));
            if (state.q) parts.push("Search active");
            subline.textContent = parts.join(" • ");
        }
    }

    var requestId = 0;

    function fetchData(opts) {
        if (opts) {
            for (var k in opts) {
                if (opts.hasOwnProperty(k)) state[k] = opts[k];
            }
        }

        requestId++;
        var rid = requestId;

        setBusy(true);
        renderSkeleton();

        var url = API + "?" + qs({
            q: state.q,
            sort: state.sort,
            flt: state.flt,
            page: state.page,
            limit: state.limit
        });

        return fetch(url, { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (rid !== requestId) return;
                setBusy(false);

                if (!j || !j.ok) {
                    grid.innerHTML = '<div class="empty">Failed to load dashboard data.</div>';
                    if (pager) pager.style.display = "none";
                    return;
                }

                render(j.items, j);
            })
            .catch(function () {
                if (rid !== requestId) return;
                setBusy(false);
                grid.innerHTML = '<div class="empty">Network error. Please refresh.</div>';
                if (pager) pager.style.display = "none";
            });
    }

    // Debounced search
    var t = 0;
    function scheduleSearch() {
        if (t) window.clearTimeout(t);
        t = window.setTimeout(function () {
            state.page = 1;
            fetchData();
        }, 220);
    }

    // Wire up UI
    if (searchInp) {
        searchInp.addEventListener("input", function () {
            state.q = searchInp.value.trim();
            setSearchState();
            scheduleSearch();
        });

        searchInp.addEventListener("keydown", function (e) {
            if (e.key === "Escape") {
                searchInp.value = "";
                state.q = "";
                setSearchState();
                state.page = 1;
                fetchData();
            }
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener("click", function () {
            if (!searchInp) return;
            searchInp.value = "";
            state.q = "";
            setSearchState();
            state.page = 1;
            fetchData();
            searchInp.focus();
        });
    }

    if (sortBtn) sortBtn.addEventListener("click", toggleMenu);

    for (var i = 0; i < sortItems.length; i++) {
        sortItems[i].addEventListener("click", function () {
            var v = this.getAttribute("data-sort");
            state.sort = v || "newest";
            state.page = 1;
            setActiveSort();
            closeMenu();
            fetchData();
        });
    }

    if (chipsWrap) {
        chipsWrap.addEventListener("click", function (e) {
            var t = e.target;
            if (!t) return;
            if (!t.classList.contains("chipBtn")) return;
            state.flt = t.getAttribute("data-flt") || "";
            state.page = 1;
            setActiveChip();
            fetchData();
        });
    }

    document.addEventListener("click", function (e) {
        if (!sortMenu || !sortBtn) return;
        var t = e.target;
        if (sortMenu.contains(t) || sortBtn.contains(t)) return;
        closeMenu();
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") closeMenu();
    });

    if (prevBtn) {
        prevBtn.addEventListener("click", function () {
            if (state.page <= 1) return;
            state.page -= 1;
            fetchData();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener("click", function () {
            state.page += 1;
            fetchData();
        });
    }

    // init
    setSearchState();
    setActiveSort();
    setActiveChip();
    fetchData();
})();
