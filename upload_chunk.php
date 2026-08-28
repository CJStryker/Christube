<?php
require_once 'config.php';

$userId = mediaRequireApiAuthAndCsrf();

$sessionId = trim((string)($_POST['session_id'] ?? ''));
$chunkIndex = (int)($_POST['chunk_index'] ?? -1);
$totalChunks = (int)($_POST['total_chunks'] ?? 0);

if ($sessionId === '' || !isset($_FILES['chunk'])) {
    mediaJson(['ok' => false, 'error' => 'Missing chunk payload'], 422);
}

$session = mediaGetUploadSession($pdo, $sessionId, $userId);
if (!$session) {
    mediaJson(['ok' => false, 'error' => 'Upload session not found'], 404);
}

if (strtotime((string)$session['expires_at']) < time()) {
    mediaJson(['ok' => false, 'error' => 'Upload session expired'], 410);
}

$file = $_FILES['chunk'];
$cfg = mediaConfig();
if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    mediaJson(['ok' => false, 'error' => 'Chunk upload failed'], 422);
}

if ($totalChunks !== max(1, (int)ceil((int)$session['total_bytes'] / $cfg['chunkBytes']))) {
    mediaJson(['ok' => false, 'error' => 'Chunk count does not match upload session'], 422);
}
if ((int)$file['size'] < 1 || (int)$file['size'] > $cfg['chunkBytes'] || ($chunkIndex === $totalChunks - 1 && (int)$file['size'] > (int)$session['total_bytes'])) {
    mediaJson(['ok' => false, 'error' => 'Invalid chunk size'], 422);
}

try {
    mediaAppendChunk($pdo, $session, $chunkIndex, $totalChunks, (string)$file['tmp_name'], (int)$file['size']);
    mediaJson(['ok' => true, 'chunk_index' => $chunkIndex]);
} catch (Throwable $e) {
    mediaJson(['ok' => false, 'error' => $e->getMessage()], 422);
}
