<?php
require_once 'config.php';
requireLogin();

$videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$userId = (int)$_SESSION['user_id'];

if ($videoId < 1) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid delete request.'];
    header('Location: uploads/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT file_path FROM videos WHERE id = ? AND user_id = ?');
$stmt->execute([$videoId, $userId]);
$video = $stmt->fetch();
if (!$video) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Video not found or not owned by you.'];
    header('Location: uploads/index.php');
    exit;
}

$pdo->prepare('DELETE FROM videos WHERE id = ? AND user_id = ?')->execute([$videoId, $userId]);
$absolute = __DIR__ . '/' . ltrim((string)$video['file_path'], '/');
if (is_file($absolute)) {
    @unlink($absolute);
}

$_SESSION['flash'] = ['ok' => true, 'msg' => 'Your video was deleted.'];
header('Location: uploads/index.php');
exit;
?>
