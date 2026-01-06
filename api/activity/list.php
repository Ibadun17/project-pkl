<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);

$pdo = db();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$hasil = trim($_GET['hasil'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$limit = (int)($_GET['limit'] ?? 10);
if ($limit < 1) $limit = 10;
if ($limit > 100) $limit = 100;

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where = "WHERE al.user_id = ?";
$params = [$_SESSION['user_id']];

if ($q !== '') {
  $where .= " AND (al.detail LIKE ? OR al.device_id LIKE ? OR u.email LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

if ($status !== '') {
  $where .= " AND al.status = ?";
  $params[] = $status;
}

if ($hasil !== '') {
  $where .= " AND al.hasil = ?";
  $params[] = $hasil;
}

if ($from !== '' && $to !== '') {
  $where .= " AND DATE(al.created_at) BETWEEN ? AND ?";
  $params[] = $from;
  $params[] = $to;
} elseif ($from !== '') {
  $where .= " AND DATE(al.created_at) >= ?";
  $params[] = $from;
} elseif ($to !== '') {
  $where .= " AND DATE(al.created_at) <= ?";
  $params[] = $to;
}

// hitung total
$st = $pdo->prepare("
  SELECT COUNT(*)
  FROM activity_logs al
  LEFT JOIN users u ON u.id = al.user_id
  $where
");
$st->execute($params);
$total = (int)$st->fetchColumn();
$totalPages = (int)ceil($total / $limit);
if ($totalPages < 1) $totalPages = 1;

// ambil data
$st = $pdo->prepare("
  SELECT
    al.id,
    al.created_at,
    al.group_area AS area_zone,
    al.device_id,
    u.email AS user_email,
    al.status,
    al.hasil
  FROM activity_logs al
  LEFT JOIN users u ON u.id = al.user_id
  $where
  ORDER BY al.id DESC
  LIMIT $limit OFFSET $offset
");
$st->execute($params);

ok([
  'items' => $st->fetchAll(PDO::FETCH_ASSOC),
  'page' => $page,
  'limit' => $limit,
  'total' => $total,
  'total_pages' => $totalPages
], 'OK');
