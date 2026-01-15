<?php

require_once __DIR__ . "/db.php";

function auth_session_start() {
  if (session_status() === PHP_SESSION_NONE) {
    
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");

   

    ini_set("session.cookie_httponly", "1");

    session_start();
  }
}

function is_logged_in() {
  auth_session_start();
  return isset($_SESSION["user_id"]) && (int)$_SESSION["user_id"] > 0;
}

function login_user($user_id) {
  auth_session_start();
  session_regenerate_id(true);
  $_SESSION["user_id"] = (int)$user_id;
}

function logout_user() {
  auth_session_start();
  $_SESSION = array();

  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000,
      $params["path"],
      $params["domain"],
      $params["secure"],
      $params["httponly"]
    );
  }

  session_destroy();
}

function require_login() {
  auth_session_start();

 
  header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
  header("Pragma: no-cache");

  if (!is_logged_in()) {
    header("Location: login.php");
    exit;
  }
}

function current_user() {
  if (!is_logged_in()) return null;

  $pdo = db();
  $st = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
  $st->execute(array((int)$_SESSION["user_id"]));
  $u = $st->fetch(PDO::FETCH_ASSOC);
  if (!$u) return null;

  return array(
    "id" => (int)$u["id"],
    "name" => (string)$u["name"],
    "email" => (string)$u["email"]
  );
}
