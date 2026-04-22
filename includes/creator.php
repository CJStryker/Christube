<?php

function ensureCreatorSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(60) NOT NULL,
        title VARCHAR(180) NOT NULL,
        body TEXT NULL,
        ref_type VARCHAR(40) NULL,
        ref_id INT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        dedupe_key VARCHAR(160) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME NULL,
        UNIQUE KEY uniq_notification_dedupe (user_id, dedupe_key),
        INDEX idx_notification_user (user_id, is_read, created_at),
        CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        in_app_comments TINYINT(1) NOT NULL DEFAULT 1,
        in_app_follows TINYINT(1) NOT NULL DEFAULT 1,
        in_app_processing TINYINT(1) NOT NULL DEFAULT 1,
        in_app_promotions TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_notif_pref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS creator_daily_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        stat_date DATE NOT NULL,
        views_count INT NOT NULL DEFAULT 0,
        watch_starts INT NOT NULL DEFAULT 0,
        likes_count INT NOT NULL DEFAULT 0,
        comments_count INT NOT NULL DEFAULT 0,
        followers_gained INT NOT NULL DEFAULT 0,
        avg_watch_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
        completion_rate DECIMAL(7,4) NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_creator_day (user_id, stat_date),
        INDEX idx_creator_day (stat_date),
        CONSTRAINT fk_creator_stat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS video_daily_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        stat_date DATE NOT NULL,
        views_count INT NOT NULL DEFAULT 0,
        watch_starts INT NOT NULL DEFAULT 0,
        likes_count INT NOT NULL DEFAULT 0,
        comments_count INT NOT NULL DEFAULT 0,
        avg_watch_seconds DECIMAL(12,3) NOT NULL DEFAULT 0,
        completion_rate DECIMAL(7,4) NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_video_day (video_id, stat_date),
        INDEX idx_video_day (stat_date),
        CONSTRAINT fk_video_stat_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS creator_comment_actions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        creator_id INT NOT NULL,
        comment_id INT NOT NULL,
        action VARCHAR(40) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_creator_comment_actions (creator_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!$pdo->query("SHOW COLUMNS FROM videos LIKE 'is_archived'")->fetch()) {
        $pdo->exec("ALTER TABLE videos ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER visibility");
    }
    if (!$pdo->query("SHOW COLUMNS FROM videos LIKE 'published_at'")->fetch()) {
        $pdo->exec("ALTER TABLE videos ADD COLUMN published_at DATETIME NULL AFTER uploaded_at");
    }
}

function requireAdminUser(): void {
    if (($_SESSION['username'] ?? '') !== 'Zesty') {
        http_response_code(403);
        exit('Admin only');
    }
}

function creatorNav(string $base = '../'): array {
    return [
        $base . 'creator/dashboard.php' => 'Dashboard',
        $base . 'creator/videos.php' => 'My Videos',
        $base . 'creator/analytics.php' => 'Analytics',
        $base . 'creator/comments.php' => 'Comments',
        $base . 'creator/promotions.php' => 'Promotions',
        $base . 'creator/notifications.php' => 'Notifications',
        $base . 'creator/settings.php' => 'Channel Settings',
    ];
}

function notifyUser(PDO $pdo, int $userId, string $type, string $title, string $body = '', ?string $refType = null, ?int $refId = null, ?string $dedupeKey = null): void {
    $pref = $pdo->prepare('SELECT * FROM notification_preferences WHERE user_id=? LIMIT 1');
    $pref->execute([$userId]);
    $prefs = $pref->fetch();
    if (!$prefs) {
        $pdo->prepare('INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)')->execute([$userId]);
        $prefs = ['in_app_comments'=>1,'in_app_follows'=>1,'in_app_processing'=>1,'in_app_promotions'=>1];
    }

    $prefMap = [
        'comment_on_video' => 'in_app_comments',
        'new_follower' => 'in_app_follows',
        'upload_processed' => 'in_app_processing',
        'upload_failed' => 'in_app_processing',
        'promotion_status' => 'in_app_promotions',
    ];
    if (isset($prefMap[$type]) && (int)($prefs[$prefMap[$type]] ?? 1) === 0) {
        return;
    }

    $sql = 'INSERT INTO notifications (user_id,type,title,body,ref_type,ref_id,dedupe_key) VALUES (?,?,?,?,?,?,?)';
    if ($dedupeKey !== null) {
        $sql .= ' ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body), created_at=NOW(), is_read=0, read_at=NULL';
    }
    $pdo->prepare($sql)->execute([$userId, $type, $title, $body, $refType, $refId, $dedupeKey]);
}

function creatorDateRangeSql(string $preset): array {
    return match ($preset) {
        '7d' => ['clause' => '>= DATE_SUB(CURDATE(), INTERVAL 7 DAY)', 'label' => 'Last 7 Days'],
        '28d' => ['clause' => '>= DATE_SUB(CURDATE(), INTERVAL 28 DAY)', 'label' => 'Last 28 Days'],
        default => ['clause' => '>= DATE_SUB(CURDATE(), INTERVAL 3650 DAY)', 'label' => 'Lifetime'],
    };
}

function getCreatorOverviewStats(PDO $pdo, int $creatorId): array {
    $stats = [
        'total_videos' => 0,
        'published_videos' => 0,
        'processing_videos' => 0,
        'failed_videos' => 0,
        'followers' => 0,
        'total_views' => 0,
        'unread_notifications' => 0,
    ];

    $sql = "SELECT
        COUNT(*) AS total_videos,
        SUM(CASE WHEN processing_status='ready' AND visibility='public' AND is_archived=0 THEN 1 ELSE 0 END) AS published_videos,
        SUM(CASE WHEN processing_status='processing' THEN 1 ELSE 0 END) AS processing_videos,
        SUM(CASE WHEN processing_status='failed' THEN 1 ELSE 0 END) AS failed_videos
        FROM videos WHERE user_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$creatorId]);
    $row = $stmt->fetch() ?: [];
    foreach ($stats as $k => $v) {
        if (isset($row[$k])) $stats[$k] = (int)$row[$k];
    }

    $f = $pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE followed_id=?');
    $f->execute([$creatorId]);
    $stats['followers'] = (int)$f->fetchColumn();

    $views = $pdo->prepare('SELECT COUNT(*) FROM video_views vv INNER JOIN videos v ON v.id=vv.video_id WHERE v.user_id=?');
    $views->execute([$creatorId]);
    $stats['total_views'] = (int)$views->fetchColumn();

    $unread = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $unread->execute([$creatorId]);
    $stats['unread_notifications'] = (int)$unread->fetchColumn();

    return $stats;
}

function getCreatorAnalytics(PDO $pdo, int $creatorId, string $preset = '28d'): array {
    $range = creatorDateRangeSql($preset);
    $where = $range['clause'];

    $viewsStmt = $pdo->prepare("SELECT COUNT(*) FROM video_views vv INNER JOIN videos v ON v.id=vv.video_id WHERE v.user_id=? AND DATE(vv.viewed_at) {$where}");
    $viewsStmt->execute([$creatorId]);
    $views = (int)$viewsStmt->fetchColumn();

    $startsStmt = $pdo->prepare("SELECT COUNT(*) FROM product_events pe INNER JOIN videos v ON v.id=JSON_UNQUOTE(JSON_EXTRACT(pe.payload_json, '$.video_id')) WHERE pe.event_type='watch_start' AND v.user_id=? AND DATE(pe.created_at) {$where}");
    $startsStmt->execute([$creatorId]);
    $watchStarts = (int)$startsStmt->fetchColumn();

    $likesStmt = $pdo->prepare("SELECT COUNT(*) FROM video_reactions vr INNER JOIN videos v ON v.id=vr.video_id WHERE v.user_id=? AND vr.reaction='like' AND DATE(vr.created_at) {$where}");
    $likesStmt->execute([$creatorId]);
    $likes = (int)$likesStmt->fetchColumn();

    $commentsStmt = $pdo->prepare("SELECT COUNT(*) FROM video_comments vc INNER JOIN videos v ON v.id=vc.video_id WHERE v.user_id=? AND DATE(vc.created_at) {$where}");
    $commentsStmt->execute([$creatorId]);
    $comments = (int)$commentsStmt->fetchColumn();

    $followsStmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE followed_id=? AND DATE(created_at) {$where}");
    $followsStmt->execute([$creatorId]);
    $follows = (int)$followsStmt->fetchColumn();

    $progressStmt = $pdo->prepare("SELECT AVG(h.last_position_seconds) AS avg_watch, AVG(CASE WHEN h.duration_seconds>0 THEN LEAST(1, h.last_position_seconds / h.duration_seconds) ELSE 0 END) AS completion
        FROM watch_history h INNER JOIN videos v ON v.id=h.video_id WHERE v.user_id=? AND DATE(h.watched_at) {$where}");
    $progressStmt->execute([$creatorId]);
    $progress = $progressStmt->fetch() ?: ['avg_watch' => 0, 'completion' => 0];

    $topStmt = $pdo->prepare("SELECT v.id,v.slug,v.title,COUNT(vv.id) AS views
        FROM videos v LEFT JOIN video_views vv ON vv.video_id=v.id AND DATE(vv.viewed_at) {$where}
        WHERE v.user_id=? GROUP BY v.id ORDER BY views DESC LIMIT 5");
    $topStmt->execute([$creatorId]);

    $seriesStmt = $pdo->prepare("SELECT DATE(vv.viewed_at) AS day, COUNT(*) AS views
        FROM video_views vv INNER JOIN videos v ON v.id=vv.video_id WHERE v.user_id=? AND DATE(vv.viewed_at) {$where}
        GROUP BY DATE(vv.viewed_at) ORDER BY day ASC");
    $seriesStmt->execute([$creatorId]);

    return [
        'range_label' => $range['label'],
        'views' => $views,
        'watch_starts' => $watchStarts,
        'likes' => $likes,
        'comments' => $comments,
        'follows_gained' => $follows,
        'avg_watch_seconds' => (float)($progress['avg_watch'] ?? 0),
        'completion_rate' => (float)($progress['completion'] ?? 0),
        'top_videos' => $topStmt->fetchAll(),
        'series' => $seriesStmt->fetchAll(),
    ];
}
