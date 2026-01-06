<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();

$target_type = strtolower(trim($body['target_type'] ?? 'device'));
$target_id   = trim($body['target_id'] ?? '');
$time_on     = trim($body['time_on'] ?? '');
$time_off    = trim($body['time_off'] ?? '');
$tuya_code   = trim($body['tuya_code'] ?? 'switch_1');
$days        = $body['days'] ?? null; // array [1..7]
$is_active   = isset($body['is_active']) ? (int)(!!$body['is_active']) : 1;

if (!in_array($target_type, ['device','group'], true)) fail('target_type harus device/group', 422);
if ($target_id === '') fail('target_id wajib', 422);
if ($time_on === '' || $time_off === '') fail('Jam ON dan Jam OFF wajib', 422);
if (!preg_match('/^\d{2}:\d{2}$/', $time_on)) fail('Format time_on harus HH:MM', 422);
if (!preg_match('/^\d{2}:\d{2}$/', $time_off)) fail('Format time_off harus HH:MM', 422);

if (!is_array($days) || count($days) < 1) fail('Minimal pilih 1 hari', 422);
$days = array_values(array_unique(array_map('intval', $days)));
foreach ($days as $d) {
  if ($d < 1 || $d > 7) fail('Hari tidak valid', 422);
}
$days_mask = implode(',', $days);

$pdo = db();

// validasi target ada
if ($target_type === 'device') {
  $st = $pdo->prepare("SELECT id FROM devices WHERE device_id = ? LIMIT 1");
  $st->execute([$target_id]);
  if (!$st->fetch()) fail('Device ID belum ada. Tambahkan dulu lewat Tambah Device.', 422);
} else {
  $st = $pdo->prepare("SELECT id FROM device_groups WHERE id = ? LIMIT 1");
  $st->execute([$target_id]);
  if (!$st->fetch()) fail('Group tidak ditemukan.', 422);
}

$st = $pdo->prepare("
  INSERT INTO schedules (user_id, target_type, target_id, tuya_code, time_on, time_off, days_mask, is_active, created_at)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$st->execute([
  $_SESSION['user_id'],
  $target_type,
  $target_id,
  $tuya_code ?: 'switch_1',
  $time_on . ':00',
  $time_off . ':00',
  $days_mask,
  $is_active
]);

ok(['id' => (int)$pdo->lastInsertId()], 'Jadwal berhasil dibuat');
