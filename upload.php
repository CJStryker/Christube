<?php
require_once 'config.php';
requireLogin();

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'upload',
    'rateLimit' => 25,
    'rateWindow' => 300,
    'onErrorRedirect' => 'index.php',
], function () use ($pdo): void {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';

    if (!isset($_FILES['videoFile']) || (int)($_FILES['videoFile']['error'] ?? -1) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Video upload failed.');
    }

    $file = $_FILES['videoFile'];
    $size = (int)$file['size'];
    $userId = (int)$_SESSION['user_id'];

    $sessionInfo = mediaCreateUploadSession(
        $pdo,
        $userId,
        $title,
        $description,
        (string)$visibility,
        $size,
        (string)($file['name'] ?? 'upload.mp4')
    );

    $session = mediaGetUploadSession($pdo, $sessionInfo['session_id'], $userId);
    if (!$session) {
        throw new RuntimeException('Could not initialize upload session.');
    }

    mediaAppendChunk($pdo, $session, 0, 1, (string)$file['tmp_name'], $size);
    $videoId = mediaFinalizeUpload($pdo, $session, $userId);

    addExperience($pdo, $userId, 25, 'video_upload');
    setFlash(true, 'Upload received. Processing has started for video #' . $videoId . '.');
    header('Location: uploads/index.php');
    exit;
});
