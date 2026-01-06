<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

require_once __DIR__ . '/../../app/services/TuyaService.php';

try {
  $svc = new TuyaService();
  // ini contoh, tergantung API: butuh home_id atau asset_id
  // kita bikin aja test token dulu:
  $token = $svc->getAccessToken();
  ok(['token_ok' => true, 'token' => substr($token,0,8).'...'], 'TOKEN OK');
} catch (Throwable $e) {
  fail('TUYA ERROR: '.$e->getMessage(), 500);
}
