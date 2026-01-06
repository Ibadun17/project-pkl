<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);

$pdo = db();

// devices
$st = $pdo->query("SELECT device_id, nama_perangkat, lokasi FROM devices ORDER BY id DESC");
$devices = $st->fetchAll(PDO::FETCH_ASSOC);

// groups
$st = $pdo->query("SELECT id, nama_group, lokasi FROM device_groups ORDER BY id DESC");
$groups = $st->fetchAll(PDO::FETCH_ASSOC);

ok([
  'devices' => $devices,
  'groups' => $groups
], 'OK');
