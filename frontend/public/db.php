<?php
function db() {
  static $pdo = null;
  if ($pdo !== null) return $pdo;

  date_default_timezone_set("Asia/Manila");

  $host = "127.0.0.1";
  $db   = "resynex_v1";
  $user = "root";
  $pass = "";
  $charset = "utf8mb4";

  $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
  $opts = array(
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  );

  try {
    $pdo = new PDO($dsn, $user, $pass, $opts);
    $pdo->exec("SET time_zone = '+00:00'");
  } catch (Exception $e) {
    http_response_code(500);
    echo "Database connection failed.";
    exit;
  }

  return $pdo;
}
