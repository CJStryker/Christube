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

mustContain($root . '/includes/media.php', 'function mediaFinalizeUpload');
mustContain($root . '/includes/product.php', 'function getTrendingVideos');
mustContain($root . '/includes/product.php', 'function getRelatedVideos');
mustContain($root . '/includes/product.php', 'function searchVideosAndChannels');
mustContain($root . '/includes/product.php', 'function recordWatchProgress');
mustContain($root . '/includes/product.php', 'CREATE TABLE IF NOT EXISTS playlists');
mustContain($root . '/includes/product.php', 'CREATE TABLE IF NOT EXISTS watch_history');
mustContain($root . '/includes/components.php', 'function renderVideoCard');
mustContain($root . '/view.php', 'Related Videos');
mustContain($root . '/view.php', 'history_update.php');
mustContain($root . '/view.php', 'report_video.php');
mustContain($root . '/index.php', 'From Subscriptions');
mustContain($root . '/index.php', 'Continue Watching');
mustContain($root . '/search.php', 'searchVideosAndChannels');
mustContain($root . '/trending.php', 'getTrendingVideos');
mustContain($root . '/subscriptions.php', 'Subscriptions Feed');
mustContain($root . '/history.php', 'Watch History');
mustContain($root . '/playlist.php', 'playlist_videos');

echo "Phase-5 integration checks passed.\n";
