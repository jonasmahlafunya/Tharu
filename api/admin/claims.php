<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth   = require_admin();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

// ── GET: all claims or single claim with documents ─────────
if ($method === 'GET') {
  if ($id) {
    $stmt = $pdo->prepare(
      'SELECT c.*, u.name as client_name, u.email as client_email, u.phone as client_phone,
              pol.plan, pol.premium
       FROM claims c
       JOIN policies pol ON pol.id = c.policy_id
       JOIN users u ON u.id = pol.user_id
       WHERE c.id = ?'
    );
    $stmt->execute([$id]);
    $claim = $stmt->fetch();
    if (!$claim) json_error('Claim not found', 404);

    $dstmt = $pdo->prepare('SELECT * FROM claim_documents WHERE claim_id = ?');
    $dstmt->execute([$id]);
    $claim['documents'] = $dstmt->fetchAll();

    json_out($claim);
  }

  $status = $_GET['status'] ?? '';
  $where  = ['1=1'];
  $params = [];
  if ($status) { $where[] = 'c.status = ?'; $params[] = $status; }

  $stmt = $pdo->prepare(
    'SELECT c.id, c.claimant_name, c.deceased_name, c.date_of_death, c.status,
            c.created_at, c.updated_at, u.name as client_name, pol.plan,
            (SELECT COUNT(*) FROM claim_documents cd WHERE cd.claim_id = c.id) as doc_count
     FROM claims c
     JOIN policies pol ON pol.id = c.policy_id
     JOIN users u ON u.id = pol.user_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY c.created_at DESC'
  );
  $stmt->execute($params);
  json_out($stmt->fetchAll());
}

// ── PUT: update claim status + admin note ─────────────────
if ($method === 'PUT') {
  if (!$id) json_error('Claim ID required');
  $body       = json_decode(file_get_contents('php://input'), true);
  $status     = $body['status'] ?? null;
  $admin_note = trim($body['admin_note'] ?? '');

  $valid = ['pending','under_review','approved','rejected'];
  if ($status && !in_array($status, $valid)) json_error('Invalid status');

  $pdo->prepare(
    'UPDATE claims SET status=?, admin_note=?, reviewed_by=?, reviewed_at=NOW()
     WHERE id=?'
  )->execute([$status, $admin_note, $auth['sub'], $id]);

  // Notify client
  $claimStmt = $pdo->prepare(
    'SELECT pol.user_id FROM claims c JOIN policies pol ON pol.id = c.policy_id WHERE c.id = ?'
  );
  $claimStmt->execute([$id]);
  $row = $claimStmt->fetch();
  if ($row) {
    $status_msg = [
      'pending'      => 'Your claim has been received and is pending review.',
      'under_review' => 'Your claim is now under review by our team.',
      'approved'     => 'Your claim has been approved. We will contact you shortly.',
      'rejected'     => 'Your claim has been reviewed. Please contact us for more information.',
    ];
    $pdo->prepare(
      'INSERT INTO notifications (user_id, message, sent_by) VALUES (?, ?, ?)'
    )->execute([$row['user_id'], $status_msg[$status] ?? "Claim status updated to: $status", $auth['sub']]);
  }

  json_out(['message' => 'Claim updated']);
}

json_error('Method not allowed', 405);
