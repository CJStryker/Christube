<?php
$root = dirname(__DIR__);

function mustContain(string $file, string $needle): void {
    $content = file_get_contents($file);
    if ($content === false || strpos($content, $needle) === false) {
        throw new RuntimeException("Assertion failed: {$file} must contain `{$needle}`");
    }
}

$mutationEndpoints = [
    'register.php','login.php','upload.php','comment.php','react.php','follow.php','promote_video.php',
    'buy_points.php','admin_verify_points.php','update_visibility.php','delete_own_video.php','delete_video.php','edit_profile.php',
    'playlist_save.php','report_video.php'
];
foreach ($mutationEndpoints as $endpoint) {
    mustContain($root . '/' . $endpoint, 'handleMutation([');
}

mustContain($root . '/config.php', 'ensureCreatorSchema($pdo);');
mustContain($root . '/includes/creator.php', 'function ensureCreatorSchema');
mustContain($root . '/includes/creator.php', 'function notifyUser');
mustContain($root . '/includes/creator.php', 'function getCreatorAnalytics');
mustContain($root . '/includes/creator.php', 'CREATE TABLE IF NOT EXISTS notifications');
mustContain($root . '/includes/creator.php', 'CREATE TABLE IF NOT EXISTS creator_daily_stats');
mustContain($root . '/creator/dashboard.php', 'Dashboard Overview');
mustContain($root . '/creator/videos.php', 'My Videos');
mustContain($root . '/creator/video_edit.php', 'Edit Video');
mustContain($root . '/creator/analytics.php', 'Analytics Overview');
mustContain($root . '/creator/video_analytics.php', 'Video Analytics');
mustContain($root . '/creator/comments.php', 'Comments Inbox');
mustContain($root . '/creator/promotions.php', 'Promotion History');
mustContain($root . '/creator/notifications.php', 'Notifications');
mustContain($root . '/admin/ops.php', 'Operational Summary');
mustContain($root . '/creator_rollup.php', 'Creator stats rollup completed');
mustContain($root . '/comment.php', 'comment_on_video');
mustContain($root . '/follow.php', 'new_follower');
mustContain($root . '/worker_media.php', 'upload_processed');
mustContain($root . '/worker_media.php', 'upload_failed');

echo "Phase-6 integration checks passed.\n";
