<?php
require_once 'config.php';
requireLogin();

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'react',
    'onErrorRedirect' => 'index.php',
], function () use ($pdo): void {
    $videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    $reaction = $_POST['reaction'] ?? '';

    if ($videoId < 1 || !in_array($reaction, ['like', 'dislike'], true)) {
        throw new RuntimeException('Invalid reaction request.');
    }

    $stmt = $pdo->prepare('SELECT id, slug, visibility, user_id, processing_status FROM videos WHERE id = ?');
    $stmt->execute([$videoId]);
    $video = $stmt->fetch();
    if (!$video) {
        throw new RuntimeException('Video not found.');
    }

    if (($video['processing_status'] ?? '') !== MEDIA_STATUS_READY && (int)$video['user_id'] !== (int)$_SESSION['user_id']) {
        throw new RuntimeException('Video is not ready for reactions.');
    }

    if ($video['visibility'] === 'private' && (int)$video['user_id'] !== (int)$_SESSION['user_id']) {
        throw new RuntimeException('Cannot react to private video you do not own.');
    }

    $existingStmt = $pdo->prepare('SELECT reaction FROM video_reactions WHERE video_id = ? AND user_id = ?');
    $existingStmt->execute([$videoId, (int)$_SESSION['user_id']]);
    $oldReaction = $existingStmt->fetchColumn();

    if ($oldReaction === $reaction) {
        $pdo->prepare('DELETE FROM video_reactions WHERE video_id=? AND user_id=?')->execute([$videoId, (int)$_SESSION['user_id']]);
        trackProductEvent($pdo, 'reaction_removed', (int)$_SESSION['user_id'], ['video_id'=>$videoId,'reaction'=>$reaction]);
        setFlash(true, 'Reaction removed.');
        header('Location: v.php?s=' . urlencode($video['slug']));
        exit;
    }

    $upsert = $pdo->prepare("INSERT INTO video_reactions (video_id, user_id, reaction) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)");
    $upsert->execute([$videoId, (int)$_SESSION['user_id'], $reaction]);

    if ($oldReaction !== $reaction) {
        awardExp($pdo, (int)$_SESSION['user_id'], economyConfig()['xp']['reaction_given'], 'reaction_given', 'reacted to video', 'react-' . $videoId . '-' . (int)$_SESSION['user_id'] . '-' . date('YmdHi'), ['reaction'=>$reaction], false, (int)$_SESSION['user_id']);
    }

    trackProductEvent($pdo, 'reaction_saved', (int)$_SESSION['user_id'], ['video_id'=>$videoId,'reaction'=>$reaction]);
    setFlash(true, 'Reaction saved.');
    header('Location: v.php?s=' . urlencode($video['slug']));
    exit;
});
?>
