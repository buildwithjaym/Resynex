<?php
// onboard.php
require_once __DIR__ . "/auth.php";
require_login();

/*
  Onboarding is intended for NEW users after register.
  If you want to allow replay later, remove this guard.
*/
$pdo = db();
$st = $pdo->prepare("SELECT onboarded_at FROM users WHERE id = ?");
$st->execute(array((int)$_SESSION["user_id"]));
$row = $st->fetch();

$already = false;
if ($row && isset($row["onboarded_at"]) && $row["onboarded_at"] !== null && $row["onboarded_at"] !== "") {
  $already = true;
}
if ($already) {
  header("Location: index.php");
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $st2 = $pdo->prepare("UPDATE users SET onboarded_at = NOW() WHERE id = ?");
  $st2->execute(array((int)$_SESSION["user_id"]));
  header("Content-Type: application/json");
  echo json_encode(array("ok" => true));
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Resynex • Welcome</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.css?v=1">

  <style>
    .onWrap{
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 26px;
    }

    /* Glassmorphism */
    .onShell{
      width: min(1080px, 100%);
      border-radius: 26px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(15, 15, 25, .34);
      box-shadow: 0 30px 80px rgba(0,0,0,.45);
      overflow: hidden;
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      position: relative;
    }

    .onGlowA, .onGlowB{
      position:absolute;
      filter: blur(50px);
      opacity: .55;
      pointer-events:none;
    }
    .onGlowA{
      width: 420px; height: 420px;
      left: -120px; top: -120px;
      background: radial-gradient(circle, rgba(192,132,252,.85), rgba(124,58,237,0));
    }
    .onGlowB{
      width: 520px; height: 520px;
      right: -180px; bottom: -180px;
      background: radial-gradient(circle, rgba(34,197,94,.45), rgba(34,197,94,0));
    }

    .onTop{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 12px;
      padding: 16px 18px;
      border-bottom: 1px solid rgba(255,255,255,.08);
      position: relative;
      z-index: 1;
    }

    .onBrand{
      display:flex;
      align-items:center;
      gap: 10px;
    }
    .onMark{
      width: 14px; height: 14px;
      border-radius: 6px;
      background: rgba(192,132,252,.95);
      box-shadow: 0 0 0 10px rgba(124,58,237,.12);
    }
    .onTitle{
      font-weight: 900;
      letter-spacing: .2px;
      line-height: 1.1;
    }
    .onSub{
      margin-top: 3px;
      font-size: 12px;
      color: rgba(255,255,255,.72);
    }

    .onBody{ overflow:hidden; position: relative; z-index:1; }
    .track{ display:flex; transition: transform .38s ease; will-change: transform; }

    .slide{ width:100%; flex:0 0 100%; padding: 18px; }

    .grid{
      display:grid;
      grid-template-columns: 1.15fr .85fr;
      gap: 16px;
      align-items: stretch;
    }
    @media (max-width: 900px){
      .grid{ grid-template-columns: 1fr; }
    }

    .copy{
      border-radius: 22px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.18);
      box-shadow: 0 18px 55px rgba(0,0,0,.28);
      padding: 18px;
      display:grid;
      gap: 10px;
      align-content:start;
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }

    .kicker{
      display:inline-flex;
      align-items:center;
      gap: 8px;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: rgba(255,255,255,.78);
    }
    .kDot{
      width: 8px; height: 8px;
      border-radius: 999px;
      background: rgba(192,132,252,.95);
      box-shadow: 0 0 0 8px rgba(124,58,237,.12);
    }

    .copy h2{
      margin: 0;
      font-size: 26px;
      font-weight: 900;
      letter-spacing: -.2px;
      line-height: 1.15;
    }
    .copy p{
      margin: 0;
      font-size: 13px;
      color: rgba(255,255,255,.78);
      line-height: 1.6;
      max-width: 78ch;
    }

    .bullets{
      display:grid;
      gap: 10px;
      margin-top: 6px;
    }
    .b{
      display:flex;
      gap: 10px;
      align-items:flex-start;
      padding: 12px;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.03);
    }
    .bIcon{
      width: 10px; height: 10px;
      border-radius: 999px;
      margin-top: 5px;
      background: rgba(192,132,252,.95);
      box-shadow: 0 0 0 10px rgba(124,58,237,.10);
      flex: 0 0 auto;
    }
    .bText{
      font-size: 13px;
      color: rgba(255,255,255,.88);
      line-height: 1.45;
    }

    /* Right side: “value proof” */
    .side{
      border-radius: 22px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.16);
      box-shadow: 0 18px 55px rgba(0,0,0,.22);
      padding: 16px;
      display:grid;
      gap: 10px;
      align-content:start;
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }

    .proofTitle{
      font-weight: 900;
      letter-spacing: .2px;
      margin: 0;
    }
    .proofSub{
      margin: 0;
      font-size: 12px;
      color: rgba(255,255,255,.70);
      line-height: 1.5;
    }

    .statGrid{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 8px;
    }
    @media (max-width: 900px){
      .statGrid{ grid-template-columns: 1fr 1fr; }
    }
    .stat{
      padding: 12px;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.03);
    }
    .statBig{
      font-size: 18px;
      font-weight: 900;
      letter-spacing: -.2px;
    }
    .statSmall{
      margin-top: 4px;
      font-size: 12px;
      color: rgba(255,255,255,.72);
      line-height: 1.35;
    }

    .mock{
      margin-top: 10px;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.10);
      background: linear-gradient(135deg, rgba(192,132,252,.14), rgba(34,197,94,.08));
      padding: 12px;
    }
    .mockLine{
      display:flex;
      justify-content:space-between;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(0,0,0,.14);
      margin-top: 10px;
      font-size: 12px;
      color: rgba(255,255,255,.78);
    }
    .tagPill{
      font-size: 11px;
      font-weight: 900;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(0,0,0,.18);
      white-space: nowrap;
    }
    .tagOk{ border-color: rgba(34,197,94,.30); background: rgba(34,197,94,.10); }
    .tagWarn{ border-color: rgba(234,179,8,.30); background: rgba(234,179,8,.10); }
    .tagBad{ border-color: rgba(239,68,68,.30); background: rgba(239,68,68,.10); }

    .onFoot{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap: 12px;
      flex-wrap:wrap;
      padding: 16px 18px;
      border-top: 1px solid rgba(255,255,255,.08);
      position: relative;
      z-index: 1;
    }

    .leftFoot{ display:flex; align-items:center; gap: 12px; }
    .dots{ display:flex; gap: 8px; }
    .dot{ width: 8px; height: 8px; border-radius: 999px; background: rgba(255,255,255,.22); }
    .dot.active{ background: rgba(192,132,252,.95); box-shadow: 0 0 0 6px rgba(124,58,237,.12); }

    .bar{
      width: 170px; height: 8px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(0,0,0,.18);
      overflow:hidden;
    }
    .fill{ height: 100%; width: 0%; background: rgba(192,132,252,.95); }

    .btns{ display:flex; gap: 10px; flex-wrap:wrap; }
    .tiny{
      font-size: 12px;
      color: rgba(255,255,255,.70);
      display:flex;
      align-items:center;
      gap: 8px;
    }
    .tinyDot{
      width: 8px; height: 8px; border-radius: 999px;
      background: rgba(255,255,255,.35);
      box-shadow: 0 0 0 7px rgba(255,255,255,.06);
    }
  </style>
</head>

<body>
  <div class="bg"></div>
  <div class="grain"></div>

  <div class="onWrap">
    <div class="onShell">
      <div class="onGlowA"></div>
      <div class="onGlowB"></div>

      <div class="onTop">
        <div class="onBrand">
          <div class="onMark" aria-hidden="true"></div>
          <div>
            <div class="onTitle">Resynex</div>
            <div class="onSub">We turn “revise” into a clear checklist.</div>
          </div>
        </div>
        <span class="chip">Tour</span>
      </div>

      <div class="onBody">
        <div class="track" id="track">

          <div class="slide">
            <div class="grid">
              <div class="copy">
                <div class="kicker"><span class="kDot"></span> Your problem, solved</div>
                <h2>Stop guessing what your adviser will flag.</h2>
                <p>
                  Resynex checks your research like a strict reviewer—then tells you exactly what to fix, why it matters,
                  and what evidence is missing. You get clarity in minutes, not after another stressful round of feedback.
                </p>

                <div class="bullets">
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Clear decision:</b> Know if you’re ready to submit or need revision.</div></div>
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Fix-first plan:</b> The fastest path to raise your score.</div></div>
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Evidence-based checks:</b> No vague advice—just what your document shows.</div></div>
                </div>
              </div>

              <div class="side">
                <p class="proofTitle">What you get</p>
                <p class="proofSub">A report designed to guide revision, not overwhelm you.</p>

                <div class="statGrid">
                  <div class="stat">
                    <div class="statBig">0–100</div>
                    <div class="statSmall">Score you can track after every revision</div>
                  </div>
                  <div class="stat">
                    <div class="statBig">Clear/Revise</div>
                    <div class="statSmall">Decision aligned to your threshold</div>
                  </div>
                  <div class="stat">
                    <div class="statBig">Fix-first</div>
                    <div class="statSmall">Top blockers with evidence notes</div>
                  </div>
                  <div class="stat">
                    <div class="statBig">Steps</div>
                    <div class="statSmall">Action list you can follow today</div>
                  </div>
                </div>

                <div class="mock">
                  <div class="proofSub">Example snapshot</div>
                  <div class="mockLine">
                    <span>Methodology clarity</span>
                    <span class="tagPill tagBad">FAIL</span>
                  </div>
                  <div class="mockLine">
                    <span>Literature support</span>
                    <span class="tagPill tagWarn">WEAK</span>
                  </div>
                  <div class="mockLine">
                    <span>Abstract completeness</span>
                    <span class="tagPill tagOk">PASS</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="slide">
            <div class="grid">
              <div class="copy">
                <div class="kicker"><span class="kDot"></span> Faster revisions</div>
                <h2>We tell you what to fix first—so you finish sooner.</h2>
                <p>
                  Most students revise in the wrong order: they polish formatting while major requirements are still missing.
                  Resynex highlights the highest-impact issues first so your next upload improves immediately.
                </p>

                <div class="bullets">
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Fix FAIL items first</b> to move your score the fastest.</div></div>
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Next steps list</b> so you always know what to do next.</div></div>
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Strengths + weak categories</b> to keep what works and target what doesn’t.</div></div>
                </div>
              </div>

              <div class="side">
                <p class="proofTitle">The workflow</p>
                <p class="proofSub">Simple. Repeatable. Built for real school deadlines.</p>

                <div class="mock">
                  <div class="mockLine"><span>1) Upload DOCX/PDF</span><span class="tagPill">Start</span></div>
                  <div class="mockLine"><span>2) Pick a format/rubric</span><span class="tagPill">Setup</span></div>
                  <div class="mockLine"><span>3) Get report + blockers</span><span class="tagPill tagWarn">Review</span></div>
                  <div class="mockLine"><span>4) Revise + re-upload</span><span class="tagPill tagOk">Improve</span></div>
                </div>

                <div class="statGrid">
                  <div class="stat">
                    <div class="statBig">DOCX</div>
                    <div class="statSmall">Best accuracy (recommended)</div>
                  </div>
                  <div class="stat">
                    <div class="statBig">PDF</div>
                    <div class="statSmall">Best-effort extraction</div>
                  </div>
                </div>

                <div class="tiny"><span class="tinyDot"></span> Tip: revise 1–2 FAIL items, then re-upload.</div>
              </div>
            </div>
          </div>

          <div class="slide">
            <div class="grid">
              <div class="copy">
                <div class="kicker"><span class="kDot"></span> Ready to ship</div>
                <h2>Submit with confidence—no more “I hope this is enough.”</h2>
                <p>
                  Resynex is your problem-solver when research expectations feel unclear. You’ll know what’s missing,
                  what’s strong, and what to do next—so your final version is clean, complete, and defendable.
                </p>

                <div class="bullets">
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Clear threshold:</b> You’ll see the score needed to pass.</div></div>
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Evidence notes:</b> Each check explains what the evaluator found.</div></div>
                  <div class="b"><div class="bIcon"></div><div class="bText"><b>Repeatable loop:</b> Upload → evaluate → revise → submit.</div></div>
                </div>
              </div>

              <div class="side">
                <p class="proofTitle">Before you start</p>
                <p class="proofSub">Pick the right format and you’ll get the cleanest report.</p>

                <div class="mock">
                  <div class="mockLine"><span>IMRAD</span><span class="tagPill tagOk">Recommended</span></div>
                  <div class="mockLine"><span>Chapter-Based</span><span class="tagPill tagWarn">Works</span></div>
                  <div class="mockLine"><span>Custom rubric upload</span><span class="tagPill">Flexible</span></div>
                </div>

                <div class="tiny"><span class="tinyDot"></span> You can upload a revision anytime.</div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="onFoot">
        <div class="leftFoot">
          <div class="dots" id="dots">
            <span class="dot active"></span>
            <span class="dot"></span>
            <span class="dot"></span>
          </div>
          <div class="bar"><div class="fill" id="fill"></div></div>
        </div>

        <div class="btns">
          <button class="btn ghost" id="backBtn" type="button" disabled>Back</button>
          <button class="btn ghost" id="skipBtn" type="button">Skip</button>
          <button class="btn primary" id="nextBtn" type="button">Next</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      var track = document.getElementById("track");
      var dotsWrap = document.getElementById("dots");
      var dots = dotsWrap ? dotsWrap.querySelectorAll(".dot") : [];
      var fill = document.getElementById("fill");

      var backBtn = document.getElementById("backBtn");
      var nextBtn = document.getElementById("nextBtn");
      var skipBtn = document.getElementById("skipBtn");

      var total = dots.length || 3;
      var idx = 0;

      function setIndex(n){
        idx = Math.max(0, Math.min(total - 1, n));
        track.style.transform = "translateX(" + (-idx * 100) + "%)";

        for (var i=0;i<dots.length;i++){
          if (i === idx) dots[i].classList.add("active");
          else dots[i].classList.remove("active");
        }

        backBtn.disabled = (idx === 0);
        nextBtn.textContent = (idx === total - 1) ? "Start evaluating" : "Next";

        var pct = ((idx + 1) / total) * 100;
        if (fill) fill.style.width = pct + "%";
      }

      function finish(){
        fetch("onboard.php", { method: "POST" })
          .then(function(){ window.location.href = "index.php"; })
          .catch(function(){ window.location.href = "index.php"; });
      }

      backBtn.addEventListener("click", function(){ setIndex(idx - 1); });
      nextBtn.addEventListener("click", function(){ if (idx === total - 1) finish(); else setIndex(idx + 1); });
      skipBtn.addEventListener("click", finish);

      var down=false, startX=0, dx=0;
      function onDown(x){ down=true; startX=x; dx=0; }
      function onMove(x){ if(!down) return; dx = x - startX; }
      function onUp(){
        if(!down) return;
        down=false;
        if (Math.abs(dx) > 45) setIndex(idx + (dx < 0 ? 1 : -1));
        dx=0;
      }

      track.addEventListener("touchstart", function(e){ onDown(e.touches[0].clientX); }, { passive:true });
      track.addEventListener("touchmove", function(e){ onMove(e.touches[0].clientX); }, { passive:true });
      track.addEventListener("touchend", onUp);

      track.addEventListener("mousedown", function(e){ onDown(e.clientX); });
      window.addEventListener("mousemove", function(e){ onMove(e.clientX); });
      window.addEventListener("mouseup", onUp);

      setIndex(0);
    })();
  </script>
</body>
</html>
