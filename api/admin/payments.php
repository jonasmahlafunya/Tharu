<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth   = require_admin();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: all payments with filters ────────────────────────
if ($method === 'GET') {
  $month      = $_GET['month'] ?? '';  // YYYY-MM
  $policy_id  = (int)($_GET['policy_id'] ?? 0);
  $where      = ['1=1'];
  $params     = [];

  if ($month)     { $where[] = "DATE_FORMAT(p.payment_date, '%Y-%m') = ?"; $params[] = $month; }
  if ($policy_id) { $where[] = 'p.policy_id = ?'; $params[] = $policy_id; }

  $stmt = $pdo->prepare(
    'SELECT p.*, u.name as client_name, u.email as client_email,
            pol.plan, pol.premium,
            adm.name as recorded_by_name
     FROM payments p
     JOIN policies pol ON pol.id = p.policy_id
     JOIN users u ON u.id = pol.user_id
     JOIN users adm ON adm.id = p.recorded_by
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY p.payment_date DESC'
  );
  $stmt->execute($params);
  json_out($stmt->fetchAll());
}

// ── POST: record a payment ────────────────────────────────
if ($method === 'POST') {
  $body        = json_decode(file_get_contents('php://input'), true);
  $policy_id   = (int)($body['policy_id'] ?? 0);
  $amount      = (float)($body['amount'] ?? 0);
  $date        = $body['payment_date'] ?? date('Y-m-d');
  $method_type = $body['method'] ?? 'eft';
  $note        = trim($body['note'] ?? '');

  if (!$policy_id || !$amount) json_error('Policy ID and amount are required');

  $pdo->prepare(
    'INSERT INTO payments (policy_id, amount, payment_date, recorded_by, method, note)
     VALUES (?, ?, ?, ?, ?, ?)'
  )->execute([$policy_id, $amount, $date, $auth['sub'], $method_type, $note]);

  // Auto-activate policy if pending
  $pdo->prepare('UPDATE policies SET status="active" WHERE id=? AND status="pending"')
      ->execute([$policy_id]);

  json_out(['message' => 'Payment recorded', 'id' => $pdo->lastInsertId()], 201);
}

// ── DELETE: remove a payment ──────────────────────────────
if ($method === 'DELETE') {
  $id = (int)($_GET['id'] ?? 0);
  if (!$id) json_error('Payment ID required');
  $pdo->prepare('DELETE FROM payments WHERE id=?')->execute([$id]);
  json_out(['message' => 'Payment deleted']);
}

json_error('Method not allowed', 405);
