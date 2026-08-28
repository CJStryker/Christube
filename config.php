<?php
/**
 * Christube bootstrap (legacy-compatible)
 * Centralizes environment loading, PDO setup, auth/session helpers,
 * validation, flash messaging, XP economy, and schema setup.
 */

// -------------------------
// Environment loading
// -------------------------
function loadDotEnv(string $path): void {
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

loadDotEnv(__DIR__ . '/.env');

function env(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

const DONATION_XMR_ADDRESS = '86KNpUKopsJTFUj72PQoLYX7xpsKMiyd6G5BKYoG65FaKzUQqf4jqLaS6LPUjh8cq5MQTsQh3V2hVRQSqp8j4JGL4Xf9cvq';
const XP_PER_XMR = 1000;
const MAX_VIDEO_UPLOAD_BYTES = 157286400; // 150MB

require_once __DIR__ . '/includes/media.php';
require_once __DIR__ . '/includes/product.php';
require_once __DIR__ . '/includes/creator.php';
require_once __DIR__ . '/includes/economy.php';
require_once __DIR__ . '/includes/recommendation.php';

$appEnv = env('APP_ENV', 'production');

// -------------------------
// DB setup
// -------------------------
$dbHost = env('DB_HOST', '127.0.0.1');
$dbPort = env('DB_PORT', '3306');
$dbName = env('DB_NAME', 'user_auth');
$dbUser = env('DB_USER', 'root');
$dbPass = env('DB_PASS', '');

$dsnOverride = env('DB_DSN');
$dsn = $dsnOverride ?: "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    $hint = 'Database connection failed. Check DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS (or DB_DSN) in .env.';
    if ($appEnv === 'development') {
        die($hint . ' PDO says: ' . $e->getMessage());
    }
    error_log('Christube DB connection error: ' . $e->getMessage());
    die($hint);
}

// -------------------------
// Session hardening
// -------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

// -------------------------
// Generic helpers
// -------------------------
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setFlash(bool $ok, string $msg): void {
    $_SESSION['flash'] = ['ok' => $ok, 'msg' => $msg];
}

function pullFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}


function currentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => (int)$_SESSION['user_id'],
        'username' => (string)($_SESSION['username'] ?? ''),
    ];
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function csrfInput(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken(?string $token): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    if (!is_string($sessionToken) || !is_string($token)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function handleMutation(array $options, callable $handler): void {
    $requireAuth = $options['requireAuth'] ?? true;
    $rateBucket = $options['rateBucket'] ?? 'mutation';
    $rateLimit = $options['rateLimit'] ?? 80;
    $rateWindow = $options['rateWindow'] ?? 60;
    $onErrorRedirect = $options['onErrorRedirect'] ?? 'index.php';

    requirePost();

    if (!rateLimitCheck((string)$rateBucket, (int)$rateLimit, (int)$rateWindow)) {
        setFlash(false, 'Too many requests. Please slow down.');
        header('Location: ' . $onErrorRedirect);
        exit;
    }

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash(false, 'Invalid or expired form token. Refresh and try again.');
        header('Location: ' . $onErrorRedirect);
        exit;
    }

    if ($requireAuth) {
        requireLogin();
    }

    try {
        $handler();
    } catch (Throwable $e) {
        if (env('APP_ENV', 'production') === 'development') {
            setFlash(false, 'Mutation failed: ' . $e->getMessage());
        } else {
            setFlash(false, 'Request failed. Please try again.');
        }
        header('Location: ' . $onErrorRedirect);
        exit;
    }
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

function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }
}

function validateVisibility(string $visibility): bool {
    return in_array($visibility, ['public', 'private', 'unlisted'], true);
}

function validateTxHash(string $txHash): bool {
    return (bool)preg_match('/^[a-f0-9]{64}$/i', $txHash);
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

// -------------------------
// Security placeholders
// -------------------------
function rateLimitCheck(string $bucket, int $limit = 60, int $windowSeconds = 60): bool {
    // TODO(next phase): replace with Redis/IP+user based distributed limiter.
    $key = 'rl_' . $bucket;
    $now = time();
    $entry = $_SESSION[$key] ?? ['count' => 0, 'start' => $now];

    if (($now - (int)$entry['start']) > $windowSeconds) {
        $entry = ['count' => 0, 'start' => $now];
    }

    $entry['count']++;
    $_SESSION[$key] = $entry;

    return (int)$entry['count'] <= $limit;
}


function auditEvent(PDO $pdo, int $actorUserId, string $eventType, array $payload = []): void {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $pdo->prepare('INSERT INTO audit_logs (actor_user_id, event_type, payload_json) VALUES (?, ?, ?)')->execute([$actorUserId, $eventType, $json]);
}

function moderationCheck(string $content): bool {
    // TODO(next phase): plug in moderation service/classifier and human review queue.
    return trim($content) !== '';
}

// -------------------------
// XP economy
// -------------------------
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

// -------------------------
// Schema management
// -------------------------
function ensureSchema(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            display_name VARCHAR(80) NULL,
            bio TEXT NULL,
            avatar_url VARCHAR(255) NULL,
            banner_url VARCHAR(255) NULL,
            role VARCHAR(24) NOT NULL DEFAULT 'user',
            account_status VARCHAR(24) NOT NULL DEFAULT 'active',
            profile_visibility VARCHAR(24) NOT NULL DEFAULT 'public',
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
            visibility ENUM('public','private','unlisted') NOT NULL DEFAULT 'public',
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
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_user_id INT NULL,
            event_type VARCHAR(80) NOT NULL,
            payload_json TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_event (event_type),
            INDEX idx_audit_actor (actor_user_id)
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

    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'display_name'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(80) NULL AFTER email");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_url'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL AFTER bio");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'banner_url'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN banner_url VARCHAR(255) NULL AFTER avatar_url");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(24) NOT NULL DEFAULT 'user' AFTER banner_url");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'account_status'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN account_status VARCHAR(24) NOT NULL DEFAULT 'active' AFTER role");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'profile_visibility'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_visibility VARCHAR(24) NOT NULL DEFAULT 'public' AFTER account_status");
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'experience_points'")->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN experience_points INT NOT NULL DEFAULT 0 AFTER bio");
    }

    $pdo->exec("ALTER TABLE videos MODIFY COLUMN visibility ENUM('public','private','unlisted') NOT NULL DEFAULT 'public'");

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
ensureMediaSchema($pdo);
ensureProductSchema($pdo);
ensureCreatorSchema($pdo);
ensureEconomySchema($pdo);
?>
