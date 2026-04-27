<?php
require_once __DIR__ . '/../../includes/cors.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Admin only — clients cannot fetch arbitrary documents
$auth = require_admin();

$doc_id = (int)($_GET['id'] ?? 0);
if (!$doc_id) json_error('Document ID required');

$pdo = db();

// Fetch document record — join through claims → policies to verify it exists
$stmt = $pdo->prepare(
  'SELECT cd.file_path, cd.file_name, cd.file_type
   FROM claim_documents cd
   JOIN claims c ON c.id = cd.claim_id
   JOIN policies p ON p.id = c.policy_id
   WHERE cd.id = ?'
);
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) json_error('Document not found', 404);

$full_path = UPLOAD_DIR . $doc['file_path'];

if (!file_exists($full_path)) {
  json_error('File not found on server. It may have been moved or deleted.', 404);
}

// ── Stream the file ──────────────────────────────────────────
// Clear any JSON content-type set by cors.php
header_remove('Content-Type');
header_remove('Access-Control-Allow-Origin');

$mime = $doc['file_type'] ?: mime_content_type($full_path) ?: 'application/octet-stream';
$size = filesize($full_path);
$name = basename($doc['file_name']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: inline; filename="' . addslashes($name) . '"');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization');

readfile($full_path);
exit;
