<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

require_once __DIR__ . '/../../app/services/TuyaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);

$deviceId = trim($_GET['device_id'] ?? '');
if ($deviceId === '') fail('device_id wajib', 422);

try {
  $svc = new TuyaService();

$device = $svc->getDeviceDetail($deviceId);
$status = $svc->getDeviceStatus($deviceId);

// SIMPAN KE SESSION
$_SESSION['checked_device_id'] = $deviceId;

ok([
  'device' => $device['result'] ?? $device,
  'status' => $status['result'] ?? $status,
], 'Connected');



} catch (Throwable $e) {
  fail('TUYA ERROR: ' . $e->getMessage(), 500, [
    'file' => $e->getFile(),
    'line' => $e->getLine(),
  ]);
}
