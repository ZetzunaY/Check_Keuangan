CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaksi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  tipe ENUM('pemasukan', 'pengeluaran', 'setor_tabungan') NOT NULL,
  nominal DECIMAL(15,2) NOT NULL,
  keterangan VARCHAR(255) NOT NULL,
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_transaksi_user_tanggal (user_id, tanggal),
  INDEX idx_transaksi_user_tipe (user_id, tipe),
  CONSTRAINT fk_transaksi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS target_tabungan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  nominal DECIMAL(15,2) NOT NULL DEFAULT 10000000,
  target_tanggal DATE NOT NULL,
  diperbarui_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_target_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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
