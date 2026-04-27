<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth    = require_auth();
$user_id = $auth['sub'];
$pdo     = db();

// Get policy_id for this user
function get_policy_id(PDO $pdo, int $user_id): int {
  $stmt = $pdo->prepare('SELECT id FROM policies WHERE user_id = ? LIMIT 1');
  $stmt->execute([$user_id]);
  $row = $stmt->fetch();
  if (!$row) json_error('No policy found', 404);
  return (int)$row['id'];
}

$method    = $_SERVER['REQUEST_METHOD'];
$policy_id = get_policy_id($pdo, $user_id);

if ($method === 'GET') {
  $stmt = $pdo->prepare('SELECT * FROM members WHERE policy_id = ? ORDER BY id');
  $stmt->execute([$policy_id]);
  json_out($stmt->fetchAll());
}

if ($method === 'POST') {
  $body        = json_decode(file_get_contents('php://input'), true);
  $name        = trim($body['name'] ?? '');
  $id_number   = trim($body['id_number'] ?? '');
  $relationship = trim($body['relationship'] ?? '');
  $dob         = $body['date_of_birth'] ?? null;

  if (!$name) json_error('Member name is required');

  // Check member limit
  $count_stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM members WHERE policy_id = ?');
  $count_stmt->execute([$policy_id]);
  $count = (int)$count_stmt->fetch()['cnt'];

  $policy_stmt = $pdo->prepare('SELECT plan FROM policies WHERE id = ?');
  $policy_stmt->execute([$policy_id]);
  $policy = $policy_stmt->fetch();
  $limits = ['A'=>14,'B'=>14,'C'=>12,'D'=>10,'E'=>10];
  $limit  = $limits[$policy['plan']] ?? 10;

  if ($count >= $limit)
    json_error("Your Plan {$policy['plan']} allows a maximum of $limit members");

  $stmt = $pdo->prepare(
    'INSERT INTO members (policy_id, name, id_number, relationship, date_of_birth)
     VALUES (?, ?, ?, ?, ?)'
  );
  $stmt->execute([$policy_id, $name, $id_number, $relationship, $dob ?: null]);
  json_out(['message' => 'Member added', 'id' => $pdo->lastInsertId()], 201);
}

if ($method === 'DELETE') {
  $id = (int)($_GET['id'] ?? 0);
  if (!$id) json_error('Member ID required');

  // Ensure member belongs to this policy
  $stmt = $pdo->prepare('SELECT relationship FROM members WHERE id = ? AND policy_id = ?');
  $stmt->execute([$id, $policy_id]);
  $member = $stmt->fetch();
  if (!$member) json_error('Member not found', 404);
  if ($member['relationship'] === 'Main Member') json_error('Cannot remove the main member');

  $pdo->prepare('DELETE FROM members WHERE id = ?')->execute([$id]);
  json_out(['message' => 'Member removed']);
}

json_error('Method not allowed', 405);
