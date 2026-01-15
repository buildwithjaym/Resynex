<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/db.php";

require_login();
header("Content-Type: application/json; charset=utf-8");

$user = current_user();
if (!$user || !isset($user["id"])) {
  http_response_code(401);
  echo json_encode(array("ok" => false, "error" => "unauthorized"));
  exit;
}

$pdo = db();
$uid = (int)$user["id"];

// IMPORTANT: We assume DB timestamps are UTC.
// If your DB is Manila time, change UTC to Asia/Manila below.
$DB_TZ = new DateTimeZone("UTC");

function get_str($k) {
  if (!isset($_GET[$k])) return "";
  return trim((string)$_GET[$k]);
}

function get_int($k, $default) {
  if (!isset($_GET[$k])) return (int)$default;
  $v = (int)$_GET[$k];
  if ($v <= 0) return (int)$default;
  return $v;
}

function score_class($score) {
  $s = (int)$score;
  if ($s >= 75) return "good";
  if ($s < 55) return "critical";
  return "work";
}

function get_existing_column($pdo, $table, $candidates) {
  $st = $pdo->query("SELECT DATABASE()");
  $db = (string)$st->fetchColumn();

  $sql = "SELECT COLUMN_NAME
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $st2 = $pdo->prepare($sql);

  foreach ($candidates as $col) {
    $st2->execute(array($db, $table, $col));
    $found = $st2->fetchColumn();
    if ($found) return (string)$found;
  }
  return "";
}

/* inputs */
$q    = get_str("q");
$sort = get_str("sort");
$flt  = get_str("flt");

$page  = get_int("page", 1);
$limit = get_int("limit", 18);
if ($limit > 60) $limit = 60;

$offset = ($page - 1) * $limit;

/* detect filename col safely */
$docFileCol = get_existing_column($pdo, "documents", array(
  "stored_filename",
  "filename",
  "original_filename",
  "file_name",
  "name",
  "path",
  "file_path"
));
if ($docFileCol === "") {
  echo json_encode(array("ok" => false, "error" => "documents_filename_column_missing"));
  exit;
}

$rubricLabelCol = get_existing_column($pdo, "rubrics", array("title", "name", "rubric_name"));

$docFileExpr = "d.`" . str_replace("`", "", $docFileCol) . "`";
$rubricExpr  = $rubricLabelCol !== ""
  ? "r.`" . str_replace("`", "", $rubricLabelCol) . "`"
  : "NULL";

/* sort */
$orderBy = "e.created_at DESC";
if ($sort === "newest") $orderBy = "e.created_at DESC";
else if ($sort === "oldest") $orderBy = "e.created_at ASC";
else if ($sort === "score_high") $orderBy = "e.score DESC, e.created_at DESC";
else if ($sort === "score_low") $orderBy = "e.score ASC, e.created_at DESC";
else if ($sort === "filename_az") $orderBy = "{$docFileExpr} ASC, e.created_at DESC";
else if ($sort === "filename_za") $orderBy = "{$docFileExpr} DESC, e.created_at DESC";
else if ($sort === "decision") $orderBy = "e.decision ASC, e.created_at DESC";

/* base query (user ownership enforced) */
$sql = "
  SELECT
    e.id,
    e.job_id,
    e.project_id,
    e.document_id,
    e.rubric_id,
    e.score,
    e.level,
    e.decision,
    e.created_at,
    p.title AS project_title,
    {$docFileExpr} AS filename,
    COALESCE({$rubricExpr}, CONCAT('Rubric #', e.rubric_id)) AS rubric_label
  FROM evaluations e
  INNER JOIN projects p ON p.id = e.project_id
  INNER JOIN documents d ON d.id = e.document_id
  LEFT JOIN rubrics r ON r.id = e.rubric_id
  WHERE p.user_id = ?
";

$params = array($uid);

/* filter by status bucket */
if ($flt === "good" || $flt === "work" || $flt === "critical") {
  if ($flt === "good") $sql .= " AND e.score >= 75 ";
  else if ($flt === "critical") $sql .= " AND e.score < 55 ";
  else $sql .= " AND e.score >= 55 AND e.score < 75 ";
}

/* search */
if ($q !== "") {
  $sql .= " AND (
      {$docFileExpr} LIKE ?
      OR p.title LIKE ?
      OR e.decision LIKE ?
      OR e.level LIKE ?
      OR COALESCE({$rubricExpr}, '') LIKE ?
    )";
  $like = "%" . $q . "%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

/* count */
$countSql = "SELECT COUNT(*)
  FROM evaluations e
  INNER JOIN projects p ON p.id = e.project_id
  INNER JOIN documents d ON d.id = e.document_id
  LEFT JOIN rubrics r ON r.id = e.rubric_id
  WHERE p.user_id = ?
";
$countParams = array($uid);

if ($flt === "good" || $flt === "work" || $flt === "critical") {
  if ($flt === "good") $countSql .= " AND e.score >= 75 ";
  else if ($flt === "critical") $countSql .= " AND e.score < 55 ";
  else $countSql .= " AND e.score >= 55 AND e.score < 75 ";
}

if ($q !== "") {
  $countSql .= " AND (
      {$docFileExpr} LIKE ?
      OR p.title LIKE ?
      OR e.decision LIKE ?
      OR e.level LIKE ?
      OR COALESCE({$rubricExpr}, '') LIKE ?
    )";
  $like = "%" . $q . "%";
  $countParams[] = $like;
  $countParams[] = $like;
  $countParams[] = $like;
  $countParams[] = $like;
  $countParams[] = $like;
}

$stc = $pdo->prepare($countSql);
$stc->execute($countParams);
$total = (int)$stc->fetchColumn();
$totalPages = (int)ceil($total / $limit);

/* data page */
$sql .= " ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

/* normalize for JSON */
$out = array();

for ($i = 0; $i < count($rows); $i++) {
  $r = $rows[$i];

  $created = isset($r["created_at"]) ? (string)$r["created_at"] : "";
  $created_epoch_utc = 0;

  if ($created !== "") {
    // MySQL DATETIME commonly returns "Y-m-d H:i:s"
    $dt = DateTimeImmutable::createFromFormat("Y-m-d H:i:s", $created, $DB_TZ);
    if (!$dt) {
      // fallback parse, still forcing DB timezone
      $dt = new DateTimeImmutable($created, $DB_TZ);
    }
    $created_epoch_utc = $dt->getTimestamp(); // epoch in UTC basis
  }

  $score  = isset($r["score"]) ? (int)$r["score"] : 0;
  $bucket = score_class($score);

  $out[] = array(
    "id" => (int)$r["id"],
    "job_id" => isset($r["job_id"]) ? (int)$r["job_id"] : 0,
    "score" => $score,
    "bucket" => $bucket,
    "decision" => isset($r["decision"]) ? (string)$r["decision"] : "",
    "level" => isset($r["level"]) ? (string)$r["level"] : "",
    "filename" => isset($r["filename"]) ? (string)$r["filename"] : "Untitled",
    "project_title" => isset($r["project_title"]) ? (string)$r["project_title"] : "Untitled Project",
    "rubric_label" => isset($r["rubric_label"]) ? (string)$r["rubric_label"] : "Format",

    // Raw DB string (debuggable)
    "created_at" => $created,

    // The only thing the client needs for correct timezone display
    "created_epoch_utc" => $created_epoch_utc
  );
}

echo json_encode(array(
  "ok" => true,
  "items" => $out,
  "total" => $total,
  "page" => $page,
  "total_pages" => $totalPages,
  "limit" => $limit,
  "q" => $q,
  "sort" => ($sort !== "" ? $sort : "newest"),
  "flt" => $flt
));
