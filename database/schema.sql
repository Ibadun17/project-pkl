-- =========================================
-- SMART KOMINFO KOTA MADIUN - DATABASE SCHEMA
-- MySQL / phpMyAdmin
-- =========================================

-- 1) Buat database (ubah nama kalau kamu mau)
CREATE DATABASE IF NOT EXISTS smart_kominfo
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smart_kominfo;

-- =========================================
-- TABLE: users
-- =========================================
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(120) NULL,
  nip VARCHAR(30) NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_users_role ON users(role);

-- =========================================
-- TABLE: devices
-- (menyimpan device id Tuya + info perangkat)
-- =========================================
CREATE TABLE IF NOT EXISTS devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- ID device dari Tuya / platform IoT kamu
  device_id VARCHAR(80) NOT NULL UNIQUE,

  nama_perangkat VARCHAR(120) NOT NULL,
  lokasi VARCHAR(190) NOT NULL,

  -- function (sesuaikan kebutuhanmu)
  fungsi VARCHAR(50) NOT NULL,

  -- status terakhir untuk tampilan cepat (optional)
  status_terakhir ENUM('ON','OFF') NULL DEFAULT NULL,
  tegangan_terakhir VARCHAR(20) NULL DEFAULT NULL,

  -- siapa yang menambahkan
  created_by BIGINT UNSIGNED NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_devices_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_devices_lokasi ON devices(lokasi);
CREATE INDEX idx_devices_fungsi ON devices(fungsi);

-- =========================================
-- TABLE: groups
-- (group lampu / area, terkait device)
-- =========================================
CREATE TABLE IF NOT EXISTS groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  nama_group VARCHAR(120) NOT NULL,
  lokasi VARCHAR(190) NOT NULL,

  -- device_id mengarah ke devices.device_id (bukan devices.id)
  device_id VARCHAR(80) NOT NULL,

  area_zone VARCHAR(120) NOT NULL,

  created_by BIGINT UNSIGNED NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_groups_device_id
    FOREIGN KEY (device_id) REFERENCES devices(device_id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT fk_groups_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_groups_nama ON groups(nama_group);
CREATE INDEX idx_groups_area ON groups(area_zone);

-- =========================================
-- TABLE: activity_logs
-- (log aktivitas ON/OFF + hasil)
-- =========================================
CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- relasi user (siapa yang melakukan)
  user_id BIGINT UNSIGNED NULL,

  -- relasi group (optional)
  group_id BIGINT UNSIGNED NULL,

  -- device id (Tuya) yang diubah
  device_id VARCHAR(80) NOT NULL,

  -- status yang diproses
  status ENUM('ON','OFF') NOT NULL,

  -- hasil eksekusi
  hasil ENUM('SUKSES','GAGAL') NOT NULL DEFAULT 'SUKSES',

  -- pesan error / catatan (optional)
  message VARCHAR(255) NULL,

  -- waktu aktivitas
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT fk_logs_group
    FOREIGN KEY (group_id) REFERENCES groups(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT fk_logs_device
    FOREIGN KEY (device_id) REFERENCES devices(device_id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_logs_created_at ON activity_logs(created_at);
CREATE INDEX idx_logs_device_id ON activity_logs(device_id);
CREATE INDEX idx_logs_status ON activity_logs(status);
CREATE INDEX idx_logs_hasil ON activity_logs(hasil);

-- =========================================
-- (OPSIONAL) SEED DATA (contoh user admin)
-- Password: admin123 (hash sudah disiapkan)
-- =========================================
INSERT INTO users (nama, nip, email, password_hash, role)
VALUES (
  'Admin Smart Kominfo',
  '0000000000',
  'admin@kominfo.test',
  '$2y$10$3UQw5R7oQbNqS7cYzAkbY.6i8h0l.9h9d7YJvB3nYv8bq3lP5c3yG',
  'admin'
)
ON DUPLICATE KEY UPDATE email=email;

-- Catatan:
-- Hash di atas adalah contoh bcrypt. Kalau kamu mau bikin sendiri:
-- di PHP: password_hash('admin123', PASSWORD_BCRYPT)

-- =========================================================
-- Smart Kominfo - Schema Fix
-- MySQL 8+ recommended
-- =========================================================

SET NAMES utf8mb4;
SET time_zone = "+00:00";

-- =========================================================
-- USERS
-- =========================================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(120) NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'USER',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- DEVICES
-- =========================================================
CREATE TABLE IF NOT EXISTS devices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  device_id VARCHAR(64) NOT NULL,
  -- kompatibilitas: project kamu kadang pakai fungsi, kadang switch_code
  fungsi VARCHAR(64) NULL,
  switch_code VARCHAR(64) NULL,

  nama_perangkat VARCHAR(160) NOT NULL,
  lokasi VARCHAR(160) NOT NULL,

  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_devices_device_id (device_id),
  KEY idx_devices_created_by (created_by),
  KEY idx_devices_lokasi (lokasi),

  CONSTRAINT fk_devices_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- DEVICE GROUPS
-- 1 group = 1 device utama (biar bisa ON/OFF bareng)
-- =========================================================
CREATE TABLE IF NOT EXISTS device_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama_group VARCHAR(160) NOT NULL,
  lokasi VARCHAR(160) NOT NULL,
  area_zone VARCHAR(160) NULL,

  -- group terikat ke 1 device
  device_id VARCHAR(64) NOT NULL,

  -- code untuk ON/OFF group (default switch_1)
  tuya_code VARCHAR(64) NOT NULL DEFAULT 'switch_1',

  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_groups_created_by (created_by),
  KEY idx_groups_device_id (device_id),
  KEY idx_groups_lokasi (lokasi),

  CONSTRAINT fk_groups_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT fk_groups_device_id
    FOREIGN KEY (device_id) REFERENCES devices(device_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- GROUP LAMPS
-- daftar lampu di dalam group
-- =========================================================
CREATE TABLE IF NOT EXISTS group_lamps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  device_id VARCHAR(64) NOT NULL,

  lampu_nama VARCHAR(160) NOT NULL,

  -- kalau semua bareng: ini sama semua (misal switch_1)
  -- kalau suatu saat multi-channel: bisa switch_1, switch_2, dst
  tuya_code VARCHAR(64) NOT NULL DEFAULT 'switch_1',

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY idx_group_lamps_group (group_id),
  KEY idx_group_lamps_device (device_id),

  CONSTRAINT fk_group_lamps_group
    FOREIGN KEY (group_id) REFERENCES device_groups(id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_group_lamps_device
    FOREIGN KEY (device_id) REFERENCES devices(device_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- ACTIVITY LOGS
-- sesuai hasil SHOW COLUMNS yang kamu kirim
-- =========================================================
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,

  action VARCHAR(50) NOT NULL,
  detail TEXT NOT NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  device_id VARCHAR(64) NULL,
  group_area VARCHAR(120) NULL,

  status VARCHAR(10) NULL,         -- ON / OFF (opsional)
  hasil VARCHAR(10) NOT NULL DEFAULT 'SUKSES',  -- SUKSES / GAGAL

  KEY idx_activity_user (user_id),
  KEY idx_activity_device (device_id),
  KEY idx_activity_created (created_at),
  KEY idx_activity_hasil (hasil),
  KEY idx_activity_status (status),

  CONSTRAINT fk_activity_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- SCHEDULES (Jadwal Otomatis)
-- target bisa DEVICE atau GROUP
-- days_mask: bitmask 7 hari (Sen..Min) contoh:
-- Sen=1, Sel=2, Rab=4, Kam=8, Jum=16, Sab=32, Min=64
-- default 127 = tiap hari
-- =========================================================
CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,

  created_by INT NOT NULL,

  target_type ENUM('DEVICE','GROUP') NOT NULL,

  -- isi salah satu:
  device_id VARCHAR(64) NULL,
  group_id INT NULL,

  tuya_code VARCHAR(64) NOT NULL DEFAULT 'switch_1',

  on_time TIME NOT NULL,
  off_time TIME NOT NULL,

  days_mask INT NOT NULL DEFAULT 127,
  is_active TINYINT(1) NOT NULL DEFAULT 1,

  last_run_at DATETIME NULL,

  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_sched_created_by (created_by),
  KEY idx_sched_target_type (target_type),
  KEY idx_sched_device (device_id),
  KEY idx_sched_group (group_id),
  KEY idx_sched_active (is_active),

  CONSTRAINT fk_sched_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_sched_device
    FOREIGN KEY (device_id) REFERENCES devices(device_id)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT fk_sched_group
    FOREIGN KEY (group_id) REFERENCES device_groups(id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  -- memastikan salah satu terisi (MySQL 8 mendukung CHECK, tapi beberapa hosting matiin)
  -- jadi ini optional:
  -- CHECK (
  --   (target_type='DEVICE' AND device_id IS NOT NULL AND group_id IS NULL)
  --   OR
  --   (target_type='GROUP' AND group_id IS NOT NULL AND device_id IS NULL)
  -- )
  --
  -- (kalau CHECK tidak aktif, validasi tetap dilakukan di PHP)
  --
  -- no trailing comma
  UNIQUE KEY uq_sched_unique (target_type, device_id, group_id, tuya_code, on_time, off_time, days_mask)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

