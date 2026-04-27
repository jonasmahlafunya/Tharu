<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth   = require_admin();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

// policy_id must always be provided as query param
$policy_id = (int)($_GET['policy_id'] ?? 0);
if (!$policy_id) json_error('policy_id is required');

// Verify policy exists
$pol_stmt = $pdo->prepare('SELECT id, plan FROM policies WHERE id = ?');
$pol_stmt->execute([$policy_id]);
$policy = $pol_stmt->fetch();
if (!$policy) json_error('Policy not found', 404);

// ── GET: list members for a policy ────────────────────────
if ($method === 'GET') {
  $stmt = $pdo->prepare('SELECT * FROM members WHERE policy_id = ? ORDER BY id');
  $stmt->execute([$policy_id]);
  json_out($stmt->fetchAll());
}

// ── POST: add a member to a policy ────────────────────────
if ($method === 'POST') {
  $body         = json_decode(file_get_contents('php://input'), true);
  $name         = trim($body['name'] ?? '');
  $id_number    = trim($body['id_number'] ?? '');
  $relationship = trim($body['relationship'] ?? '');
  $dob          = $body['date_of_birth'] ?? null;

  if (!$name) json_error('Member name is required');

  // Check member limit for this plan
  $count_stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM members WHERE policy_id = ?');
  $count_stmt->execute([$policy_id]);
  $count = (int)$count_stmt->fetch()['cnt'];

  $limits = ['A' => 14, 'B' => 14, 'C' => 12, 'D' => 10, 'E' => 10];
  $limit  = $limits[$policy['plan']] ?? 10;

  if ($count >= $limit)
    json_error("Plan {$policy['plan']} allows a maximum of {$limit} members. Current: {$count}.");

  $stmt = $pdo->prepare(
    'INSERT INTO members (policy_id, name, id_number, relationship, date_of_birth)
     VALUES (?, ?, ?, ?, ?)'
  );
  $stmt->execute([$policy_id, $name, $id_number, $relationship, $dob ?: null]);
  json_out(['message' => 'Member added successfully', 'id' => $pdo->lastInsertId()], 201);
}

// ── PUT: update a member ───────────────────────────────────
if ($method === 'PUT') {
  $member_id    = (int)($_GET['member_id'] ?? 0);
  if (!$member_id) json_error('member_id is required');

  $body         = json_decode(file_get_contents('php://input'), true);
  $name         = trim($body['name'] ?? '');
  $id_number    = trim($body['id_number'] ?? '');
  $relationship = trim($body['relationship'] ?? '');
  $dob          = $body['date_of_birth'] ?? null;

  if (!$name) json_error('Member name is required');

  // Confirm member belongs to this policy
  $check = $pdo->prepare('SELECT id FROM members WHERE id = ? AND policy_id = ?');
  $check->execute([$member_id, $policy_id]);
  if (!$check->fetch()) json_error('Member not found on this policy', 404);

  $pdo->prepare(
    'UPDATE members SET name=?, id_number=?, relationship=?, date_of_birth=? WHERE id=?'
  )->execute([$name, $id_number, $relationship, $dob ?: null, $member_id]);

  json_out(['message' => 'Member updated successfully']);
}

// ── DELETE: remove a member ────────────────────────────────
if ($method === 'DELETE') {
  $member_id = (int)($_GET['member_id'] ?? 0);
  if (!$member_id) json_error('member_id is required');

  // Confirm member belongs to this policy
  $check = $pdo->prepare('SELECT relationship FROM members WHERE id = ? AND policy_id = ?');
  $check->execute([$member_id, $policy_id]);
  $member = $check->fetch();

  if (!$member) json_error('Member not found on this policy', 404);
  if ($member['relationship'] === 'Main Member') json_error('The main member cannot be removed');

  $pdo->prepare('DELETE FROM members WHERE id = ?')->execute([$member_id]);
  json_out(['message' => 'Member removed successfully']);
}

json_error('Method not allowed', 405);
