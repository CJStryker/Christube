<?php
require_once 'config.php';
requireLogin();

function toBytes(string $value): int {
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower($value[strlen($value) - 1]);
    $number = (float)$value;

    switch ($unit) {
        case 'g':
            return (int)($number * 1024 * 1024 * 1024);
        case 'm':
            return (int)($number * 1024 * 1024);
        case 'k':
            return (int)($number * 1024);
        default:
            return (int)$number;
    }
}


function generateUniqueSlug(PDO $pdo): string {
    $stmt = $pdo->prepare('SELECT id FROM videos WHERE slug = ? LIMIT 1');
    for ($i = 0; $i < 10; $i++) {
        $slug = bin2hex(random_bytes(4));
        $stmt->execute([$slug]);
        if (!$stmt->fetch()) {
            return $slug;
        }
    }

    return bin2hex(random_bytes(6));
}

function failUpload(string $message): void {
    $_SESSION['flash'] = ['ok' => false, 'msg' => $message];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
$postMaxSize = toBytes((string)ini_get('post_max_size'));
if ($postMaxSize > 0 && $contentLength > $postMaxSize) {
    failUpload('Upload failed before PHP could read the file: active post_max_size is ' . ini_get('post_max_size') . '. Increase server/PHP limits (php.ini, FPM pool, or webserver config) to at least 160M post and 150M upload.');
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$visibility = $_POST['visibility'] ?? 'public';

$allowedVisibility = ['public', 'private'];
if ($title === '' || !in_array($visibility, $allowedVisibility, true)) {
    failUpload('Invalid form input. Please include title and a valid privacy setting.');
}

if (!isset($_FILES['videoFile'])) {
    failUpload('No video file was received by the server. This usually means the file exceeded server upload limits.');
}

$file = $_FILES['videoFile'];
if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Upload failed: file exceeds active upload_max_filesize (' . ini_get('upload_max_filesize') . '). Increase server/PHP limits to at least 150M.',
        UPLOAD_ERR_FORM_SIZE => 'Upload failed: file exceeds HTML form MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL => 'Upload failed: file was only partially uploaded. This can happen with unstable/slow Tor circuits. Try again.',
        UPLOAD_ERR_NO_FILE => 'Upload failed: no file selected.',
        UPLOAD_ERR_NO_TMP_DIR => 'Upload failed: server temporary folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Upload failed: server could not write uploaded data to disk.',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by a server extension.',
    ];
    $errorCode = (int)($file['error'] ?? -1);
    failUpload($errors[$errorCode] ?? ('Upload failed with unknown error code: ' . $errorCode));
}

$maxAppSize = 150 * 1024 * 1024; // 150MB app-level cap
if ($file['size'] > $maxAppSize) {
    failUpload('File is too large for this app (max 150MB).');
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExt = ['mp4', 'webm', 'ogg', 'mov'];
if (!in_array($ext, $allowedExt, true)) {
    failUpload('Invalid file type. Allowed: mp4, webm, ogg, mov.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMime = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
if (!in_array($mime, $allowedMime, true)) {
    failUpload('File does not appear to be a valid video (detected MIME: ' . htmlspecialchars((string)$mime) . ').');
}

$userId = (int)$_SESSION['user_id'];
$uploadDir = getUserUploadDir($userId);
if (!is_dir($uploadDir)) {
    failUpload('Upload directory is missing: ' . $uploadDir);
}

if (!is_writable($uploadDir)) {
    failUpload('Upload directory is not writable by the web server user: ' . $uploadDir . '. Fix folder ownership/permissions.');
}

$basename = bin2hex(random_bytes(16)) . '.' . $ext;
$absolutePath = $uploadDir . $basename;
$relativePath = 'uploads/videos/user_' . $userId . '/' . $basename;

if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
    $lastError = error_get_last();
    $reason = $lastError['message'] ?? 'unknown filesystem error';
    failUpload('Failed to save uploaded file on the server. Reason: ' . $reason);
}

$slug = generateUniqueSlug($pdo);
$stmt = $pdo->prepare('INSERT INTO videos (user_id, slug, title, description, file_path, visibility) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([$userId, $slug, $title, $description, $relativePath, $visibility]);

$_SESSION['flash'] = ['ok' => true, 'msg' => 'Video uploaded successfully.'];
header('Location: index.php');
exit;
?>
