<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth    = require_auth();
$user_id = $auth['sub'];
$pdo     = db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('Method not allowed', 405);

$stmt = $pdo->prepare(
  'SELECT p.*, u.name as recorded_by_name
   FROM payments p
   JOIN policies pol ON pol.id = p.policy_id
   JOIN users u ON u.id = p.recorded_by
   WHERE pol.user_id = ?
   ORDER BY p.payment_date DESC'
);
$stmt->execute([$user_id]);
json_out($stmt->fetchAll());
