<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();

/**
 * lamps itu ARRAY, jadi require_fields harus sudah versi yang kamu update (biar gak Array to string conversion).
 * Aku tetap pakai require_fields seperti punyamu.
 */
$missing = require_fields($body, ['nama_group', 'lokasi', 'device_id', 'lamps']);
if ($missing) fail('Field wajib belum diisi', 422, ['missing' => $missing]);

$nama_group = trim($body['nama_group']);
$lokasi     = trim($body['lokasi']);
$area_zone  = trim($body['area_zone'] ?? '');
$device_id  = trim($body['device_id']);
$lamps      = $body['lamps'];

/**
 * ✅ TAMBAHAN: tuya_code 1 untuk ON/OFF group (dari FE kamu sekarang)
 * Kalau tidak dikirim, default switch_1
 */
$group_tuya_code = trim($body['tuya_code'] ?? 'switch_1');
if ($group_tuya_code === '') $group_tuya_code = 'switch_1';

if ($nama_group === '' || $lokasi === '' || $device_id === '') {
  fail('Nama group, lokasi, dan device id wajib diisi', 422);
}

if (!is_array($lamps) || count($lamps) < 1) {
  fail('Minimal harus ada 1 lampu', 422);
}

$pdo = db();

// OPTIONAL (disarankan): pastikan device sudah ada di tabel devices
$st = $pdo->prepare("SELECT id FROM devices WHERE device_id = ? LIMIT 1");
$st->execute([$device_id]);
if (!$st->fetch()) {
  fail('Device ID belum ada di database. Tambahkan dulu lewat menu Tambah Device.', 422);
}

// validasi lamp list
$clean = [];

/**
 * ✅ PERUBAHAN LOGIKA (tanpa menghapus):
 * - support 2 format nama lampu: nama_lampu (FE baru) atau lampu_nama (versi lama)
 * - tuya_code bisa per lampu ATAU pakai group_tuya_code (1 code untuk semua)
 * - kalau kamu mau "semua lampu bareng", enforce semua tuya_code harus sama
 */
$seenCodes = [];
$effectiveCode = null; // kode yang dipakai (harus sama untuk semua jika mode bareng)

foreach ($lamps as $i => $l) {
  if (!is_array($l)) {
    fail("Lampu baris ke-".($i+1)." format tidak valid", 422);
  }

  // ✅ TERIMA dua kemungkinan key dari FE/versi lama
  $lampu_nama = trim($l['nama_lampu'] ?? ($l['lampu_nama'] ?? ''));

  // ✅ tuya_code per lampu (opsional) atau fallback ke group_tuya_code
  $tuya_code_item = trim($l['tuya_code'] ?? '');
  $tuya_code = $tuya_code_item !== '' ? $tuya_code_item : $group_tuya_code;

  if ($lampu_nama === '') {
    fail("Lampu baris ke-".($i+1)." wajib isi Nama Lampu", 422);
  }

  if ($tuya_code === '') {
    // seharusnya gak kejadian karena default switch_1
    fail("Lampu baris ke-".($i+1)." Tuya Code kosong", 422);
  }

  /**
   * ✅ MODE BARENG (sesuai request kamu):
   * semua lampu dalam group harus pakai switch code yang sama,
   * karena 1 device dikontrol bareng dari 1 tombol.
   */
  if ($effectiveCode === null) {
    $effectiveCode = $tuya_code;
  } else {
    if (strtolower($tuya_code) !== strtolower($effectiveCode)) {
      fail("Semua lampu dalam group harus menggunakan Tuya Code yang sama. (Baris ke-".($i+1).")", 422);
    }
  }

  // kamu sebelumnya cegah duplikat tuya_code per lampu, tapi sekarang memang harus sama → jadi ini gak relevan.
  // Aku BIARKAN variabel seenCodes tetap ada, tapi tidak dipakai untuk memblok karena memang harus sama.

  $clean[] = [
    'lampu_nama' => $lampu_nama,
    'tuya_code'  => $tuya_code,
  ];
}

try {
  $pdo->beginTransaction();

  // insert group
  $st = $pdo->prepare("
    INSERT INTO device_groups (nama_group, lokasi, area_zone, created_by, created_at)
    VALUES (?, ?, ?, ?, NOW())
  ");
  $st->execute([$nama_group, $lokasi, $area_zone ?: null, $_SESSION['user_id']]);
  $group_id = (int)$pdo->lastInsertId();

  // insert lamp list
  $st = $pdo->prepare("
    INSERT INTO group_lamps (group_id, device_id, lampu_nama, tuya_code, created_at)
    VALUES (?, ?, ?, ?, NOW())
  ");

  foreach ($clean as $l) {
    $st->execute([$group_id, $device_id, $l['lampu_nama'], $l['tuya_code']]);
  }

  $pdo->commit();

  ok([
    'group_id'    => $group_id,
    'nama_group'  => $nama_group,
    'device_id'   => $device_id,
    'area_zone'   => $area_zone,
    'tuya_code'   => $effectiveCode, // kode yang dipakai untuk semua lampu
    'lamp_count'  => count($clean)
  ], 'Group berhasil dibuat');

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail('Gagal simpan group: '.$e->getMessage(), 500);
}
