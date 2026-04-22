<?php
require_once 'config.php';
requireLogin();

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'delete_own',
    'onErrorRedirect' => 'uploads/index.php',
], function () use ($pdo): void {
    $videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    $userId = (int)$_SESSION['user_id'];

    if ($videoId < 1) {
        throw new RuntimeException('Invalid delete request.');
    }

    $stmt = $pdo->prepare('SELECT file_path FROM videos WHERE id = ? AND user_id = ?');
    $stmt->execute([$videoId, $userId]);
    $video = $stmt->fetch();
    if (!$video) {
        throw new RuntimeException('Video not found or not owned by you.');
    }

    $pdo->prepare('DELETE FROM videos WHERE id = ? AND user_id = ?')->execute([$videoId, $userId]);
    $absolute = __DIR__ . '/' . ltrim((string)$video['file_path'], '/');
    if (is_file($absolute)) {
        @unlink($absolute);
    }

    setFlash(true, 'Your video was deleted.');
    header('Location: uploads/index.php');
    exit;
});
?>
