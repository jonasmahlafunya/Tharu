-- ============================================================
-- THARU FUNERAL SERVICES — Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS tharu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tharu_db;

-- ── USERS ──────────────────────────────────────────────────
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(20),
  id_number     VARCHAR(30),
  role          ENUM('client','admin') NOT NULL DEFAULT 'client',
  status        ENUM('active','suspended','pending') NOT NULL DEFAULT 'pending',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── POLICIES ───────────────────────────────────────────────
CREATE TABLE policies (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  plan          ENUM('A','B','C','D','E') NOT NULL,
  premium       DECIMAL(10,2) NOT NULL,
  join_date     DATE NOT NULL,
  status        ENUM('active','suspended','lapsed','pending') NOT NULL DEFAULT 'pending',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── MEMBERS ────────────────────────────────────────────────
CREATE TABLE members (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  policy_id     INT NOT NULL,
  name          VARCHAR(150) NOT NULL,
  id_number     VARCHAR(30),
  relationship  VARCHAR(80),
  date_of_birth DATE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE CASCADE
);

-- ── PAYMENTS ───────────────────────────────────────────────
CREATE TABLE payments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  policy_id     INT NOT NULL,
  amount        DECIMAL(10,2) NOT NULL,
  payment_date  DATE NOT NULL,
  recorded_by   INT NOT NULL,
  method        ENUM('cash','eft','other') DEFAULT 'eft',
  note          TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- ── CLAIMS ─────────────────────────────────────────────────
CREATE TABLE claims (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  policy_id       INT NOT NULL,
  claimant_name   VARCHAR(150) NOT NULL,
  deceased_name   VARCHAR(150) NOT NULL,
  date_of_death   DATE NOT NULL,
  description     TEXT,
  status          ENUM('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note      TEXT,
  reviewed_by     INT,
  reviewed_at     TIMESTAMP NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- ── CLAIM DOCUMENTS ────────────────────────────────────────
CREATE TABLE claim_documents (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  claim_id    INT NOT NULL,
  file_name   VARCHAR(255) NOT NULL,
  file_path   VARCHAR(500) NOT NULL,
  file_type   VARCHAR(80),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE
);

-- ── NOTIFICATIONS ──────────────────────────────────────────
CREATE TABLE notifications (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  message     TEXT NOT NULL,
  sent_by     INT NOT NULL,
  read_at     TIMESTAMP NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (sent_by) REFERENCES users(id)
);

-- ── SEED: Default Admin Account ─────────────────────────────
-- Password: Admin@Tharu2025 (change immediately after first login)
INSERT INTO users (name, email, password_hash, phone, role, status)
VALUES (
  'Tharu Admin',
  'admin@tharu.co.za',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJadCvVa2',
  '071 674 8911',
  'admin',
  'active'
);
