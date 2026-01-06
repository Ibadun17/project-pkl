<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();
require_once __DIR__ . '/../../app/services/TuyaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();
$deviceId = trim($body['device_id'] ?? '');
$status   = strtoupper(trim($body['status'] ?? ''));
$code     = trim($body['code'] ?? 'switch_1');

if ($deviceId === '') fail('device_id wajib', 422);
if (!in_array($status, ['ON', 'OFF'], true)) fail('status harus ON/OFF', 422);

$pdo = db();

try {
  // 1) Kirim perintah ke Tuya
  $svc = new TuyaService();
  $svc->setSwitch($deviceId, $status === 'ON', $code);

  // 2) Ambil lokasi utk group/area (opsional)
  $st = $pdo->prepare("SELECT lokasi FROM devices WHERE device_id = ? LIMIT 1");
  $st->execute([$deviceId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  $groupArea = $row['lokasi'] ?? '-';

  // 3) Simpan log aktivitas (WAJIB isi 'action' karena kolom action NOT NULL)
  $action = 'TOGGLE_SWITCH';
  $detail = "Toggle {$deviceId} -> {$status} (code={$code})";

  $stLog = $pdo->prepare("
    INSERT INTO activity_logs (user_id, action, detail, device_id, group_area, status, hasil)
    VALUES (?, ?, ?, ?, ?, ?, 'SUKSES')
  ");
  $stLog->execute([
    $_SESSION['user_id'],
    $action,
    $detail,
    $deviceId,
    $groupArea,
    $status
  ]);

  ok([
    'device_id' => $deviceId,
    'status'    => $status,
    'code'      => $code
  ], 'OK');

} catch (Throwable $e) {

  // log gagal (biar keliatan juga di Log Aktivitas)
  try {
    $action = 'TOGGLE_SWITCH';
    $detail = "Gagal toggle {$deviceId} -> {$status} (code={$code}): " . $e->getMessage();

    $stLogFail = $pdo->prepare("
      INSERT INTO activity_logs (user_id, action, detail, device_id, group_area, status, hasil)
      VALUES (?, ?, ?, ?, '-', ?, 'GAGAL')
    ");
    $stLogFail->execute([
      $_SESSION['user_id'],
      $action,
      $detail,
      $deviceId,
      $status
    ]);
  } catch (Throwable $e2) {
    // kalau log gagal pun, tetap jangan bikin output error tambahan
  }

  fail('TUYA ERROR: ' . $e->getMessage(), 500);
}
