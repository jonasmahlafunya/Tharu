<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$body = json_decode(file_get_contents('php://input'), true);

$name      = trim($body['name'] ?? '');
$email     = strtolower(trim($body['email'] ?? ''));
$password  = $body['password'] ?? '';
$phone     = trim($body['phone'] ?? '');
$id_number = trim($body['id_number'] ?? '');
$plan      = strtoupper(trim($body['plan'] ?? ''));

// Validate
if (!$name || !$email || !$password || !$plan)
  json_error('Name, email, password and plan are required');
if (!filter_var($email, FILTER_VALIDATE_EMAIL))
  json_error('Invalid email address');
if (strlen($password) < 8)
  json_error('Password must be at least 8 characters');
if (!in_array($plan, ['A','B','C','D','E']))
  json_error('Invalid plan selected');

$pdo = db();

// Check email unique
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) json_error('An account with this email already exists');

$plan_premiums = ['A'=>200,'B'=>250,'C'=>300,'D'=>340,'E'=>450];
$premium = $plan_premiums[$plan];

$pdo->beginTransaction();
try {
  // Create user
  $stmt = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, phone, id_number, role, status)
     VALUES (?, ?, ?, ?, ?, "client", "active")'
  );
  $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $phone, $id_number]);
  $user_id = $pdo->lastInsertId();

  // Create policy
  $stmt = $pdo->prepare(
    'INSERT INTO policies (user_id, plan, premium, join_date, status)
     VALUES (?, ?, ?, CURDATE(), "pending")'
  );
  $stmt->execute([$user_id, $plan, $premium]);

  // Add main member automatically
  $stmt = $pdo->prepare(
    'INSERT INTO members (policy_id, name, id_number, relationship)
     VALUES (?, ?, ?, "Main Member")'
  );
  $stmt->execute([$pdo->lastInsertId(), $name, $id_number]);

  $pdo->commit();
} catch (Exception $e) {
  $pdo->rollBack();
  json_error('Registration failed. Please try again.', 500);
}

$user = ['id' => $user_id, 'name' => $name, 'role' => 'client'];
json_out([
  'message' => 'Account created successfully',
  'token'   => create_token($user),
  'user'    => ['id' => $user_id, 'name' => $name, 'email' => $email, 'role' => 'client'],
], 201);
