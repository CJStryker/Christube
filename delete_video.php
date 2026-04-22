<?php
require_once 'config.php';
requireLogin();

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'delete_any',
    'onErrorRedirect' => 'index.php',
], function () use ($pdo): void {
    if (($_SESSION['username'] ?? '') !== 'Zesty') {
        throw new RuntimeException('Only Zesty can delete videos.');
    }

    $videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    if ($videoId < 1) {
        throw new RuntimeException('Invalid delete request.');
    }

    $stmt = $pdo->prepare('SELECT file_path FROM videos WHERE id = ?');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();
    if (!$video) {
        throw new RuntimeException('Video not found.');
    }

    $pdo->prepare('DELETE FROM videos WHERE id = ?')->execute([$videoId]);
    $absolute = __DIR__ . '/' . ltrim((string)$video['file_path'], '/');
    if (is_file($absolute)) {
        @unlink($absolute);
    }

    setFlash(true, 'Video deleted.');
    header('Location: index.php');
    exit;
});
?>
