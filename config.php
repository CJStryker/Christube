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

function ensureSchema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
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

    // Schema upgrades for existing installs.


    $hasBio = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'")->fetch();
    if (!$hasBio) {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER email");
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
