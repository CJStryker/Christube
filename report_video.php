<?php
require_once 'config.php';
requireLogin();
handleMutation([
  'requireAuth'=>true,
  'rateBucket'=>'report_video',
  'onErrorRedirect'=>'index.php',
], function() use ($pdo): void {
    $videoId = (int)($_POST['video_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? 'other'));
    $details = trim((string)($_POST['details'] ?? ''));
    $allowed = ['spam','abuse','copyright','other'];
    if ($videoId < 1 || !in_array($reason, $allowed, true)) {
        throw new RuntimeException('Invalid report.');
    }
    $pdo->prepare('INSERT INTO video_reports (video_id, user_id, reason, details) VALUES (?, ?, ?, ?)')
        ->execute([$videoId, (int)$_SESSION['user_id'], $reason, $details]);
    trackProductEvent($pdo, 'video_reported', (int)$_SESSION['user_id'], ['video_id'=>$videoId,'reason'=>$reason]);
    setFlash(true, 'Report submitted.');
    $slug = (string)$pdo->query('SELECT slug FROM videos WHERE id=' . (int)$videoId)->fetchColumn();
    header('Location: v.php?s=' . urlencode($slug));
    exit;
});
