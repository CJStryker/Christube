<?php
require_once __DIR__ . '/../config.php';

function renderTopbar(string $title, array $links = []): void {
    echo '<div class="topbar"><strong>' . e($title) . '</strong><div>';
    foreach ($links as $href => $label) {
        echo '<a href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '</div></div>';
}

function renderFlashBlock(): void {
    $flash = pullFlash();
    if ($flash) {
        echo '<div class="flash">' . e((string)$flash['msg']) . '</div>';
    }
}

function renderPromotedSidebar(PDO $pdo, int $limit = 8, string $prefix = ''): void {
    $ads = getActiveVideoAds($pdo, $limit);
    echo '<aside class="left-ads"><div class="panel"><h3>Promoted Videos</h3>';
    if (!$ads) {
        echo '<p class="muted">No active promotions yet.</p>';
    } else {
        foreach ($ads as $ad) {
            echo '<div style="margin-bottom:10px; border-bottom:1px solid #7a0000; padding-bottom:8px;">';
            echo '<a href="' . e($prefix . 'v.php?s=' . urlencode($ad['slug'])) . '">' . e($ad['title']) . '</a>';
            echo '<div class="tiny">by ' . e($ad['username']) . '</div>';
            echo '</div>';
        }
    }
    echo '</div></aside>';
}
