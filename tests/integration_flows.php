<?php
$root = dirname(__DIR__);

function mustContain(string $file, string $needle): void {
    $content = file_get_contents($file);
    if ($content === false || strpos($content, $needle) === false) {
        throw new RuntimeException("Assertion failed: {$file} must contain `{$needle}`");
    }
}

$mutationEndpoints = [
    'register.php',
    'login.php',
    'upload.php',
    'comment.php',
    'react.php',
    'follow.php',
    'promote_video.php',
    'buy_points.php',
    'admin_verify_points.php',
    'update_visibility.php',
    'delete_own_video.php',
    'delete_video.php',
    'edit_profile.php',
];

foreach ($mutationEndpoints as $endpoint) {
    mustContain($root . '/' . $endpoint, 'handleMutation([');
}

// phase-4 media pipeline checks
mustContain($root . '/includes/media.php', 'function mediaCreateUploadSession');
mustContain($root . '/includes/media.php', 'function mediaAppendChunk');
mustContain($root . '/includes/media.php', 'function mediaFinalizeUpload');
mustContain($root . '/includes/media.php', 'CREATE TABLE IF NOT EXISTS media_assets');
mustContain($root . '/includes/media.php', 'CREATE TABLE IF NOT EXISTS media_jobs');
mustContain($root . '/upload_start.php', 'mediaCreateUploadSession');
mustContain($root . '/upload_chunk.php', 'mediaAppendChunk');
mustContain($root . '/upload_finalize.php', 'mediaFinalizeUpload');
mustContain($root . '/media_asset.php', 'Forbidden');
mustContain($root . '/worker_media.php', 'process_video');
mustContain($root . '/cleanup_uploads.php', 'expired sessions');
mustContain($root . '/index.php', 'upload_start.php');
mustContain($root . '/index.php', 'upload_chunk.php');
mustContain($root . '/index.php', 'upload_finalize.php');
mustContain($root . '/view.php', 'MEDIA_STATUS_READY');

echo "Integration checks passed.\n";
