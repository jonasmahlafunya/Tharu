<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth    = require_auth();
$user_id = $auth['sub'];
$pdo     = db();
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $stmt = $pdo->prepare('SELECT id, name, email, phone, id_number, status, created_at FROM users WHERE id = ?');
  $stmt->execute([$user_id]);
  json_out($stmt->fetch());
}

if ($method === 'PUT') {
  $body  = json_decode(file_get_contents('php://input'), true);
  $name  = trim($body['name'] ?? '');
  $phone = trim($body['phone'] ?? '');

  if (!$name) json_error('Name is required');

  // Password change (optional)
  if (!empty($body['new_password'])) {
    if (strlen($body['new_password']) < 8) json_error('Password must be at least 8 characters');
    if (empty($body['current_password'])) json_error('Current password is required');

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!password_verify($body['current_password'], $user['password_hash']))
      json_error('Current password is incorrect');

    $pdo->prepare('UPDATE users SET name=?, phone=?, password_hash=? WHERE id=?')
        ->execute([$name, $phone, password_hash($body['new_password'], PASSWORD_BCRYPT), $user_id]);
  } else {
    $pdo->prepare('UPDATE users SET name=?, phone=? WHERE id=?')
        ->execute([$name, $phone, $user_id]);
  }

  json_out(['message' => 'Profile updated successfully']);
}

json_error('Method not allowed', 405);
