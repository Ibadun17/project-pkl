<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);

$q = trim($_GET['q'] ?? '');

$pdo = db();

$sql = "
  SELECT id, device_id, nama_perangkat, lokasi, switch_code, fungsi, created_at
  FROM devices
  WHERE created_by = ?
";
$params = [$_SESSION['user_id']];

if ($q !== '') {
  $sql .= " AND (nama_perangkat LIKE ? OR lokasi LIKE ? OR device_id LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);

ok($st->fetchAll(PDO::FETCH_ASSOC), 'OK');
