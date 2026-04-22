<?php
require_once 'config.php';

$userId = mediaRequireApiAuthAndCsrf();
$sessionId = trim((string)($_POST['session_id'] ?? ''));
if ($sessionId === '') {
    mediaJson(['ok' => false, 'error' => 'Session id required'], 422);
}

$session = mediaGetUploadSession($pdo, $sessionId, $userId);
if (!$session) {
    mediaJson(['ok' => false, 'error' => 'Upload session not found'], 404);
}

try {
    $videoId = mediaFinalizeUpload($pdo, $session, $userId);
    $slugStmt = $pdo->prepare('SELECT slug FROM videos WHERE id = ? LIMIT 1');
    $slugStmt->execute([$videoId]);
    $slug = (string)$slugStmt->fetchColumn();
    mediaJson(['ok' => true, 'video_id' => $videoId, 'watch_url' => 'v.php?s=' . urlencode($slug)]);
} catch (Throwable $e) {
    $pdo->prepare('UPDATE upload_sessions SET status = ?, error_message = ? WHERE id = ?')->execute([MEDIA_STATUS_FAILED, substr($e->getMessage(), 0, 250), (int)$session['id']]);
    mediaJson(['ok' => false, 'error' => $e->getMessage()], 422);
}
