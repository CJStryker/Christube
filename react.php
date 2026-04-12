<?php
require_once 'config.php';
requireLogin();

$videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$reaction = $_POST['reaction'] ?? '';
if ($videoId < 1 || !in_array($reaction, ['like', 'dislike'], true)) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid reaction request.'];
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, slug, visibility, user_id FROM videos WHERE id = ?');
$stmt->execute([$videoId]);
$video = $stmt->fetch();
if (!$video) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Video not found.'];
    header('Location: index.php');
    exit;
}

if ($video['visibility'] === 'private' && (int)$video['user_id'] !== (int)$_SESSION['user_id']) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Cannot react to private video you do not own.'];
    header('Location: index.php');
    exit;
}

$upsert = $pdo->prepare(
    "INSERT INTO video_reactions (video_id, user_id, reaction) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)"
);
$upsert->execute([$videoId, (int)$_SESSION['user_id'], $reaction]);

$_SESSION['flash'] = ['ok' => true, 'msg' => 'Reaction saved.'];
header('Location: v.php?s=' . urlencode($video['slug']));
exit;
?>
