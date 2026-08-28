<?php
require_once 'config.php';

$assetId = (int)($_GET['id'] ?? 0);
if ($assetId < 1) {
    http_response_code(404);
    exit('Asset not found');
}

$stmt = $pdo->prepare("SELECT a.*, v.id AS video_id, v.user_id AS owner_id, v.visibility, v.processing_status
    FROM media_assets a
    LEFT JOIN videos v ON v.playback_asset_id = a.id OR v.thumbnail_asset_id = a.id OR v.source_asset_id = a.id
    WHERE a.id = ?");
$stmt->execute([$assetId]);
$asset = $stmt->fetch();
if (!$asset) {
    http_response_code(404);
    exit('Asset not found');
}

$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$ownerId = isset($asset['owner_id']) ? (int)$asset['owner_id'] : 0;
$isPublic = (($asset['visibility'] ?? 'private') === 'public' && ($asset['processing_status'] ?? '') === MEDIA_STATUS_READY);
if (!$isPublic && $currentUserId !== $ownerId) {
    http_response_code(403);
    exit('Forbidden');
}

$abs = mediaResolveAbsolutePath((string)$asset['storage_path']);
if (!is_file($abs)) {
    http_response_code(404);
    exit('Asset file missing');
}

$mime = (string)($asset['mime_type'] ?: mediaMimeForPath($abs));
$size = (int)filesize($abs);
$start = 0;
$end = max(0, $size - 1);

// Support byte ranges so native browser players can seek and resume old/new files.
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\\d*)-(\\d*)/i', (string)$_SERVER['HTTP_RANGE'], $range)) {
    $start = $range[1] === '' ? max(0, $size - (int)$range[2]) : (int)$range[1];
    $end = $range[2] !== '' ? min($end, (int)$range[2]) : $end;
    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416);
        exit;
    }
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Accept-Ranges: bytes');
} else {
    header('Accept-Ranges: bytes');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . ($end - $start + 1));
header('Cache-Control: public, max-age=3600');
$handle = fopen($abs, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Unable to open asset');
}
fseek($handle, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(1048576, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($handle);
exit;
