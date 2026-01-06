<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();

// terima beberapa kemungkinan nama field dari FE
$device_id = trim($body['device_id'] ?? '');
$nama = trim($body['nama_perangkat'] ?? '');
$lokasi = trim($body['lokasi'] ?? '');

// function tuya code bisa dikirim sebagai "fungsi" atau "switch_code"
$tuya_code = trim($body['fungsi'] ?? ($body['switch_code'] ?? ''));

$missing = [];
if ($device_id === '') $missing[] = 'device_id';
if ($tuya_code === '') $missing[] = 'fungsi'; // tetap sebut fungsi biar sesuai form
if ($nama === '') $missing[] = 'nama_perangkat';
if ($lokasi === '') $missing[] = 'lokasi';
if ($missing) fail('Field wajib belum diisi', 422, ['missing' => $missing]);

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  // ini sering kejadian kalau session kebaca login tapi user_id tidak ada
  fail('Session user_id tidak ditemukan. Silakan login ulang.', 401);
}

$pdo = db();

try {
  // 1) cek kolom yang ada: "fungsi" atau "switch_code"
  $cols = $pdo->query("SHOW COLUMNS FROM devices")->fetchAll(PDO::FETCH_COLUMN, 0);
  $hasFungsi = in_array('fungsi', $cols, true);
  $hasSwitchCode = in_array('switch_code', $cols, true);

  $codeCol = null;
  if ($hasFungsi) $codeCol = 'fungsi';
  else if ($hasSwitchCode) $codeCol = 'switch_code';
  else {
    fail("Struktur tabel devices tidak punya kolom 'fungsi' atau 'switch_code'. Cek tabel devices.", 500);
  }

  // 2) cegah duplicate device_id
  $st = $pdo->prepare("SELECT id FROM devices WHERE device_id = ? LIMIT 1");
  $st->execute([$device_id]);
  if ($st->fetch()) fail('Device ID sudah terdaftar', 409);

  // 3) insert sesuai kolom yang ada
  $sql = "
    INSERT INTO devices (device_id, {$codeCol}, nama_perangkat, lokasi, created_by)
    VALUES (?, ?, ?, ?, ?)
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$device_id, $tuya_code, $nama, $lokasi, $userId]);

    // ===============================
  // TAMBAHAN: simpan log "Tambah device"
  // ===============================
  $logCols = $pdo->query("SHOW COLUMNS FROM activity_logs")->fetchAll(PDO::FETCH_COLUMN, 0);

  // mapping kolom yang mungkin berbeda-beda namanya
  $colUser  = in_array('user_id', $logCols, true) ? 'user_id' : null;
  $colDev   = in_array('device_id', $logCols, true) ? 'device_id' : null;

  // group/area bisa punya beberapa nama
  $colGroup = null;
  foreach (['group_area', 'grouparea', 'area', 'area_zone', 'group_name', 'nama_group'] as $c) {
    if (in_array($c, $logCols, true)) { $colGroup = $c; break; }
  }

  // status bisa punya beberapa nama
  $colStatus = null;
  foreach (['status', 'device_status', 'aksi_status'] as $c) {
    if (in_array($c, $logCols, true)) { $colStatus = $c; break; }
  }

  // hasil bisa punya beberapa nama
  $colHasil = null;
  foreach (['hasil', 'result'] as $c) {
    if (in_array($c, $logCols, true)) { $colHasil = $c; break; }
  }

  // detail bisa punya beberapa nama
  $colDetail = null;
  foreach (['detail', 'keterangan', 'message'] as $c) {
    if (in_array($c, $logCols, true)) { $colDetail = $c; break; }
  }

  // created_at bisa punya beberapa nama
  $colCreated = null;
  foreach (['created_at', 'waktu', 'createdAt'] as $c) {
    if (in_array($c, $logCols, true)) { $colCreated = $c; break; }
  }

  // minimal wajib ada user_id + device_id
  if (!$colUser || !$colDev) {
    fail("Struktur tabel activity_logs tidak sesuai. Minimal harus ada kolom user_id dan device_id.", 500, [
      'found_columns' => $logCols
    ]);
  }

  // susun kolom & value insert sesuai yang tersedia
  $cols = [$colUser, $colDev];
  $vals = [$userId, $device_id];

  if ($colGroup)  { $cols[] = $colGroup;  $vals[] = $lokasi; }
  if ($colStatus) { $cols[] = $colStatus; $vals[] = 'OFF'; } // default tambah device
  if ($colHasil)  { $cols[] = $colHasil;  $vals[] = 'SUKSES'; }
  if ($colDetail) { $cols[] = $colDetail; $vals[] = "Tambah device: {$nama} (code={$tuya_code})"; }
  if ($colCreated){ $cols[] = $colCreated; }

  $placeholders = rtrim(str_repeat('?,', count($vals)), ',');
  // kalau ada created_at, pakai NOW() tanpa placeholder
  if ($colCreated) {
    $sqlLog = "INSERT INTO activity_logs (" . implode(',', $cols) . ")
              VALUES ($placeholders, NOW())";
  } else {
    $sqlLog = "INSERT INTO activity_logs (" . implode(',', $cols) . ")
              VALUES ($placeholders)";
  }

  $stLog = $pdo->prepare($sqlLog);
  $stLog->execute($vals);

     // 4) INSERT LOG: device baru ditambahkan (WAJIB isi kolom action)
  $stLog = $pdo->prepare("
    INSERT INTO activity_logs (user_id, action, detail, device_id, group_area, status, hasil)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $stLog->execute([
    $userId,
    'ADD_DEVICE',
    "Tambah device: {$nama} ({$device_id}) code={$tuya_code} lokasi={$lokasi}",
    $device_id,
    $lokasi,     // group_area pakai lokasi dulu
    'OFF',       // default status device baru
    'SUKSES'
  ]);




  ok(['id' => (int)$pdo->lastInsertId()], 'Device berhasil ditambahkan');
} catch (PDOException $e) {
  // tampilkan error DB yang jelas
  fail('DB ERROR: '.$e->getMessage(), 500, [
    'sqlstate' => $e->getCode(),
  ]);
} catch (Throwable $e) {
  fail('ERROR: '.$e->getMessage(), 500);
}
