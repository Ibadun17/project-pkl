<?php
// api/cron/run_schedules.php

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../../app/services/TuyaService.php';

$pdo = db();
$nowTime = date('H:i');
$day = (int)date('N'); // 1=Senin ... 7=Minggu
$dayMask = 1 << ($day - 1); // Sen=1, Sel=2, Rab=4, ...

// Ambil jadwal aktif yang sesuai hari
$st = $pdo->prepare("
  SELECT *
  FROM schedules
  WHERE is_active = 1
    AND (days_mask & ?) > 0
");
$st->execute([$dayMask]);
$schedules = $st->fetchAll(PDO::FETCH_ASSOC);

$tuya = new TuyaService();

foreach ($schedules as $s) {

  try {
    // ON
    if ($s['on_time'] === $nowTime) {

      if ($s['target_type'] === 'DEVICE') {
        $tuya->setSwitch($s['device_id'], true, $s['tuya_code']);
      }

      if ($s['target_type'] === 'GROUP') {
        // ambil device_id dari group
        $g = $pdo->prepare("SELECT device_id FROM device_groups WHERE id=?");
        $g->execute([$s['group_id']]);
        if ($row = $g->fetch()) {
          $tuya->setSwitch($row['device_id'], true, $s['tuya_code']);
        }
      }

      logActivity($pdo, $s, 'ON', 'SUKSES');
    }

    // OFF
    if ($s['off_time'] === $nowTime) {

      if ($s['target_type'] === 'DEVICE') {
        $tuya->setSwitch($s['device_id'], false, $s['tuya_code']);
      }

      if ($s['target_type'] === 'GROUP') {
        $g = $pdo->prepare("SELECT device_id FROM device_groups WHERE id=?");
        $g->execute([$s['group_id']]);
        if ($row = $g->fetch()) {
          $tuya->setSwitch($row['device_id'], false, $s['tuya_code']);
        }
      }

      logActivity($pdo, $s, 'OFF', 'SUKSES');
    }

  } catch (Throwable $e) {
    logActivity($pdo, $s, '-', 'GAGAL', $e->getMessage());
  }
}

function logActivity($pdo, $s, $status, $hasil, $detail = '') {
  $st = $pdo->prepare("
    INSERT INTO activity_logs
      (user_id, action, detail, device_id, group_area, status, hasil)
    VALUES (?, 'SCHEDULE', ?, ?, ?, ?, ?)
  ");
  $st->execute([
    $s['created_by'],
    $detail ?: 'Jadwal otomatis',
    $s['device_id'] ?? null,
    $s['group_id'] ? 'GROUP-'.$s['group_id'] : null,
    $status,
    $hasil
  ]);
}
