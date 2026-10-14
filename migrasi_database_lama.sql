-- MIGRASI DARI STARTER CELENGAN SEBELUMNYA (yang belum memiliki login)
-- Jalankan file ini SATU KALI pada database celengan lama.
-- File ini tidak menghapus transaksi lama; user_id akan diisi setelah akun pemilik didaftarkan.
USE celengan;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE transaksi
  ADD COLUMN user_id INT UNSIGNED NULL AFTER id,
  ADD INDEX idx_transaksi_user_tanggal (user_id, tanggal),
  ADD INDEX idx_transaksi_user_tipe (user_id, tipe),
  ADD CONSTRAINT fk_transaksi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE target_tabungan
  ADD COLUMN user_id INT UNSIGNED NULL AFTER id,
  MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ADD UNIQUE KEY uq_target_user (user_id),
  ADD CONSTRAINT fk_target_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS remember_tokens (
  selector CHAR(24) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_remember_user (user_id),
  INDEX idx_remember_expires (expires_at),
  CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Setelah migrasi, buat akun pemilik melalui register.php.
-- Lalu buka phpMyAdmin > SQL dan jalankan dua perintah di README.txt
-- untuk mengaitkan transaksi serta target lama ke username tersebut.
