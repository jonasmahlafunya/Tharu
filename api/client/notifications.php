<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth    = require_auth();
$user_id = $auth['sub'];
$pdo     = db();
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $stmt = $pdo->prepare(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50'
  );
  $stmt->execute([$user_id]);
  $notes = $stmt->fetchAll();

  // Mark as read
  $pdo->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')
      ->execute([$user_id]);

  json_out($notes);
}

json_error('Method not allowed', 405);
