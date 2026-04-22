<?php
require_once 'config.php';
requireLogin();

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'comment',
    'onErrorRedirect' => 'index.php',
], function () use ($pdo): void {
    $videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    $comment = trim($_POST['comment'] ?? '');

    if ($videoId < 1 || $comment === '' || !moderationCheck($comment)) {
        throw new RuntimeException('Invalid comment request.');
    }

    $stmt = $pdo->prepare('SELECT id, slug, visibility, user_id, processing_status FROM videos WHERE id = ?');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();
    if (!$video) {
        throw new RuntimeException('Video not found.');
    }

    if (($video['processing_status'] ?? '') !== MEDIA_STATUS_READY && (int)$video['user_id'] !== (int)$_SESSION['user_id']) {
        throw new RuntimeException('Cannot comment on unready video.');
    }

    if ($video['visibility'] === 'private' && (int)$video['user_id'] !== (int)$_SESSION['user_id']) {
        throw new RuntimeException('Cannot comment on private video you do not own.');
    }

    $insert = $pdo->prepare('INSERT INTO video_comments (video_id, user_id, comment) VALUES (?, ?, ?)');
    $insert->execute([$videoId, (int)$_SESSION['user_id'], $comment]);
    addExperience($pdo, (int)$_SESSION['user_id'], 5, 'comment_posted');
    trackProductEvent($pdo, 'comment_posted', (int)$_SESSION['user_id'], ['video_id'=>$videoId]);

    setFlash(true, 'Comment posted.');
    header('Location: v.php?s=' . urlencode($video['slug']));
    exit;
});
?>
