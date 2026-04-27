<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth   = require_admin();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // Stats overview
  $stats = [];

  $stats['total_clients']  = $pdo->query('SELECT COUNT(*) FROM users WHERE role="client"')->fetchColumn();
  $stats['active_policies'] = $pdo->query('SELECT COUNT(*) FROM policies WHERE status="active"')->fetchColumn();
  $stats['pending_claims'] = $pdo->query('SELECT COUNT(*) FROM claims WHERE status="pending"')->fetchColumn();
  $stats['this_month']     = $pdo->query(
    'SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())'
  )->fetchColumn();
  $stats['overdue_clients'] = $pdo->query(
    'SELECT COUNT(DISTINCT pol.user_id) FROM policies pol
     WHERE pol.status="active"
     AND pol.id NOT IN (
       SELECT policy_id FROM payments
       WHERE MONTH(payment_date)=MONTH(NOW()) AND YEAR(payment_date)=YEAR(NOW())
     )'
  )->fetchColumn();

  // Recent activity
  $recent_claims = $pdo->query(
    'SELECT c.id, c.deceased_name, c.status, c.created_at, u.name as client_name
     FROM claims c JOIN policies p ON p.id=c.policy_id JOIN users u ON u.id=p.user_id
     ORDER BY c.created_at DESC LIMIT 5'
  )->fetchAll();

  $recent_payments = $pdo->query(
    'SELECT pay.amount, pay.payment_date, u.name as client_name, pol.plan
     FROM payments pay JOIN policies pol ON pol.id=pay.policy_id JOIN users u ON u.id=pol.user_id
     ORDER BY pay.payment_date DESC LIMIT 5'
  )->fetchAll();

  json_out([
    'stats'           => $stats,
    'recent_claims'   => $recent_claims,
    'recent_payments' => $recent_payments,
  ]);
}

json_error('Method not allowed', 405);
