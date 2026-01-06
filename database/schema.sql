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
