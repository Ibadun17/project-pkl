<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

require_once __DIR__ . '/../../app/services/TuyaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);

$deviceId = trim($_GET['device_id'] ?? '');
if ($deviceId === '') fail('device_id wajib', 422);

try {
  $svc = new TuyaService();
  $status = $svc->getDeviceStatus($deviceId);
  ok($status['result'] ?? $status, 'OK');
} catch (Throwable $e) {
  fail('TUYA ERROR: '.$e->getMessage(), 500);
}
