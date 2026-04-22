<?php
require_once 'config.php';
$userId = mediaRequireApiAuthAndCsrf();
$videoId = (int)($_POST['video_id'] ?? 0);
$position = (float)($_POST['position_seconds'] ?? 0);
$duration = (float)($_POST['duration_seconds'] ?? 0);
if ($videoId < 1) {
    mediaJson(['ok'=>false,'error'=>'Invalid video'],422);
}
$stmt = $pdo->prepare('SELECT id,user_id,visibility,processing_status FROM videos WHERE id=?');
$stmt->execute([$videoId]);
$video = $stmt->fetch();
if (!$video || !canUserAccessVideo($video, $userId)) {
    mediaJson(['ok'=>false,'error'=>'Not accessible'],403);
}
recordWatchProgress($pdo, $videoId, $userId, $position, $duration);
trackProductEvent($pdo, 'watch_progress', $userId, ['video_id'=>$videoId,'position'=>$position]);
mediaJson(['ok'=>true]);
