<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

if (is_logged_in()) {
  header("Location: index.php");
  exit;
}

$mode = "login";
if (isset($_GET["mode"])) {
  $m = strtolower(trim((string)$_GET["mode"]));
  if ($m === "register") $mode = "register";
}

$flash = "";
if (isset($_GET["flash"])) {
  $f = (string)$_GET["flash"];
  if ($f === "registered") $flash = "Account created. Welcome to Resynex.";
  if ($f === "logout") $flash = "You’ve been logged out.";
  if ($f === "need_login") $flash = "Please login to continue.";
}

$err = "";

function postv($k) { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ""; }
function valid_email($email) { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = postv("action");
  $email = strtolower(postv("email"));
  $password = postv("password");
  $name = postv("name");
  $confirm = postv("confirm");

  if ($action === "register") {
    $mode = "register";

    if ($name === "" || strlen($name) < 2) {
      $err = "Please enter your name.";
    } elseif (!valid_email($email)) {
      $err = "Please enter a valid email.";
    } elseif ($password === "" || strlen($password) < 8) {
      $err = "Password must be at least 8 characters.";
    } elseif ($confirm === "" || $confirm !== $password) {
      $err = "Passwords do not match.";
    } else {
      $pdo = db();
      $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
      $st->execute(array($email));
      $exists = $st->fetch();

      if ($exists) {
        $err = "That email is already registered. Please login instead.";
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st2 = $pdo->prepare("INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
        $st2->execute(array($name, $email, $hash));
        $new_id = (int)$pdo->lastInsertId();

        login_user($new_id);

      
        header("Location: onboard.php?flash=registered");
        exit;
      }
    }
  } else {
    $mode = "login";

    if (!valid_email($email)) {
      $err = "Please enter a valid email.";
    } elseif ($password === "") {
      $err = "Please enter your password.";
    } else {
      $pdo = db();
      $st = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ? LIMIT 1");
      $st->execute(array($email));
      $u = $st->fetch();

      if (!$u || !isset($u["password_hash"]) || !password_verify($password, (string)$u["password_hash"])) {
        $err = "Incorrect email or password.";
      } else {
        login_user((int)$u["id"]);

        /* Login -> index with toast */
        header("Location: index.php?flash=login");
        exit;
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Resynex • <?php echo $mode === "register" ? "Create account" : "Login"; ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800;900&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.css?v=1" />

  <style>
    :root{
      --glass: rgba(15, 15, 25, .34);
      --line: rgba(255,255,255,.14);
      --muted: rgba(255,255,255,.72);
      --muted2: rgba(255,255,255,.56);

      --violetA: #7C3AED;
      --violetB: #C084FC;

      --shadow: 0 30px 90px rgba(0,0,0,.55);
    }

    body { min-height: 100vh; }

    .authWrap{
      min-height: 100vh;
      display:grid;
      place-items:center;
      padding: 28px;
    }

    .authCard{
      width: min(520px, 100%);
      border-radius: 26px;
      border: 1px solid var(--line);
      background: var(--glass);
      box-shadow: var(--shadow);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      overflow: hidden;
      position: relative;

      transform: translateY(0);
      transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .authCard:hover{
      transform: translateY(-4px);
      border-color: rgba(192,132,252,.32);
      box-shadow: 0 36px 110px rgba(0,0,0,.60);
    }

    .authCard:hover::after{
      content:"";
      position:absolute;
      inset:0;
      border-radius: inherit;
      background: radial-gradient(180px 180px at 30% 18%, rgba(192,132,252,.08), transparent 60%);
      pointer-events:none;
    }

    .glowA,.glowB{
      position:absolute;
      filter: blur(55px);
      opacity: .60;
      pointer-events:none;
    }
    .glowA{
      width: 420px; height: 420px;
      left: -160px; top: -180px;
      background: radial-gradient(circle, rgba(192,132,252,.90), rgba(124,58,237,0));
    }
    .glowB{
      width: 520px; height: 520px;
      right: -220px; bottom: -240px;
      background: radial-gradient(circle, rgba(124,58,237,.55), rgba(124,58,237,0));
    }

    .authInner{ position:relative; z-index:1; padding: 22px; }

    .brand{
      display:flex;
      align-items:center;
      justify-content:center;
      gap: 12px;
      margin-top: 6px;
    }
    .mark{
      width: 14px; height: 14px;
      border-radius: 6px;
      background: rgba(192,132,252,.95);
      box-shadow: 0 0 0 10px rgba(124,58,237,.12);
    }

    .title{
      text-align:center;
      margin: 14px 0 0;
      font-size: 26px;
      font-weight: 900;
      letter-spacing: .2px;
    }
    .sub{
      text-align:center;
      margin: 8px 0 0;
      font-size: 13px;
      color: var(--muted);
      line-height: 1.5;
    }

    .form{ margin-top: 18px; display:grid; gap: 12px; }

    .field label{
      display:block;
      font-size: 12px;
      font-weight: 800;
      color: rgba(255,255,255,.82);
      margin: 0 0 8px;
    }

    .inp{
      width:100%;
      height: 46px;
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,.16);
      background: rgba(255,255,255,.08);
      color: rgba(255,255,255,.92);
      padding: 0 14px;
      outline: none;
      transition: border-color .15s ease, background-color .15s ease;
    }
    .inp::placeholder{ color: rgba(255,255,255,.45); }
    .inp:focus{
      border-color: rgba(192,132,252,.60);
      background: rgba(255,255,255,.10);
    }

    .pwWrap{ position: relative; }
    .pwInp{ padding-right: 86px; }

    .pwBtn{
      position:absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      height: 30px;
      padding: 0 10px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(0,0,0,.18);
      color: rgba(255,255,255,.82);
      font-size: 12px;
      font-weight: 900;
      cursor: pointer;
      user-select: none;
    }
    .pwBtn:hover{
      border-color: rgba(192,132,252,.45);
      color: rgba(255,255,255,.92);
    }

    .btnAuth{
      margin-top: 6px;
      height: 46px;
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,.14);
      background: linear-gradient(135deg, var(--violetA), var(--violetB));
      color: white;
      font-weight: 900;
      letter-spacing: .2px;
      cursor:pointer;
      box-shadow: 0 16px 36px rgba(124,58,237,.28);
      transition: box-shadow .18s ease, filter .18s ease, transform .08s ease;
    }
    .btnAuth:hover{
      filter: brightness(1.04);
      box-shadow: 0 22px 48px rgba(124,58,237,.40);
    }
    .btnAuth:active{ transform: translateY(1px); }

    .msg{
      border-radius: 16px;
      padding: 10px 12px;
      font-size: 12px;
      line-height: 1.4;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.16);
      color: rgba(255,255,255,.85);
      margin-top: 14px;
    }
    .msg.err{
      border-color: rgba(239,68,68,.28);
      background: rgba(239,68,68,.10);
      color: rgba(255,255,255,.92);
    }

    .rowBottom{
      margin-top: 12px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap: 10px;
      flex-wrap:wrap;
      color: var(--muted2);
      font-size: 12px;
    }

    .aLink{
      color: rgba(255,255,255,.84);
      text-decoration:none;
      border-bottom: 1px solid rgba(255,255,255,.22);
      padding-bottom: 2px;
    }
    .aLink:hover{
      border-bottom-color: rgba(192,132,252,.60);
      color: rgba(255,255,255,.92);
    }

   /* Toast (forced dark glass, resistant to style.css overrides) */
#toast.toast{
  position: fixed;
  left: 50%;
  top: 18px;
  transform: translateX(-50%) translateY(-6px);
  z-index: 9999;
  width: min(560px, calc(100% - 24px));
  border-radius: 16px;

  border: 1px solid rgba(255,255,255,.14) !important;
  background: rgba(12, 12, 20, .55) !important;
  color: rgba(255,255,255,.90) !important;

  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 20px 60px rgba(0,0,0,.55);

  padding: 12px 14px;

  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 12px;

  opacity: 0;
  pointer-events: none;
  transition: opacity .18s ease, transform .18s ease;
}

#toast.toast.show{
  opacity: 1;
  transform: translateX(-50%) translateY(0);
  pointer-events: auto;
}

#toast .toastLeft{
  display:flex;
  align-items:center;
  gap: 10px;
  color: inherit !important;
}

#toast #toastText{
  color: inherit !important;
  font-weight: 700;
}

#toast .tDot{
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: rgba(34,197,94,.95) !important;
  box-shadow: 0 0 0 8px rgba(34,197,94,.12);
  flex: 0 0 auto;
}

#toast .tClose{
  border: 1px solid rgba(255,255,255,.14) !important;
  background: rgba(0,0,0,.22) !important;
  color: rgba(255,255,255,.86) !important;
  height: 28px;
  padding: 0 10px;
  border-radius: 12px;
  cursor:pointer;
  font-weight: 900;
  font-size: 12px;
}

#toast .tClose:hover{
  border-color: rgba(192,132,252,.45) !important;
  color: rgba(255,255,255,.95) !important;
}

  </style>
</head>

<body>
  <div class="bg"></div>
  <div class="grain"></div>

  <div class="toast" id="toast" aria-live="polite">
    <div class="toastLeft">
      <span class="tDot" aria-hidden="true"></span>
      <span id="toastText">Success</span>
    </div>
    <button class="tClose" id="toastClose" type="button">Close</button>
  </div>

  <div class="authWrap">
    <div class="authCard">
      <div class="glowA"></div>
      <div class="glowB"></div>

      <div class="authInner">
        <div class="brand">
          <div class="mark" aria-hidden="true"></div>
          <div style="font-weight:900;">Resynex</div>
        </div>

        <div class="title">
          <?php echo $mode === "register" ? "Create your account" : "Welcome back"; ?>
        </div>

        <div class="sub">
          <?php
            if ($mode === "register") {
              echo "We solve the “what do I fix?” problem—clear blockers, real evidence, faster approvals.";
            } else {
              echo "Upload. Evaluate. Fix blockers. Submit with confidence.";
            }
          ?>
        </div>

        <?php if ($err !== ""): ?>
          <div class="msg err"><?php echo htmlspecialchars($err); ?></div>
        <?php endif; ?>

        <form class="form" method="post" action="login.php<?php echo $mode === "register" ? "?mode=register" : ""; ?>">
          <input type="hidden" name="action" value="<?php echo $mode === "register" ? "register" : "login"; ?>">

          <?php if ($mode === "register"): ?>
            <div class="field">
              <label for="name">Name</label>
              <input class="inp" id="name" name="name" type="text" placeholder="Enter your name" autocomplete="name" required>
            </div>
          <?php endif; ?>

          <div class="field">
            <label for="email">Email</label>
            <input class="inp" id="email" name="email" type="email" placeholder="Enter your email" autocomplete="email" required>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="pwWrap">
              <input class="inp pwInp" id="password" name="password" type="password" placeholder="Enter your password" autocomplete="<?php echo $mode === "register" ? "new-password" : "current-password"; ?>" required>
              <button class="pwBtn" id="pwToggle" type="button">Show</button>
            </div>
          </div>

          <?php if ($mode === "register"): ?>
            <div class="field">
              <label for="confirm">Confirm password</label>
              <div class="pwWrap">
                <input class="inp pwInp" id="confirm" name="confirm" type="password" placeholder="Confirm your password" autocomplete="new-password" required>
                <button class="pwBtn" id="pwToggle2" type="button">Show</button>
              </div>
            </div>
          <?php endif; ?>

          <button class="btnAuth" type="submit">
            <?php echo $mode === "register" ? "Create account" : "Login"; ?>
          </button>

          <div class="rowBottom">
            <div>
              <?php echo $mode === "register" ? "Already have an account?" : "No account yet?"; ?>
              <a class="aLink" href="login.php?mode=<?php echo $mode === "register" ? "login" : "register"; ?>">
                <?php echo $mode === "register" ? "Login" : "Create one"; ?>
              </a>
            </div>
            <div>Secure session</div>
          </div>
        </form>

      </div>
    </div>
  </div>

  <script>
    (function(){
      function bindToggle(btnId, inputId){
        var btn = document.getElementById(btnId);
        var inp = document.getElementById(inputId);
        if (!btn || !inp) return;

        btn.addEventListener("click", function(){
          var isPw = inp.type === "password";
          inp.type = isPw ? "text" : "password";
          btn.textContent = isPw ? "Hide" : "Show";
        });
      }

      bindToggle("pwToggle", "password");
      bindToggle("pwToggle2", "confirm");

      var flash = <?php echo json_encode($flash); ?>;
      var toast = document.getElementById("toast");
      var toastText = document.getElementById("toastText");
      var closeBtn = document.getElementById("toastClose");
      var timer = null;

      function showToast(msg){
        if (!toast || !toastText) return;
        toastText.textContent = msg;
        toast.classList.add("show");

        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(function(){
          toast.classList.remove("show");
        }, 2600);
      }

      if (closeBtn) {
        closeBtn.addEventListener("click", function(){
          if (timer) window.clearTimeout(timer);
          if (toast) toast.classList.remove("show");
        });
      }

      if (flash && flash !== "") showToast(flash);
    })();
  </script>
</body>
</html>
