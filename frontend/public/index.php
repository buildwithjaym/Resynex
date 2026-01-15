<?php
require_once __DIR__ . "/auth.php";
require_login();

$user = current_user();
$display_name = "Account";
if ($user && isset($user["name"]) && $user["name"] !== "") {
  $display_name = $user["name"];
}

$API_UPLOAD_URL = "http://127.0.0.1:8000/api/projects/upload";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Resynex • Upload</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    .userChip {
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding: 8px 12px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.12);
      color: rgba(255,255,255,.86);
      font-weight: 900;
      font-size: 12px;
    }
    .userDot{
      width:10px;height:10px;border-radius:999px;
      background: rgba(192,132,252,.95);
      box-shadow: 0 0 0 6px rgba(124,58,237,.14);
    }
    .topRight {
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      justify-content:flex-end;
    }
  </style>
</head>
<body>
  <div class="bg"></div>
  <div class="grain"></div>

  <main class="wrap compact">
    <header class="topbar">
      <div class="brand">
        <div class="mark" aria-hidden="true"></div>
        <div class="brandText">
          <div class="brandName">Resynex</div>
          <div class="brandSub">Research Evaluation at Scale</div>
        </div>
      </div>

      <div class="topRight">
        <div class="chips">
          <span class="chip">PDF</span>
          <span class="chip">DOCX</span>
          <span class="chip strong">75+ to clear</span>
        </div>

        <div class="userChip" title="Logged in">
          <span class="userDot" aria-hidden="true"></span>
          <?= htmlspecialchars($display_name) ?>
        </div>

        <a class="chip" href="logout.php">Logout</a>
      </div>
    </header>

    <section class="shell compactShell">
      <section class="panel">
        <div class="panelHead">
          <div>
            <div class="panelTitle">Upload your research</div>
            <div class="panelSub">Choose a format (or upload a rubric) to evaluate.</div>
          </div>
          <span class="badge">V1</span>
        </div>

        <div id="dropzone" class="dropzone" tabindex="0" role="button" aria-label="Upload research document">
          <div class="dzIcon">⬆</div>
          <div class="dzBody">
            <div class="dzTitle">Drag & drop your file</div>
            <div class="dzHint">or <span class="link">browse</span> (PDF or DOCX)</div>
          </div>
          <input id="researchFile" type="file" accept=".pdf,.docx" hidden />
        </div>

        <div class="fileRow">
          <div class="fileMeta">
            <div class="fileName" id="researchName">No file selected</div>
            <div class="fileInfo" id="researchInfo">Upload a PDF or DOCX</div>
          </div>
          <button class="btn ghost" id="removeResearch" type="button" disabled>Remove</button>
        </div>

        <div class="hr"></div>

        <label class="label">Project title (optional)</label>
        <input class="input" id="projectTitle" type="text" placeholder="e.g., Social Media and Study Habits" />

        <div class="grid2">
          <div>
            <label class="label">Format</label>
            <select class="select" id="formatSelect">
              <option value="" selected>Select a format…</option>
              <option value="chapter_5">Chapter-Based (1–5)</option>
              <option value="chapter_4">Chapter-Based (1–4)</option>
              <option value="chapter_3">Chapter-Based (1–3)</option>
              <option value="imrad">IMRAD</option>
              <option value="custom_upload">Custom (Upload Rubric/Format)</option>
            </select>
            <div class="hint" id="formatHint">Choose a format or upload a rubric.</div>
          </div>

          <div>
            <label class="label">Rubric / Format file</label>
            <div class="filePick">
              <input id="rubricFile" type="file" accept=".pdf,.docx,.png,.jpg,.jpeg" />
            </div>
            <div class="hint" id="rubricHint">Optional unless required by your selection.</div>
          </div>
        </div>

        <div class="msgWrap">
          <div id="errorBox" class="msg error" hidden></div>
          <div id="okBox" class="msg ok" hidden></div>
        </div>

        <div class="progress" id="progress" hidden>
          <div class="progressTop">
            <div class="progressLabel" id="progressLabel">Preparing…</div>
            <div class="progressPct" id="progressPct">0%</div>
          </div>
          <div class="bar">
            <div class="fill" id="fill" style="width:0%"></div>
            <div class="shimmer" aria-hidden="true"></div>
          </div>
          <div class="progressSub" id="progressSub">Waiting…</div>
        </div>

        <div class="actions">
          <button class="btn primary" id="evaluateBtn" type="button" disabled>Evaluate</button>
          <button class="btn ghost" id="resetBtn" type="button">Reset</button>
        </div>

        <div class="micro">
          <span class="dot"></span>
          Evaluate unlocks after you upload a file and provide a format (selected or rubric uploaded).
        </div>
      </section>
    </section>
    <a class="chip chipRowBtn" href="client_dashboard.php">Dashboard</a>

    <footer class="footer">
      <span>© <?= date("Y") ?> Resynex</span>
      <span class="sep">•</span>
      <span class="muted">V1</span>
    </footer>
  </main>

  
    <script>
   window.RESYNEX_UPLOAD_URL = <?= json_encode($API_UPLOAD_URL) ?>;
  window.RESYNEX_USER_ID = <?= json_encode($user["id"]) ?>;
</script>

  <script src="assets/js/app.js?v=999S"></script>
</body>
</html>
