<?php
require_once __DIR__ . "/auth.php";
require_login();
$user = current_user();

$API_BASE = "http://127.0.0.1:8000";

$job_id = isset($_GET["job_id"]) ? trim((string)$_GET["job_id"]) : "";
$job_id = preg_replace("/\D+/", "", $job_id);

$user_id = isset($user["id"]) ? (int)$user["id"] : 0;

if ($user_id <= 0) {
  http_response_code(401);
  echo "Invalid session user";
  exit;
}

if ($job_id === "") {
  http_response_code(400);
  echo "Missing job_id";
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Resynex • Status</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.css" />
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
          <div class="brandSub">Evaluation status</div>
        </div>
      </div>

      <div class="chips">
        <span class="chip strong">Job #<?= htmlspecialchars($job_id) ?></span>
      </div>
    </header>

    <section class="shell compactShell">
      <div class="compactGrid single">
        <section class="panel">
          <div class="panelHead">
            <div>
              <div class="panelTitle">We are evaluating your research</div>
              <div class="panelSub">Do not refresh. We will redirect you when the report is ready.</div>
            </div>
            <span class="badge" id="statusBadge">Queued</span>
          </div>

          <div class="statusCard">
            <div class="statusLine">
              <div class="statusDot" id="pulseDot"></div>
              <div>
                <div class="statusNow" id="statusNow">Queued</div>
                <div class="statusSmall" id="statusSmall">Preparing your job…</div>
              </div>
            </div>

            <div class="stepWrap" aria-label="Evaluation steps">
              <div class="stepRow" id="stepQueued">
                <div class="stepIcon"></div>
                <div class="stepText">
                  <div class="stepTitle">Queued</div>
                  <div class="stepSub">Your file is in line</div>
                </div>
              </div>

              <div class="stepRow" id="stepParsing">
                <div class="stepIcon"></div>
                <div class="stepText">
                  <div class="stepTitle">Parsing</div>
                  <div class="stepSub">Reading chapters and extracting text</div>
                </div>
              </div>

              <div class="stepRow" id="stepEvaluating">
                <div class="stepIcon"></div>
                <div class="stepText">
                  <div class="stepTitle">Evaluating</div>
                  <div class="stepSub">Scoring structure and alignment</div>
                </div>
              </div>

              <div class="stepRow" id="stepReady">
                <div class="stepIcon"></div>
                <div class="stepText">
                  <div class="stepTitle">Report Ready</div>
                  <div class="stepSub">Redirecting you now</div>
                </div>
              </div>
            </div>

            <div class="progress" id="progress">
              <div class="progressTop">
                <div class="progressLabel" id="progressLabel">Working…</div>
                <div class="progressPct" id="progressPct">10%</div>
              </div>
              <div class="bar">
                <div class="fill" id="fill" style="width:10%"></div>
                <div class="shimmer on" aria-hidden="true"></div>
              </div>
              <div class="progressSub" id="progressSub">This usually takes under a minute.</div>
            </div>

            <div class="msgWrap">
              <div id="errorBox" class="msg error" hidden></div>
            </div>

            <div class="actions">
              <a class="btn ghost" href="index.php">Upload another</a>
              <button class="btn primary" id="openReportBtn" type="button" disabled>Open report</button>
            </div>

            <div class="micro">
              <span class="dot"></span>
              If this takes too long, your PDF may be hard to read. DOCX is best for V1.
            </div>
          </div>
        </section>
      </div>
    </section>

    <footer class="footer">
      <span>© <?= date("Y") ?> Resynex</span>
      <span class="sep">•</span>
      <span class="muted">V1</span>
    </footer>
  </main>

  <script>
    window.RESYNEX_API_BASE = <?= json_encode($API_BASE) ?>;
    window.RESYNEX_JOB_ID = <?= json_encode((string)$job_id) ?>;
    window.RESYNEX_USER_ID = <?= json_encode((string)$user_id) ?>;
  </script>
  <script src="assets/js/status.js?v=<?= time() ?>"></script>
</body>
</html>
