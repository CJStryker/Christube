<?php
require_once 'config.php';

$userId = mediaRequireApiAuthAndCsrf();

try {
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $visibility = (string)($_POST['visibility'] ?? 'public');
    $totalBytes = (int)($_POST['total_bytes'] ?? 0);
    $originalName = basename((string)($_POST['original_name'] ?? 'video.mp4'));
    $session = mediaCreateUploadSession($pdo, $userId, $title, $description, $visibility, $totalBytes, $originalName);
    mediaJson(['ok' => true] + $session);
} catch (Throwable $e) {
    mediaJson(['ok' => false, 'error' => $e->getMessage()], 422);
}
