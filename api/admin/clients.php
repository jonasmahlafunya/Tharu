<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth   = require_admin();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

// ── GET: list all clients or single client ─────────────────
if ($method === 'GET') {
  if ($id) {
    $stmt = $pdo->prepare(
      'SELECT u.id, u.name, u.email, u.phone, u.id_number, u.status, u.created_at,
              p.id as policy_id, p.plan, p.premium, p.join_date, p.status as policy_status,
              (SELECT COUNT(*) FROM members m WHERE m.policy_id = p.id) as member_count,
              (SELECT SUM(pay.amount) FROM payments pay WHERE pay.policy_id = p.id) as total_paid
       FROM users u
       LEFT JOIN policies p ON p.user_id = u.id
       WHERE u.id = ? AND u.role = "client"'
    );
    $stmt->execute([$id]);
    $client = $stmt->fetch();
    if (!$client) json_error('Client not found', 404);

    // Get members
    $mstmt = $pdo->prepare('SELECT * FROM members WHERE policy_id = ?');
    $mstmt->execute([$client['policy_id']]);
    $client['members'] = $mstmt->fetchAll();

    // Get recent payments
    $pstmt = $pdo->prepare(
      'SELECT * FROM payments WHERE policy_id = ? ORDER BY payment_date DESC LIMIT 12'
    );
    $pstmt->execute([$client['policy_id']]);
    $client['payments'] = $pstmt->fetchAll();

    json_out($client);
  }

  // Search + list
  $search = $_GET['search'] ?? '';
  $status = $_GET['status'] ?? '';
  $plan   = $_GET['plan'] ?? '';

  $where = ['u.role = "client"'];
  $params = [];

  if ($search) {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.id_number LIKE ?)';
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s);
  }
  if ($status) { $where[] = 'u.status = ?'; $params[] = $status; }
  if ($plan)   { $where[] = 'p.plan = ?';   $params[] = $plan; }

  $sql = 'SELECT u.id, u.name, u.email, u.phone, u.id_number, u.status, u.created_at,
                 p.id as policy_id, p.plan, p.premium, p.join_date, p.status as policy_status,
                 (SELECT COUNT(*) FROM members m WHERE m.policy_id = p.id) as member_count
          FROM users u
          LEFT JOIN policies p ON p.user_id = u.id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY u.created_at DESC';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  json_out($stmt->fetchAll());
}

// ── PUT: update client status or plan ─────────────────────
if ($method === 'PUT') {
  if (!$id) json_error('Client ID required');
  $body = json_decode(file_get_contents('php://input'), true);

  if (isset($body['status'])) {
    $pdo->prepare('UPDATE users SET status=? WHERE id=? AND role="client"')
        ->execute([$body['status'], $id]);
  }
  if (isset($body['policy_status'])) {
    $pdo->prepare('UPDATE policies SET status=? WHERE user_id=?')
        ->execute([$body['policy_status'], $id]);
  }
  if (isset($body['plan'])) {
    $premiums = ['A'=>200,'B'=>250,'C'=>300,'D'=>340,'E'=>450];
    $premium  = $premiums[$body['plan']] ?? null;
    if (!$premium) json_error('Invalid plan');
    $pdo->prepare('UPDATE policies SET plan=?, premium=? WHERE user_id=?')
        ->execute([$body['plan'], $premium, $id]);
  }

  json_out(['message' => 'Client updated']);
}

// ── DELETE: remove client ──────────────────────────────────
if ($method === 'DELETE') {
  if (!$id) json_error('Client ID required');
  $pdo->prepare('DELETE FROM users WHERE id=? AND role="client"')->execute([$id]);
  json_out(['message' => 'Client deleted']);
}

json_error('Method not allowed', 405);
