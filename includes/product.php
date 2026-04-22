<?php

function readyVisibilityClause(string $alias = 'v'): string {
    return "{$alias}.processing_status = 'ready' AND {$alias}.visibility = 'public'";
}

function trackProductEvent(PDO $pdo, string $eventType, ?int $userId, array $payload = []): void {
    $pdo->prepare('INSERT INTO product_events (event_type, user_id, payload_json) VALUES (?, ?, ?)')
        ->execute([$eventType, $userId, json_encode($payload, JSON_UNESCAPED_SLASHES)]);
}

function fetchDiscoveryRows(PDO $pdo, string $orderBy, int $limit = 12, array $params = []): array {
    $sql = "SELECT v.id, v.slug, v.title, v.description, v.uploaded_at, v.duration_seconds, v.width, v.height,
                u.username, u.id AS creator_id,
                COALESCE(vc.views, 0) AS views,
                SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes,
                ta.id AS thumbnail_asset_id
            FROM videos v
            INNER JOIN users u ON u.id=v.user_id
            LEFT JOIN video_reactions vr ON vr.video_id=v.id
            LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id
            LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
            WHERE " . readyVisibilityClause('v') . "
            GROUP BY v.id
            ORDER BY {$orderBy}
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getLatestVideos(PDO $pdo, int $limit = 12): array {
    return fetchDiscoveryRows($pdo, 'v.uploaded_at DESC', $limit);
}

function getTrendingVideos(PDO $pdo, int $limit = 12): array {
    return fetchDiscoveryRows($pdo, '(COALESCE(vc.views,0) * 1.5 + SUM(CASE WHEN vr.reaction=\'like\' THEN 1 ELSE 0 END) * 2) DESC, v.uploaded_at DESC', $limit);
}

function getRelatedVideos(PDO $pdo, int $videoId, int $creatorId, int $limit = 12): array {
    $sql = "SELECT v.id, v.slug, v.title, v.uploaded_at, v.duration_seconds, u.username,
                COALESCE(vc.views, 0) AS views,
                SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes,
                ta.id AS thumbnail_asset_id,
                (CASE WHEN v.user_id = ? THEN 10 ELSE 0 END) +
                (CASE WHEN v.uploaded_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) THEN 3 ELSE 0 END) +
                COALESCE(vc.views,0) * 0.02 + SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) * 0.05 AS score
            FROM videos v
            INNER JOIN users u ON u.id=v.user_id
            LEFT JOIN video_reactions vr ON vr.video_id=v.id
            LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id
            LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
            WHERE " . readyVisibilityClause('v') . " AND v.id != ?
            GROUP BY v.id
            ORDER BY score DESC, v.uploaded_at DESC
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$creatorId, $videoId]);
    return $stmt->fetchAll();
}

function searchVideosAndChannels(PDO $pdo, string $query, string $sort = 'relevance', int $limit = 24): array {
    $term = '%' . $query . '%';
    $order = match ($sort) {
        'newest' => 'v.uploaded_at DESC',
        'popular' => 'views DESC, likes DESC, v.uploaded_at DESC',
        default => 'score DESC, v.uploaded_at DESC',
    };

    $sql = "SELECT v.id, v.slug, v.title, v.description, v.uploaded_at, v.duration_seconds,
                u.username,
                ta.id AS thumbnail_asset_id,
                COALESCE(vc.views, 0) AS views,
                SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes,
                (CASE WHEN v.title LIKE ? THEN 10 ELSE 0 END) +
                (CASE WHEN u.username LIKE ? THEN 4 ELSE 0 END) +
                (CASE WHEN v.description LIKE ? THEN 2 ELSE 0 END) AS score
            FROM videos v
            INNER JOIN users u ON u.id=v.user_id
            LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id
            LEFT JOIN video_reactions vr ON vr.video_id=v.id
            LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
            WHERE " . readyVisibilityClause('v') . " AND (v.title LIKE ? OR v.description LIKE ? OR u.username LIKE ?)
            GROUP BY v.id
            ORDER BY {$order}
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$term, $term, $term, $term, $term, $term]);
    return $stmt->fetchAll();
}

function canUserAccessVideo(array $video, int $userId): bool {
    if (($video['visibility'] ?? 'private') === 'public' && ($video['processing_status'] ?? '') === 'ready') {
        return true;
    }
    return $userId > 0 && $userId === (int)$video['user_id'];
}

function recordVideoView(PDO $pdo, int $videoId, ?int $userId): void {
    $fingerprint = session_id() ?: bin2hex(random_bytes(8));
    $stmt = $pdo->prepare('SELECT id FROM video_views WHERE video_id=? AND viewer_fingerprint=? AND viewed_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1');
    $stmt->execute([$videoId, $fingerprint]);
    if (!$stmt->fetch()) {
        $pdo->prepare('INSERT INTO video_views (video_id, user_id, viewer_fingerprint) VALUES (?, ?, ?)')->execute([$videoId, $userId, $fingerprint]);
    }
}

function recordWatchProgress(PDO $pdo, int $videoId, int $userId, float $position, float $duration): void {
    if ($userId < 1) return;
    $position = max(0, $position);
    $duration = max(0, $duration);
    $pdo->prepare('INSERT INTO watch_history (user_id, video_id, last_position_seconds, duration_seconds, watched_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE last_position_seconds=VALUES(last_position_seconds), duration_seconds=VALUES(duration_seconds), watched_at=NOW()')
        ->execute([$userId, $videoId, $position, $duration]);
}

function ensureWatchLaterPlaylist(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT id FROM playlists WHERE user_id=? AND is_system_watch_later=1 LIMIT 1");
    $stmt->execute([$userId]);
    $id = (int)$stmt->fetchColumn();
    if ($id > 0) return $id;
    $pdo->prepare("INSERT INTO playlists (user_id, slug, title, description, visibility, is_system_watch_later) VALUES (?, ?, 'Watch Later', 'Auto-generated watch later list', 'private', 1)")
        ->execute([$userId, 'watch-later-' . $userId]);
    return (int)$pdo->lastInsertId();
}

function ensureProductSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        user_id INT NULL,
        viewer_fingerprint VARCHAR(128) NOT NULL,
        viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_views_video (video_id),
        INDEX idx_views_time (viewed_at),
        CONSTRAINT fk_views_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS watch_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        video_id INT NOT NULL,
        last_position_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
        duration_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
        watched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_history_user_video (user_id, video_id),
        INDEX idx_history_user_time (user_id, watched_at),
        CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_history_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS playlists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        slug VARCHAR(80) NOT NULL UNIQUE,
        title VARCHAR(140) NOT NULL,
        description TEXT NULL,
        visibility ENUM('public','private','unlisted') NOT NULL DEFAULT 'private',
        is_system_watch_later TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_playlists_user (user_id),
        CONSTRAINT fk_playlist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS playlist_videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        playlist_id INT NOT NULL,
        video_id INT NOT NULL,
        position_index INT NOT NULL DEFAULT 0,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_playlist_video (playlist_id, video_id),
        INDEX idx_playlist_position (playlist_id, position_index),
        CONSTRAINT fk_playlist_video_playlist FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
        CONSTRAINT fk_playlist_video_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(80) NOT NULL,
        user_id INT NULL,
        payload_json TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_event_type (event_type),
        INDEX idx_product_event_time (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS video_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        user_id INT NOT NULL,
        reason VARCHAR(40) NOT NULL,
        details TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_report_video (video_id),
        CONSTRAINT fk_report_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        CONSTRAINT fk_report_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
