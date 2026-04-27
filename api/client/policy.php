<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = require_auth();
$user_id = $auth['sub'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $stmt = $pdo->prepare(
    'SELECT p.*, u.name, u.email, u.phone, u.id_number
     FROM policies p
     JOIN users u ON u.id = p.user_id
     WHERE p.user_id = ?
     LIMIT 1'
  );
  $stmt->execute([$user_id]);
  $policy = $stmt->fetch();
  if (!$policy) json_error('No policy found', 404);

  $plan_details = [
    'A' => ['casket'=>'TFS Standard Casket',      'members'=>14, 'chairs'=>100, 'grocery'=>2000, 'payout'=>2500],
    'B' => ['casket'=>'RedWood Halfview Casket',   'members'=>14, 'chairs'=>120, 'grocery'=>2000, 'payout'=>3000],
    'C' => ['casket'=>'Kiat Kinston Casket',       'members'=>12, 'chairs'=>150, 'grocery'=>2500, 'payout'=>3300],
    'D' => ['casket'=>'Fantasy Casket',            'members'=>10, 'chairs'=>150, 'grocery'=>2500, 'payout'=>3600],
    'E' => ['casket'=>'Dark Stain Portable Casket','members'=>10, 'chairs'=>200, 'grocery'=>2000, 'payout'=>4000],
  ];

  $policy['plan_details'] = $plan_details[$policy['plan']] ?? [];
  json_out($policy);
}

json_error('Method not allowed', 405);
