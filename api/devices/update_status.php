<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();
$missing = require_fields($body, ['device_id', 'status']);
if ($missing) fail('Field wajib belum diisi', 422, ['missing' => $missing]);

$device_id = trim($body['device_id']);
$status = strtoupper(trim($body['status']));
if (!in_array($status, ['ON', 'OFF'], true)) fail('status harus ON/OFF', 422);

$group_id = isset($body['group_id']) ? (int)$body['group_id'] : null;
$message = null;
$hasil = 'SUKSES';

$pdo = db();

try{
  $pdo->beginTransaction();

  // cek device ada
  $st = $pdo->prepare("SELECT id FROM devices WHERE device_id = ?");
  $st->execute([$device_id]);
  $device = $st->fetch();
  if(!$device){
    $pdo->rollBack();
    fail('Device tidak ditemukan', 404);
  }

  // update status_terakhir + updated_at
  $st = $pdo->prepare("
    UPDATE devices
    SET status_terakhir = ?, updated_at = NOW()
    WHERE device_id = ?
  ");
  $st->execute([$status, $device_id]);

  // tulis log
  $st = $pdo->prepare("
    INSERT INTO activity_logs (user_id, group_id, device_id, status, hasil, message)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $st->execute([$_SESSION['user_id'], $group_id, $device_id, $status, $hasil, $message]);

  $pdo->commit();

  ok(['device_id'=>$device_id, 'status'=>$status], 'Status berhasil diupdate');
}catch(Exception $e){
  if($pdo->inTransaction()) $pdo->rollBack();

  // kalau error, tetap catat log gagal (best-effort)
  try{
    $hasil = 'GAGAL';
    $message = substr($e->getMessage(), 0, 250);
    $st = $pdo->prepare("
      INSERT INTO activity_logs (user_id, group_id, device_id, status, hasil, message)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $st->execute([$_SESSION['user_id'], $group_id, $device_id, $status, $hasil, $message]);
  }catch(Exception $e2){}

  fail('Gagal update status', 500, ['error' => $e->getMessage()]);
}
