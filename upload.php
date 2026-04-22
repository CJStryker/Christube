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
    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
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

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'upload',
    'rateLimit' => 25,
    'rateWindow' => 300,
    'onErrorRedirect' => 'index.php',
], function () use ($pdo): void {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxSize = toBytes((string)ini_get('post_max_size'));
    if ($postMaxSize > 0 && $contentLength > $postMaxSize) {
        throw new RuntimeException('Upload exceeded server POST limit.');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';
    if ($title === '' || !validateVisibility($visibility)) {
        throw new RuntimeException('Invalid form input.');
    }
    if (!isset($_FILES['videoFile']) || (int)($_FILES['videoFile']['error'] ?? -1) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Video upload failed.');
    }

    $file = $_FILES['videoFile'];
    if ((int)$file['size'] > MAX_VIDEO_UPLOAD_BYTES) {
        throw new RuntimeException('File exceeds max 150MB limit.');
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp4', 'webm', 'ogg', 'mov'], true)) {
        throw new RuntimeException('Invalid file type.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, (string)$file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'], true)) {
        throw new RuntimeException('Invalid video MIME type.');
    }

    $userId = (int)$_SESSION['user_id'];
    $uploadDir = getUserUploadDir($userId);
    $basename = bin2hex(random_bytes(16)) . '.' . $ext;
    $absolutePath = $uploadDir . $basename;
    $relativePath = 'uploads/videos/user_' . $userId . '/' . $basename;
    if (!move_uploaded_file((string)$file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    $slug = generateUniqueSlug($pdo);
    $stmt = $pdo->prepare('INSERT INTO videos (user_id, slug, title, description, file_path, visibility) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $slug, $title, $description, $relativePath, $visibility]);
    addExperience($pdo, $userId, 25, 'video_upload');

    setFlash(true, 'Video uploaded successfully.');
    header('Location: index.php');
    exit;
});
?>
