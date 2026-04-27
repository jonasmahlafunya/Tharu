<?php
require_once __DIR__ . '/db.php';

// ── Minimal JWT (no library needed) ─────────────────────────
function jwt_encode(array $payload): string {
  $header  = base64url_encode(json_encode(['alg'=>'HS256','typ'=>'JWT']));
  $payload = base64url_encode(json_encode($payload));
  $sig     = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
  return "$header.$payload.$sig";
}

function jwt_decode(string $token): ?array {
  $parts = explode('.', $token);
  if (count($parts) !== 3) return null;
  [$header, $payload, $sig] = $parts;
  $expected = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
  if (!hash_equals($expected, $sig)) return null;
  $data = json_decode(base64url_decode($payload), true);
  if (!$data || (isset($data['exp']) && $data['exp'] < time())) return null;
  return $data;
}

function base64url_encode(string $data): string {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
  return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

// ── Create a token (24h expiry) ─────────────────────────────
function create_token(array $user): string {
  return jwt_encode([
    'sub'  => $user['id'],
    'name' => $user['name'],
    'role' => $user['role'],
    'exp'  => time() + 86400,
  ]);
}

// ── Require valid token — returns payload or dies ───────────
function require_auth(): array {
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
    json_error('Unauthorised', 401);
  }
  $payload = jwt_decode($m[1]);
  if (!$payload) json_error('Invalid or expired token', 401);
  return $payload;
}

// ── Require admin role ───────────────────────────────────────
function require_admin(): array {
  $payload = require_auth();
  if ($payload['role'] !== 'admin') json_error('Forbidden', 403);
  return $payload;
}
