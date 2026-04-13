<?php
$host = 'localhost';
$dbname = 'user_auth';
$username = 'user';
$password = 'poopie';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const DONATION_XMR_ADDRESS = '86KNpUKopsJTFUj72PQoLYX7xpsKMiyd6G5BKYoG65FaKzUQqf4jqLaS6LPUjh8cq5MQTsQh3V2hVRQSqp8j4JGL4Xf9cvq';
const XP_PER_XMR = 1000;

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUserUploadDir(int $user_id): string {
    $baseDir = __DIR__ . '/uploads/videos/';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0775, true);
    }

    $dir = $baseDir . 'user_' . $user_id . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }

    return $dir;
}


function calculateXpFromXmr(float $amountXmr): int {
    if ($amountXmr <= 0) {
        return 0;
    }

    return (int)floor($amountXmr * XP_PER_XMR);
}

function getLevelFromXp(int $xp): int {
    return (int)floor(sqrt(max(0, $xp) / 100)) + 1;
}

function getXpForNextLevel(int $xp): int {
    $level = getLevelFromXp($xp);
    return (int)(pow($level, 2) * 100);
}

function addExperience(PDO $pdo, int $userId, int $xp, string $reason): void {
    if ($xp <= 0) {
        return;
    }

    $pdo->prepare('UPDATE users SET experience_points = experience_points + ? WHERE id = ?')->execute([$xp, $userId]);
    $pdo->prepare('INSERT INTO user_xp_events (user_id, xp_delta, reason) VALUES (?, ?, ?)')->execute([$userId, $xp, $reason]);
}

function spendExperience(PDO $pdo, int $userId, int $xp, string $reason): bool {
    if ($xp <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE users SET experience_points = experience_points - ? WHERE id = ? AND experience_points >= ?');
    $stmt->execute([$xp, $userId, $xp]);
    if ($stmt->rowCount() < 1) {
        return false;
    }

    $pdo->prepare('INSERT INTO user_xp_events (user_id, xp_delta, reason) VALUES (?, ?, ?)')->execute([$userId, -$xp, $reason]);
    return true;
}

function getActiveVideoAds(PDO $pdo, int $limit = 5): array {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.points_spent, a.active_until, v.slug, v.title, u.username
         FROM video_ads a
         INNER JOIN videos v ON v.id = a.video_id
         INNER JOIN users u ON u.id = a.user_id
         WHERE a.active_until >= NOW() AND v.visibility = 'public'
         ORDER BY a.points_spent DESC, a.created_at DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function ensureSchema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            bio TEXT NULL,
            experience_points INT NOT NULL DEFAULT 0,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            slug VARCHAR(16) NOT NULL UNIQUE,
            title VARCHAR(150) NOT NULL,
            description TEXT NULL,
            file_path VARCHAR(255) NOT NULL,
            visibility ENUM('public','private') NOT NULL DEFAULT 'public',
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_videos_uploaded_at (uploaded_at),
            INDEX idx_videos_visibility (visibility),
            CONSTRAINT fk_videos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS video_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id INT NOT NULL,
            user_id INT NOT NULL,
            comment TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_comments_video (video_id),
            CONSTRAINT fk_comments_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS video_reactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id INT NOT NULL,
            user_id INT NOT NULL,
            reaction ENUM('like','dislike') NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_video_user_reaction (video_id, user_id),
            INDEX idx_reactions_video (video_id),
            CONSTRAINT fk_reactions_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            CONSTRAINT fk_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_follows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT NOT NULL,
            followed_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_follow (follower_id, followed_id),
            INDEX idx_followed (followed_id),
            CONSTRAINT fk_follow_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_followed_user FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_xp_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            xp_delta INT NOT NULL,
            reason VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_xp_user (user_id),
            CONSTRAINT fk_xp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS video_ads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id INT NOT NULL,
            user_id INT NOT NULL,
            points_spent INT NOT NULL,
            active_until DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ads_active (active_until),
            CONSTRAINT fk_ads_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
            CONSTRAINT fk_ads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );



    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS xmr_point_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            tx_hash VARCHAR(128) NOT NULL,
            amount_xmr DECIMAL(16,8) NOT NULL,
            requested_xp INT NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note TEXT NULL,
            processed_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            UNIQUE KEY uniq_tx_hash (tx_hash),
            INDEX idx_xmr_user (user_id),
            INDEX idx_xmr_status (status),
            CONSTRAINT fk_xmr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Schema upgrades for existing installs.
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'bio'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER email");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'experience_points'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN experience_points INT NOT NULL DEFAULT 0 AFTER bio");
    }

    $hasSlug = $pdo->query("SHOW COLUMNS FROM videos LIKE 'slug'")->fetch();
    if (!$hasSlug) {
        $pdo->exec("ALTER TABLE videos ADD COLUMN slug VARCHAR(16) NULL UNIQUE AFTER user_id");
        $rows = $pdo->query("SELECT id FROM videos WHERE slug IS NULL OR slug = ''")->fetchAll();
        $updateStmt = $pdo->prepare("UPDATE videos SET slug = ? WHERE id = ?");
        foreach ($rows as $row) {
            $updateStmt->execute([bin2hex(random_bytes(4)), (int)$row['id']]);
        }
        $pdo->exec("ALTER TABLE videos MODIFY COLUMN slug VARCHAR(16) NOT NULL");
    }
}

ensureSchema($pdo);
?>
