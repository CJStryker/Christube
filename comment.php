<?php
require_once 'config.php';
requireLogin();

$videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$comment = trim($_POST['comment'] ?? '');

if ($videoId < 1 || $comment === '') {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid comment request.'];
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
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Cannot comment on private video you do not own.'];
    header('Location: index.php');
    exit;
}

$insert = $pdo->prepare('INSERT INTO video_comments (video_id, user_id, comment) VALUES (?, ?, ?)');
$insert->execute([$videoId, (int)$_SESSION['user_id'], $comment]);
addExperience($pdo, (int)$_SESSION['user_id'], 5, 'comment_posted');

$_SESSION['flash'] = ['ok' => true, 'msg' => 'Comment posted.'];
header('Location: v.php?s=' . urlencode($video['slug']));
exit;
?>
