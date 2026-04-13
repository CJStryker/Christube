<?php
require_once 'config.php';
requireLogin();

if (($_SESSION['username'] ?? '') !== 'Zesty') {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Only Zesty can delete videos.'];
    header('Location: index.php');
    exit;
}

$videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
if ($videoId < 1) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid delete request.'];
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT file_path FROM videos WHERE id = ?');
$stmt->execute([$videoId]);
$video = $stmt->fetch();
if (!$video) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Video not found.'];
    header('Location: index.php');
    exit;
}

$delete = $pdo->prepare('DELETE FROM videos WHERE id = ?');
$delete->execute([$videoId]);

$absolute = __DIR__ . '/' . ltrim((string)$video['file_path'], '/');
if (is_file($absolute)) {
    @unlink($absolute);
}

$_SESSION['flash'] = ['ok' => true, 'msg' => 'Video deleted.'];
header('Location: index.php');
exit;
?>
