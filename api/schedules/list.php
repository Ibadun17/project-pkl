<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);

$pdo = db();
$userId = $_SESSION['user_id'];

$st = $pdo->prepare("
  SELECT
    id, target_type, target_id, tuya_code,
    TIME_FORMAT(time_on, '%H:%i') AS time_on,
    TIME_FORMAT(time_off, '%H:%i') AS time_off,
    days_mask, is_active, created_at
  FROM schedules
  WHERE user_id = ?
  ORDER BY id DESC
");
$st->execute([$userId]);

ok($st->fetchAll(PDO::FETCH_ASSOC), 'OK');
