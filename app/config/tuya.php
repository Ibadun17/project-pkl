<?php
// app/config/tuya.php

function tuya_config(): array {
  $clientId = $_ENV['TUYA_CLIENT_ID'] ?? '';
  $clientSecret = $_ENV['TUYA_CLIENT_SECRET'] ?? '';
  $baseUrl = $_ENV['TUYA_BASE_URL'] ?? '';
  $region = $_ENV['TUYA_REGION'] ?? '';

  // fallback base url dari region (kalau TUYA_BASE_URL kosong)
  if ($baseUrl === '') {
    $map = [
      'us' => 'https://openapi.tuyaus.com',
      'eu' => 'https://openapi.tuyaeu.com',
      'cn' => 'https://openapi.tuyacn.com',
      'in' => 'https://openapi.tuyain.com',
    ];
    $baseUrl = $map[$region] ?? 'https://openapi.tuyaus.com';
  }

  return [
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'base_url' => rtrim($baseUrl, '/'),
    'region' => $region,
  ];
}
