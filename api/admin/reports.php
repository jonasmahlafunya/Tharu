<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth   = require_admin();
$pdo    = db();
$type   = $_GET['type']   ?? 'clients';   // clients | payments | claims
$format = $_GET['format'] ?? 'json';      // json | csv
$month  = $_GET['month']  ?? '';

function to_csv(array $rows): string {
  if (empty($rows)) return '';
  $out = implode(',', array_map('str_putcsv_field', array_keys($rows[0]))) . "\n";
  foreach ($rows as $row) {
    $out .= implode(',', array_map('str_putcsv_field', $row)) . "\n";
  }
  return $out;
}
function str_putcsv_field(mixed $v): string {
  $v = (string)$v;
  if (str_contains($v, ',') || str_contains($v, '"') || str_contains($v, "\n"))
    return '"' . str_replace('"', '""', $v) . '"';
  return $v;
}

$data = [];

if ($type === 'clients') {
  $stmt = $pdo->query(
    'SELECT u.name, u.email, u.phone, u.id_number, u.status,
            p.plan, p.premium, p.join_date, p.status as policy_status,
            (SELECT COUNT(*) FROM members m WHERE m.policy_id=p.id) as members,
            (SELECT COALESCE(SUM(pay.amount),0) FROM payments pay WHERE pay.policy_id=p.id) as total_paid
     FROM users u LEFT JOIN policies p ON p.user_id=u.id
     WHERE u.role="client" ORDER BY u.name'
  );
  $data = $stmt->fetchAll();
}

if ($type === 'payments') {
  $where  = ['1=1'];
  $params = [];
  if ($month) { $where[] = "DATE_FORMAT(pay.payment_date,'%Y-%m') = ?"; $params[] = $month; }

  $stmt = $pdo->prepare(
    'SELECT u.name as client, pol.plan, pay.amount, pay.payment_date, pay.method, pay.note
     FROM payments pay
     JOIN policies pol ON pol.id=pay.policy_id
     JOIN users u ON u.id=pol.user_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY pay.payment_date DESC'
  );
  $stmt->execute($params);
  $data = $stmt->fetchAll();
}

if ($type === 'claims') {
  $stmt = $pdo->query(
    'SELECT c.id, u.name as client, pol.plan, c.claimant_name, c.deceased_name,
            c.date_of_death, c.status, c.admin_note, c.created_at
     FROM claims c
     JOIN policies pol ON pol.id=c.policy_id
     JOIN users u ON u.id=pol.user_id
     ORDER BY c.created_at DESC'
  );
  $data = $stmt->fetchAll();
}

if ($format === 'csv') {
  header('Content-Type: text/csv');
  header("Content-Disposition: attachment; filename=\"tharu_{$type}_" . date('Y-m-d') . ".csv\"");
  // Remove content-type json header set by cors.php
  header_remove('Content-Type');
  header('Content-Type: text/csv');
  echo to_csv($data);
  exit;
}

json_out(['type' => $type, 'count' => count($data), 'data' => $data]);
