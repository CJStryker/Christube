<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: index.php');
    exit;
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$visibility = $_POST['visibility'] ?? 'public';

$allowedVisibility = ['public', 'private'];
if ($title === '' || !in_array($visibility, $allowedVisibility, true)) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid form input.'];
    header('Location: index.php');
    exit;
}

if (!isset($_FILES['videoFile']) || $_FILES['videoFile']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Video upload failed.'];
    header('Location: index.php');
    exit;
}

$file = $_FILES['videoFile'];
$maxSize = 250 * 1024 * 1024; // 250MB
if ($file['size'] > $maxSize) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'File is too large. Maximum size is 250MB.'];
    header('Location: index.php');
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExt = ['mp4', 'webm', 'ogg', 'mov'];
if (!in_array($ext, $allowedExt, true)) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid file type. Allowed: mp4, webm, ogg, mov.'];
    header('Location: index.php');
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMime = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
if (!in_array($mime, $allowedMime, true)) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'File does not appear to be a valid video.'];
    header('Location: index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$uploadDir = getUserUploadDir($userId);
$basename = bin2hex(random_bytes(16)) . '.' . $ext;
$absolutePath = $uploadDir . $basename;
$relativePath = 'uploads/videos/user_' . $userId . '/' . $basename;

if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Failed to save uploaded file.'];
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('INSERT INTO videos (user_id, title, description, file_path, visibility) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$userId, $title, $description, $relativePath, $visibility]);

$_SESSION['flash'] = ['ok' => true, 'msg' => 'Video uploaded successfully.'];
header('Location: index.php');
exit;
?>
