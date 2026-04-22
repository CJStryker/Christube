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

header('Content-Type: ' . ($asset['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($abs));
header('Cache-Control: public, max-age=3600');
readfile($abs);
exit;
