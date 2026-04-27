<?php
// ── DB CONFIG — update these before deploying ──────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'tharu_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ── JWT SECRET — change this to a long random string ───────
define('JWT_SECRET', 'THARU_CHANGE_THIS_SECRET_2025_xK9#mP2$vL8');

// ── Upload path ─────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../uploads/claims/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg','image/png','application/pdf']);

// ── PDO Connection ──────────────────────────────────────────
function db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
      $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
      http_response_code(500);
      die(json_encode(['error' => 'Database connection failed']));
    }
  }
  return $pdo;
}

// ── JSON helpers ────────────────────────────────────────────
function json_out(mixed $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

function json_error(string $msg, int $code = 400): void {
  json_out(['error' => $msg], $code);
}
