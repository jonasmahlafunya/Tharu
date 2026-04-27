<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth   = require_admin();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: notifications sent/received ─────────────────────
if ($method === 'GET') {
  $user_id = (int)($_GET['user_id'] ?? 0);
  if ($user_id) {
    $stmt = $pdo->prepare(
      'SELECT n.*, u.name as sent_by_name FROM notifications n
       JOIN users u ON u.id = n.sent_by
       WHERE n.user_id = ? ORDER BY n.created_at DESC'
    );
    $stmt->execute([$user_id]);
    json_out($stmt->fetchAll());
  }
  // All notifications
  $stmt = $pdo->query(
    'SELECT n.*, u.name as recipient_name, adm.name as sent_by_name
     FROM notifications n JOIN users u ON u.id=n.user_id JOIN users adm ON adm.id=n.sent_by
     ORDER BY n.created_at DESC LIMIT 100'
  );
  json_out($stmt->fetchAll());
}

// ── POST: send notification ───────────────────────────────
if ($method === 'POST') {
  $body    = json_decode(file_get_contents('php://input'), true);
  $message = trim($body['message'] ?? '');
  $to      = $body['to'] ?? 'all'; // 'all' or user_id

  if (!$message) json_error('Message is required');

  if ($to === 'all') {
    $users = $pdo->query('SELECT id FROM users WHERE role="client" AND status="active"')->fetchAll();
    $stmt  = $pdo->prepare('INSERT INTO notifications (user_id, message, sent_by) VALUES (?, ?, ?)');
    foreach ($users as $u) {
      $stmt->execute([$u['id'], $message, $auth['sub']]);
    }
    json_out(['message' => 'Notification sent to all clients', 'count' => count($users)]);
  } else {
    $user_id = (int)$to;
    $pdo->prepare('INSERT INTO notifications (user_id, message, sent_by) VALUES (?, ?, ?)')
        ->execute([$user_id, $message, $auth['sub']]);
    json_out(['message' => 'Notification sent']);
  }
}

json_error('Method not allowed', 405);
