<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();
$id = (int)($body['id'] ?? 0);
$is_active = isset($body['is_active']) ? (int)(!!$body['is_active']) : null;

if ($id < 1) fail('id tidak valid', 422);
if ($is_active === null) fail('is_active wajib', 422);

$pdo = db();
$st = $pdo->prepare("
  UPDATE schedules
  SET is_active = ?, updated_at = NOW()
  WHERE id = ? AND user_id = ?
");
$st->execute([$is_active, $id, $_SESSION['user_id']]);

ok(['id'=>$id,'is_active'=>$is_active], 'OK');
