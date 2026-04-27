<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth    = require_auth();
$user_id = $auth['sub'];
$pdo     = db();
$method  = $_SERVER['REQUEST_METHOD'];

function get_policy_id(PDO $pdo, int $user_id): int {
  $stmt = $pdo->prepare('SELECT id FROM policies WHERE user_id = ? LIMIT 1');
  $stmt->execute([$user_id]);
  $row = $stmt->fetch();
  if (!$row) json_error('No policy found', 404);
  return (int)$row['id'];
}

$policy_id = get_policy_id($pdo, $user_id);

// ── GET: list all claims for this policy ───────────────────
if ($method === 'GET') {
  $stmt = $pdo->prepare(
    'SELECT c.*, GROUP_CONCAT(cd.file_name) as documents
     FROM claims c
     LEFT JOIN claim_documents cd ON cd.claim_id = c.id
     WHERE c.policy_id = ?
     GROUP BY c.id
     ORDER BY c.created_at DESC'
  );
  $stmt->execute([$policy_id]);
  $claims = $stmt->fetchAll();
  foreach ($claims as &$c) {
    $c['documents'] = $c['documents'] ? explode(',', $c['documents']) : [];
  }
  json_out($claims);
}

// ── POST: submit new claim (multipart/form-data) ───────────
if ($method === 'POST') {
  $claimant_name  = trim($_POST['claimant_name'] ?? '');
  $deceased_name  = trim($_POST['deceased_name'] ?? '');
  $date_of_death  = $_POST['date_of_death'] ?? '';
  $description    = trim($_POST['description'] ?? '');

  if (!$claimant_name || !$deceased_name || !$date_of_death)
    json_error('Claimant name, deceased name, and date of death are required');

  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare(
      'INSERT INTO claims (policy_id, claimant_name, deceased_name, date_of_death, description, status)
       VALUES (?, ?, ?, ?, ?, "pending")'
    );
    $stmt->execute([$policy_id, $claimant_name, $deceased_name, $date_of_death, $description]);
    $claim_id = $pdo->lastInsertId();

    // Handle file uploads
    if (!empty($_FILES['documents'])) {
      $files = $_FILES['documents'];
      $count = is_array($files['name']) ? count($files['name']) : 1;

      for ($i = 0; $i < $count; $i++) {
        $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $name = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
        $type = is_array($files['type'])     ? $files['type'][$i]     : $files['type'];
        $size = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];

        if ($size > MAX_FILE_SIZE) json_error("File $name exceeds 5MB limit");
        if (!in_array($type, ALLOWED_TYPES)) json_error("File type not allowed: $type");

        $ext      = pathinfo($name, PATHINFO_EXTENSION);
        $filename = "claim_{$claim_id}_" . uniqid() . ".$ext";
        $dest     = UPLOAD_DIR . $filename;

        if (!move_uploaded_file($tmp, $dest))
          throw new Exception("Failed to save file $name");

        $pdo->prepare(
          'INSERT INTO claim_documents (claim_id, file_name, file_path, file_type)
           VALUES (?, ?, ?, ?)'
        )->execute([$claim_id, $name, $filename, $type]);
      }
    }

    $pdo->commit();
    json_out(['message' => 'Claim submitted successfully', 'claim_id' => $claim_id], 201);

  } catch (Exception $e) {
    $pdo->rollBack();
    json_error('Failed to submit claim: ' . $e->getMessage(), 500);
  }
}

json_error('Method not allowed', 405);
