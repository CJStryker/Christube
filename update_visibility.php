<?php
require_once 'config.php';
requireLogin();

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'visibility',
    'onErrorRedirect' => 'index.php',
], function () use ($pdo): void {
    $videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    $visibility = $_POST['visibility'] ?? '';

    if ($videoId < 1 || !validateVisibility($visibility)) {
        throw new RuntimeException('Invalid privacy update request.');
    }

    $stmt = $pdo->prepare('UPDATE videos SET visibility = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$visibility, $videoId, (int)$_SESSION['user_id']]);

    setFlash(true, $stmt->rowCount() > 0 ? 'Video privacy updated.' : 'Video not found or no changes made.');
    header('Location: index.php');
    exit;
});
?>
