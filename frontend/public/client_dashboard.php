<?php
require_once __DIR__ . "/auth.php";
require_login();

$user = current_user();
if (!$user) {
  header("Location: login.php");
  exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

$name  = (isset($user["name"]) && (string)$user["name"] !== "") ? (string)$user["name"] : "Account";
$email = isset($user["email"]) ? (string)$user["email"] : "";

// Display timezone for the UI (Manila)
$DISPLAY_TZ = "Asia/Manila";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Resynex • Dashboard</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.css" />

  <!-- Keep your existing inline CSS exactly as you already have it -->
  <style>
    :root{
      --glass: rgba(12,12,20,.42);
      --line: rgba(255,255,255,.12);
      --text: rgba(255,255,255,.92);
      --muted: rgba(255,255,255,.70);
      --muted2: rgba(255,255,255,.52);

      --violetA:#7C3AED;
      --violetB:#C084FC;

      --ok: rgba(34,197,94,.95);
      --warn: rgba(234,179,8,.95);
      --bad: rgba(239,68,68,.95);
    }

    .dashWrap{ min-height:100vh; padding: 18px; }
    .dash{ max-width: 1180px; margin: 0 auto; display:grid; gap: 14px; }

    .card{
      border: 1px solid var(--line);
      background: var(--glass);
      border-radius: 18px;
      box-shadow: var(--shadow2);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
    }

    .top{
      padding: 12px 14px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .brandRow{ display:flex; align-items:center; gap: 12px; }
    .markDot{
      width: 14px; height: 14px; border-radius: 6px;
      background: rgba(192,132,252,.95);
      box-shadow: 0 0 0 10px rgba(124,58,237,.12);
      flex: 0 0 auto;
    }
    .brandName{ font-weight: 900; letter-spacing:.2px; }
    .brandSub{ margin-top:2px; font-size:12px; color: var(--muted2); }

    .rightRow{ display:flex; align-items:center; gap: 10px; flex-wrap:wrap; justify-content:flex-end; }

    .userChip{
      display:flex;
      align-items:center;
      gap: 10px;
      padding: 8px 10px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(0,0,0,.10);
      color: var(--text);
      font-size: 12px;
      font-weight: 800;
    }
    .userMeta{ display:flex; flex-direction:column; line-height: 1.1; }
    .userEmail{ font-size: 11px; color: var(--muted2); font-weight: 700; margin-top: 4px; }

    .primaryBtn{
      height: 40px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.14);
      background: linear-gradient(135deg, var(--violetA), var(--violetB));
      color: #fff;
      font-weight: 900;
      letter-spacing:.2px;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding: 0 14px;
      box-shadow: 0 16px 36px rgba(124,58,237,.24);
      transition: transform .12s ease, box-shadow .18s ease, filter .18s ease;
    }
    .primaryBtn:hover{ filter: brightness(1.06); box-shadow: 0 22px 50px rgba(124,58,237,.35); }
    .primaryBtn:active{ transform: translateY(1px); }

    .ghostBtn{
      height: 40px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.14);
      color: rgba(255,255,255,.86);
      font-weight: 900;
      font-size: 12px;
      padding: 0 12px;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      transition: border-color .14s ease, background-color .14s ease;
    }
    .ghostBtn:hover{ border-color: rgba(192,132,252,.35); background: rgba(192,132,252,.08); }

    .controls{
      padding: 12px 14px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 12px;
      flex-wrap: wrap;
      overflow: visible;
      position: relative;
      z-index: 10;
    }

    .leftInfo{ min-width: 240px; }
    .h1{ font-weight: 900; font-size: 14px; color: var(--text); }
    .sub{ margin-top: 4px; font-size: 12px; color: var(--muted2); }

    .formRow{
      display:flex;
      align-items:center;
      gap: 10px;
      flex-wrap: wrap;
      justify-content:flex-end;
    }

    .searchWrap{
      position: relative;
      display:flex;
      align-items:center;
      height: 42px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      padding: 0 10px 0 36px;
      min-width: min(520px, 92vw);
      transition: border-color .14s ease, background-color .14s ease, box-shadow .14s ease;
    }
    .searchWrap:focus-within{
      border-color: rgba(192,132,252,.55);
      background: rgba(192,132,252,.08);
      box-shadow: 0 18px 55px rgba(124,58,237,.18);
    }
    .sIcon{
      position:absolute;
      left: 12px;
      font-size: 14px;
      opacity: .7;
      pointer-events:none;
    }
    .searchInp{
      width:100%;
      height: 42px;
      border: none;
      outline: none;
      background: transparent;
      color: rgba(255,255,255,.90);
      font-weight: 800;
      font-size: 12px;
    }
    .searchInp::placeholder{ color: rgba(255,255,255,.45); }

    .clearBtn{
      height: 28px; width: 28px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.14);
      color: rgba(255,255,255,.78);
      display:grid;
      place-items:center;
      cursor:pointer;
      opacity: 0;
      transform: scale(.92);
      transition: opacity .12s ease, transform .12s ease, border-color .12s ease;
    }
    .searchWrap.hasText .clearBtn{ opacity:1; transform: scale(1); }
    .clearBtn:hover{ border-color: rgba(192,132,252,.35); color: rgba(255,255,255,.92); }

    .sortWrap{ position: relative; }

    .sortBtn{
      height: 42px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(0,0,0,.14);
      color: rgba(255,255,255,.88);
      font-weight: 900;
      font-size: 12px;
      padding: 0 12px;
      display:flex;
      align-items:center;
      gap: 8px;
      cursor:pointer;
      transition: border-color .14s ease, background-color .14s ease, box-shadow .14s ease;
    }
    .sortBtn:hover{
      border-color: rgba(192,132,252,.35);
      background: rgba(192,132,252,.08);
      box-shadow: 0 18px 55px rgba(124,58,237,.14);
    }
    .sortLabel{ opacity: .72; }
    .caret{ opacity:.75; transition: transform .14s ease; }
    .sortBtn[aria-expanded="true"] .caret{ transform: rotate(180deg); }

    .sortMenu{
      position:absolute;
      right: 0;
      top: calc(100% + 10px);
      width: 240px;
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(15,15,25,.62);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      box-shadow: 0 28px 90px rgba(0,0,0,.55);
      padding: 8px;
      display:none;
      transform-origin: top right;
      z-index: 999;
    }
    .sortMenu.open{ display:block; animation: menuIn .14s ease-out both; }
    @keyframes menuIn{
      from{ opacity:0; transform: translateY(-6px) scale(.98); }
      to{ opacity:1; transform: translateY(0) scale(1); }
    }

    .sortItem{
      width:100%;
      text-align:left;
      height: 36px;
      border-radius: 12px;
      border: 1px solid transparent;
      background: transparent;
      color: rgba(255,255,255,.86);
      font-weight: 800;
      font-size: 12px;
      padding: 0 10px;
      cursor:pointer;
      display:flex;
      align-items:center;
      justify-content:space-between;
      transition: background-color .12s ease, border-color .12s ease, transform .10s ease;
    }
    .sortItem:hover{ background: rgba(192,132,252,.10); border-color: rgba(192,132,252,.18); transform: translateX(1px); }
    .sortItem.active{ background: rgba(124,58,237,.18); border-color: rgba(192,132,252,.28); }
    .sortItem.active::after{ content:"✓"; opacity:.9; font-weight:900; }
    .sortSep{ height: 1px; background: rgba(255,255,255,.08); margin: 6px 4px; }

    .quickChips{ display:flex; gap: 8px; flex-wrap: wrap; justify-content:flex-end; }
    .chipBtn{
      height: 36px;
      padding: 0 12px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.12);
      color: rgba(255,255,255,.84);
      font-weight: 900;
      font-size: 12px;
      cursor:pointer;
      transition: border-color .12s ease, background-color .12s ease, transform .12s ease;
      position: relative;
    }
    .chipBtn:hover{ border-color: rgba(192,132,252,.28); background: rgba(192,132,252,.08); transform: translateY(-1px); }
    .chipBtn.active{ border-color: rgba(192,132,252,.40); background: rgba(124,58,237,.18); }

    .loadingDot{
      width: 8px; height: 8px;
      border-radius: 999px;
      background: rgba(192,132,252,.95);
      box-shadow: 0 0 0 8px rgba(124,58,237,.12);
      animation: pulse .75s ease-in-out infinite;
      display:inline-block;
      margin-left: 8px;
      vertical-align: middle;
    }
    @keyframes pulse { 0%,100%{ transform: scale(.9); opacity: .6; } 50%{ transform: scale(1); opacity: 1; } }

    .gridWrap{ overflow: visible; }
    .grid{
      padding: 14px;
      display:grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }
    @media (max-width: 1100px){ .grid{ grid-template-columns: repeat(2, 1fr);} }
    @media (max-width: 720px){
      .searchWrap{ min-width: 100%; }
      .quickChips{ justify-content:flex-start; }
    }
    @media (max-width: 640px){ .grid{ grid-template-columns: 1fr;} }

    .ev{
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(0,0,0,.12);
      padding: 12px;
      display:flex;
      flex-direction:column;
      gap: 10px;
      min-height: 150px;
      transition: border-color .14s ease, background-color .14s ease, transform .14s ease;
    }
    .ev:hover{ border-color: rgba(192,132,252,.28); background: rgba(192,132,252,.06); transform: translateY(-2px); }

    .evTop{ display:flex; align-items:flex-start; justify-content:space-between; gap: 10px; }
    .file{
      font-weight: 900;
      font-size: 13px;
      line-height: 1.25;
      color: var(--text);
      overflow:hidden;
      text-overflow:ellipsis;
      display:-webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }
    .meta{ margin-top:4px; font-size: 12px; color: var(--muted2); }

    .score{
      font-weight: 900;
      font-size: 12px;
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.14);
      white-space: nowrap;
    }
    .score.good{ border-color: rgba(34,197,94,.25); background: rgba(34,197,94,.10); }
    .score.work{ border-color: rgba(234,179,8,.25); background: rgba(234,179,8,.10); }
    .score.critical{ border-color: rgba(239,68,68,.28); background: rgba(239,68,68,.10); }

    .chips{ display:flex; gap: 8px; flex-wrap: wrap; }
    .pill{
      font-size: 11px;
      font-weight: 900;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(0,0,0,.10);
      color: rgba(255,255,255,.84);
    }
    .pill.ok{ border-color: rgba(34,197,94,.25); background: rgba(34,197,94,.08); }
    .pill.warn{ border-color: rgba(234,179,8,.25); background: rgba(234,179,8,.08); }
    .pill.muted{ opacity: .85; }

    .tag{
      font-size: 11px;
      font-weight: 900;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.03);
      color: rgba(255,255,255,.78);
      overflow:hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 100%;
    }

    .evActions{ margin-top:auto; display:flex; justify-content:flex-end; }
    .viewBtn{
      height: 38px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.14);
      color: rgba(255,255,255,.88);
      font-weight: 900;
      font-size: 12px;
      padding: 0 12px;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      transition: border-color .14s ease, background-color .14s ease, transform .12s ease;
    }
    .viewBtn:hover{ border-color: rgba(192,132,252,.35); background: rgba(192,132,252,.08); transform: translateY(-1px); }

    .empty{
      margin: 14px;
      padding: 14px;
      border-radius: 16px;
      border: 1px dashed rgba(255,255,255,.14);
      background: rgba(0,0,0,.10);
      color: rgba(255,255,255,.78);
      font-size: 12px;
    }

    .pager{
      padding: 12px 14px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap: 10px;
      flex-wrap: wrap;
      color: rgba(255,255,255,.72);
      font-size: 12px;
    }
    .pagerBtns{ display:flex; gap:10px; flex-wrap:wrap; }

    .skeleton{
      position: relative;
      overflow: hidden;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.04);
      min-height: 150px;
    }
    .skeleton::after{
      content:"";
      position:absolute;
      inset:-1px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.10), transparent);
      transform: translateX(-60%);
      animation: shimmer 1.05s ease-in-out infinite;
    }
    @keyframes shimmer { to { transform: translateX(60%); } }
  </style>
</head>

<body>
  <div class="bg"></div>
  <div class="grain"></div>

  <div class="dashWrap">
    <div class="dash">

      <header class="card top">
        <div class="brandRow">
          <div class="markDot" aria-hidden="true"></div>
          <div>
            <div class="brandName">Resynex</div>
            <div class="brandSub">Your evaluation history, ready when you are.</div>
          </div>
        </div>

        <div class="rightRow">
          <div class="userChip">
            <div class="userMeta">
              <div><?php echo h($name); ?></div>
              <div class="userEmail"><?php echo h($email); ?></div>
            </div>
          </div>
          <a class="primaryBtn" href="index.php">Evaluate document</a>
          <a class="ghostBtn" href="logout.php">Logout</a>
        </div>
      </header>

      <section class="card controls">
        <div class="leftInfo">
          <div class="h1">Recent evaluations <span id="busyDot" class="loadingDot" style="display:none;"></span></div>
          <div class="sub" id="subline">Loading…</div>
        </div>

        <div class="formRow" autocomplete="off">
          <div class="searchWrap" id="searchWrap">
            <span class="sIcon" aria-hidden="true">⌕</span>
            <input class="searchInp" id="searchInp" type="text" placeholder="Search filename, project, decision…" />
            <button class="clearBtn" id="clearBtn" type="button" title="Clear">×</button>
          </div>

          <div class="sortWrap">
            <button class="sortBtn" id="sortBtn" type="button" aria-haspopup="menu" aria-expanded="false">
              <span class="sortLabel">Sort:</span>
              <span id="sortBtnText">Newest first</span>
              <span class="caret" aria-hidden="true">▾</span>
            </button>

            <div class="sortMenu" id="sortMenu" role="menu" aria-label="Sort options">
              <button class="sortItem" type="button" role="menuitem" data-sort="newest">Newest first</button>
              <button class="sortItem" type="button" role="menuitem" data-sort="oldest">Oldest first</button>
              <div class="sortSep"></div>
              <button class="sortItem" type="button" role="menuitem" data-sort="filename_az">Alphabetical (A–Z)</button>
              <button class="sortItem" type="button" role="menuitem" data-sort="filename_za">Alphabetical (Z–A)</button>
              <div class="sortSep"></div>
              <button class="sortItem" type="button" role="menuitem" data-sort="score_high">Score (high → low)</button>
              <button class="sortItem" type="button" role="menuitem" data-sort="score_low">Score (low → high)</button>
              <button class="sortItem" type="button" role="menuitem" data-sort="decision">Decision</button>
            </div>
          </div>

          <div class="quickChips" id="chips">
            <button class="chipBtn active" type="button" data-flt="">All</button>
            <button class="chipBtn" type="button" data-flt="good">Good</button>
            <button class="chipBtn" type="button" data-flt="work">Needs work</button>
            <button class="chipBtn" type="button" data-flt="critical">Critical</button>
          </div>
        </div>
      </section>

      <section class="card gridWrap">
        <div class="grid" id="grid"></div>

        <div class="pager" id="pager" style="display:none;">
          <div id="pagerLeft"></div>
          <div class="pagerBtns">
            <button class="ghostBtn" id="prevBtn" type="button">Prev</button>
            <button class="ghostBtn" id="nextBtn" type="button">Next</button>
          </div>
        </div>
      </section>

    </div>
  </div>
   
  <script>
    window.RESYNEX_DASH_API = "api_dashboard.php";
    window.RESYNEX_TZ = <?php echo json_encode($DISPLAY_TZ, JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script src="assets/js/client_dashboard.js?v=2"></script>
</body>
</html>
