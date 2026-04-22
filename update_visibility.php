<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$visibility = $_POST['visibility'] ?? '';
$allowed = ['public', 'private'];

if ($videoId < 1 || !in_array($visibility, $allowed, true)) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid privacy update request.'];
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('UPDATE videos SET visibility = ? WHERE id = ? AND user_id = ?');
$stmt->execute([$visibility, $videoId, (int)$_SESSION['user_id']]);

if ($stmt->rowCount() > 0) {
    $_SESSION['flash'] = ['ok' => true, 'msg' => 'Video privacy updated.'];
} else {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Video not found or no changes made.'];
}

header('Location: index.php');
exit;
?>
