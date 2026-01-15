<?php
require_once __DIR__ . "/auth.php";
require_login();

$user = current_user();
$API_BASE = "http://127.0.0.1:8000";

$job_id = isset($_GET["job_id"]) ? trim((string)$_GET["job_id"]) : "";
$job_id = preg_replace("/\D+/", "", $job_id);

$user_id = isset($user["id"]) ? (int)$user["id"] : 0;

if ($user_id <= 0) { http_response_code(401); echo "Invalid session user"; exit; }
if ($job_id === "") { http_response_code(400); echo "Missing job_id"; exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Resynex • Report</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.css?v=12">
  <link rel="stylesheet" href="assets/css/report.v3.css?v=12">
</head>

<body>
  <div class="bg"></div>
  <div class="grain"></div>

  <main class="wrap">
    <header class="topbar">
      <div class="brand">
        <div class="mark" aria-hidden="true"></div>
        <div class="brandText">
          <div class="brandName">Resynex</div>
          <div class="brandSub">Evaluation report</div>
        </div>
      </div>

      <div class="chips">
        <span class="chip strong">Job #<?= htmlspecialchars($job_id ?: "—") ?></span>
        <a class="chip" href="client_dashboard.php">Dashboard</a>
        <a class="chip" href="index.php">New upload</a>
      </div>
    </header>

    <section class="shell">
      <section class="panel">

        <!-- SKELETON LOADING -->
        <div id="loading" class="skeletonWrap" aria-live="polite">
          <div class="skeletonHero">
            <div class="skCard skTall">
              <div class="skLine w40"></div>
              <div class="skLine w70"></div>
              <div class="skLine w90"></div>
              <div class="skLine w60"></div>
              <div class="skBlock"></div>
            </div>
            <div class="skCard skTall">
              <div class="skLine w55"></div>
              <div class="skLine w70"></div>
              <div class="skDonut"></div>
              <div class="skLine w60"></div>
            </div>
          </div>

          <div class="skeletonGrid">
            <div class="skCard">
              <div class="skLine w35"></div>
              <div class="skLine w80"></div>
              <div class="skLine w70"></div>
            </div>
            <div class="skCard">
              <div class="skLine w35"></div>
              <div class="skLine w85"></div>
              <div class="skLine w65"></div>
            </div>
          </div>
        </div>

        <div id="errorState" class="msg error" hidden></div>

        <!-- REPORT -->
        <div id="report" hidden>

          <!-- HERO -->
          <div class="rHeroGrid">

            <div class="rCard rResultCard rThemeWarn" id="resultCard">
              <div class="rResultTop">
                <div class="rDecisionWrap">
                  <div class="rDecision" id="decisionTitle">—</div>
                  <div class="rDecisionSub" id="decisionSub">—</div>
                </div>

                <div class="rPills">
                  <div class="rStatusPill" id="statusPill">
                    <span class="rStatusDot" aria-hidden="true"></span>
                    <span id="statusPillText">Result</span>
                  </div>

                  <!-- SMART BADGE (clickable) -->
                  <button class="nlpBadge nlpOk" id="nlpBadge" type="button" hidden>
                    <span class="nlpDot" aria-hidden="true"></span>
                    <span class="nlpText" id="nlpBadgeText">Smart • OK</span>
                  </button>
                </div>
              </div>

              <div class="rScoreRow">
                <div class="rScoreBig">
                  <span id="scoreBig" class="scoreNumber">0</span>
                  <span class="rScoreOver">/100</span>
                </div>

                <div class="rMetaStack">
                  <div class="rMetaLine">
                    <span class="rMetaLabel">Level</span>
                    <span class="rMetaValue" id="levelBadge">—</span>
                  </div>
                  <div class="rMetaLine">
                    <span class="rMetaLabel">Threshold</span>
                    <span class="rMetaValue" id="thresholdText">—</span>
                  </div>
                </div>
              </div>

              <div class="rCallout" id="primaryCallout">
                <div class="rCalloutTitle" id="calloutTitle">Next step</div>
                <div class="rCalloutBody" id="calloutBody">—</div>
              </div>

              <div class="rMiniNote" id="reportHint">
                Tip: Fix FAIL items first. They move the score the fastest.
              </div>
            </div>

            <div class="rCard rDonutCard" id="donutCard">
              <div class="rDonutHead">
                <div>
                  <div class="rCardTitle">Score overview</div>
                  <div class="rCardSub">A quick snapshot of readiness.</div>
                </div>
              </div>

              <div class="rDonutWrap">
                <div class="rDonut" aria-label="Score donut">
                  <svg viewBox="0 0 120 120" class="rDonutSvg" role="img">
                    <circle class="rDonutTrack" cx="60" cy="60" r="46"></circle>
                    <circle class="rDonutValue" id="donutValue" cx="60" cy="60" r="46"></circle>
                  </svg>
                  <div class="rDonutCenter">
                    <div class="rDonutPct" id="donutPct">0%</div>
                    <div class="rDonutLabel" id="donutLabel">—</div>
                  </div>
                </div>

                <div class="rLegend">
                  <div class="rLegendRow"><span class="rSwatch ok"></span> Good (≥ 75)</div>
                  <div class="rLegendRow"><span class="rSwatch warn"></span> Needs work (55–74)</div>
                  <div class="rLegendRow"><span class="rSwatch bad"></span> Critical (&lt; 55)</div>
                </div>
              </div>
            </div>

          </div>
          <!-- END HERO -->

          <div class="rGrid2">
            <div class="rCard">
              <div class="rCardTitle">Fix first</div>
              <div class="rCardSub">These issues raise your score the fastest.</div>
              <div class="blockers" id="blockers"></div>
            </div>

            <div class="rCard">
              <div class="rCardTitle">Next steps</div>
              <div class="rCardSub">Do these in order for a clean revision.</div>
              <div class="actionsList" id="nextActions"></div>
            </div>
          </div>

          <div class="rGrid2">
            <div class="rCard">
              <div class="rCardTitle">Strengths</div>
              <div class="rCardSub">What you did well—keep these in the revision.</div>
              <div class="actionsList" id="strengths"></div>
            </div>

            <div class="rCard">
              <div class="rCardTitle">Weak categories</div>
              <div class="rCardSub">Where most score loss is happening.</div>
              <div class="actionsList" id="weakCategories"></div>
            </div>
          </div>

          <div class="rCard rTableCard">
            <div class="wideHead">
              <div>
                <div class="miniTitle">Detailed checks</div>
                <div class="miniSub">Evidence-based notes for each requirement.</div>
              </div>
              <div class="legend">
                <span class="tag pass">PASS</span>
                <span class="tag weak">WEAK</span>
                <span class="tag fail">FAIL</span>
                <button class="chip" id="toggleChecksBtn" type="button">Hide details</button>
              </div>
            </div>

            <div id="checksWrap">
              <div class="tableWrap">
                <table class="checksTable">
                  <thead>
                    <tr>
                      <th>Check</th>
                      <th>Status</th>
                      <th>Why it matters</th>
                      <th>Evidence</th>
                    </tr>
                  </thead>
                  <tbody id="checksBody"></tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="reportFoot">
            <div class="micro">
              <span class="dot"></span>
              DOCX gives best accuracy in V1.
            </div>
            <div class="footBtns">
              <a class="btn ghost" href="index.php">Upload revision</a>
              <button class="btn primary" id="downloadBtn" type="button">Download (soon)</button>
            </div>
          </div>

        </div>
      </section>
    </section>

    <footer class="footer">
      <span>© <?= date("Y") ?> Resynex</span>
      <span class="sep">•</span>
      <span class="muted">V1</span>
    </footer>
  </main>

  <!-- SMART MODAL -->
  <div class="rxModal" id="nlpModal" hidden>
    <div class="rxModalBackdrop" id="nlpModalBackdrop"></div>
    <div class="rxModalDialog" role="dialog" aria-modal="true" aria-labelledby="nlpModalTitle">
      <div class="rxModalHead">
        <div>
          <div class="rxModalTitle" id="nlpModalTitle">Smart validation</div>
          <div class="rxModalSub" id="nlpModalSub">—</div>
        </div>
        <button class="rxModalClose" id="nlpModalClose" type="button" aria-label="Close">✕</button>
      </div>
      <div class="rxModalBody" id="nlpModalBody"></div>
      <div class="rxModalFoot">
        <button class="btn ghost" id="nlpModalOk" type="button">Back to report</button>
      </div>
    </div>
  </div>

  <script>
    window.RESYNEX_API_BASE = <?= json_encode($API_BASE) ?>;
    window.RESYNEX_JOB_ID = <?= json_encode((string)$job_id) ?>;
    window.RESYNEX_USER_ID = <?= json_encode((string)$user_id) ?>;
  </script>
  <script src="assets/js/report.js?v=<?= time() ?>"></script>
</body>
</html>
