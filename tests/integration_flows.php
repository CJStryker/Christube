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
    'playlist_save.php','report_video.php','creator/video_edit.php','creator/comments.php','creator/promotions.php','creator/notifications.php','creator/sponsors.php','creator/monetization.php','admin/economy.php'
];
foreach ($mutationEndpoints as $endpoint) {
    mustContain($root . '/' . $endpoint, 'handleMutation([');
}

mustContain($root . '/config.php', 'ensureEconomySchema($pdo);');
mustContain($root . '/includes/economy.php', 'function awardExp');
mustContain($root . '/includes/economy.php', 'function progressionLevels');
mustContain($root . '/includes/economy.php', 'function creatorEligibility');
mustContain($root . '/includes/economy.php', 'CREATE TABLE IF NOT EXISTS exp_ledger');
mustContain($root . '/includes/economy.php', 'CREATE TABLE IF NOT EXISTS sponsor_campaigns');
mustContain($root . '/includes/economy.php', 'CREATE TABLE IF NOT EXISTS payout_reviews');
mustContain($root . '/includes/recommendation.php', 'function personalizedHomepage');
mustContain($root . '/includes/recommendation.php', 'function personalizedRelated');
mustContain($root . '/includes/recommendation.php', 'function creatorSuggestions');
mustContain($root . '/index.php', 'For You');
mustContain($root . '/search.php', 'Trending Searches');
mustContain($root . '/search.php', 'search_saved_queries');
mustContain($root . '/leaderboard.php', 'Community Leaderboards');
mustContain($root . '/creator/monetization.php', 'Payout Readiness');
mustContain($root . '/creator/sponsors.php', 'Sponsor & Campaign Tools');
mustContain($root . '/admin/economy.php', 'EXP Ledger Controls');
mustContain($root . '/comment.php', 'awardExp(');
mustContain($root . '/follow.php', 'awardExp(');
mustContain($root . '/history_update.php', 'watch_complete');

echo "Phase-7 integration checks passed.\n";
