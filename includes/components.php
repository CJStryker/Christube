<?php
require_once __DIR__ . '/product.php';

function renderSectionHeader(string $title, ?string $link = null): void {
    echo '<div class="section-head"><h2>' . e($title) . '</h2>';
    if ($link) echo '<a href="' . e($link) . '">View all</a>';
    echo '</div>';
}

function renderVideoCard(array $video): void {
    $thumb = (int)($video['thumbnail_asset_id'] ?? 0) > 0 ? mediaAssetUrl((int)$video['thumbnail_asset_id']) : '';
    echo '<article class="panel video-card">';
    if ($thumb) echo '<a href="v.php?s=' . urlencode((string)$video['slug']) . '"><img src="' . e($thumb) . '" alt="thumbnail" class="thumb"></a>';
    echo '<h3><a href="v.php?s=' . urlencode((string)$video['slug']) . '">' . e((string)$video['title']) . '</a></h3>';
    echo '<p class="tiny">@' . e((string)$video['username']) . ' · ' . e((string)$video['uploaded_at']) . '</p>';
    echo '<p class="tiny">👁 ' . (int)($video['views'] ?? 0) . ' · 👍 ' . (int)($video['likes'] ?? 0) . '</p>';
    echo '</article>';
}

function renderRelatedRow(array $video): void {
    $thumb = (int)($video['thumbnail_asset_id'] ?? 0) > 0 ? mediaAssetUrl((int)$video['thumbnail_asset_id']) : '';
    echo '<div class="related-row">';
    if ($thumb) echo '<a href="v.php?s=' . urlencode((string)$video['slug']) . '"><img src="' . e($thumb) . '" alt="thumb" class="related-thumb"></a>';
    echo '<div><a href="v.php?s=' . urlencode((string)$video['slug']) . '">' . e((string)$video['title']) . '</a><div class="tiny">@' . e((string)$video['username']) . ' · 👁 ' . (int)($video['views'] ?? 0) . '</div></div>';
    echo '</div>';
}

function renderCommentItem(array $comment, int $ownerId): void {
    $badge = ((int)$comment['user_id'] === $ownerId) ? ' <span class="tiny">(Creator)</span>' : '';
    echo '<div class="panel" style="margin:10px 0;">';
    echo '<p>' . nl2br(e((string)$comment['comment'])) . '</p>';
    echo '<p class="meta">— <a href="profile.php?u=' . urlencode((string)$comment['username']) . '">' . e((string)$comment['username']) . '</a>' . $badge . ' at ' . e((string)$comment['created_at']) . '</p>';
    echo '</div>';
}
