<?php
require_once 'config.php';
$userId = mediaRequireApiAuthAndCsrf();
$videoId = (int)($_POST['video_id'] ?? 0);
$position = (float)($_POST['position_seconds'] ?? 0);
$duration = (float)($_POST['duration_seconds'] ?? 0);
if ($position < 0 || $duration < 0 || $position > 86400 || $duration > 86400) {
    mediaJson(['ok'=>false,'error'=>'Invalid progress values'],422);
}
if ($duration > 0) $position = min($position, $duration);
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
awardExp($pdo, $userId, economyConfig()['xp']['watch_start'], 'watch_start', 'watched video', 'watch-start-' . $videoId . '-' . $userId . '-' . date('YmdH'), ['video_id'=>$videoId], false, $userId);
if ($duration > 0 && $position / $duration >= 0.9) {
    awardExp($pdo, $userId, economyConfig()['xp']['watch_complete'], 'watch_complete', 'completed video', 'watch-complete-' . $videoId . '-' . $userId . '-' . date('Ymd'), ['video_id'=>$videoId], false, $userId);
    milestoneAward($pdo, $userId);
}
mediaJson(['ok'=>true]);
