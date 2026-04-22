<?php

const MEDIA_STATUS_DRAFT = 'draft';
const MEDIA_STATUS_UPLOADING = 'uploading';
const MEDIA_STATUS_PROCESSING = 'processing';
const MEDIA_STATUS_READY = 'ready';
const MEDIA_STATUS_FAILED = 'failed';

function mediaConfig(): array {
    $base = env('MEDIA_ROOT', __DIR__ . '/../storage/media');
    return [
        'root' => rtrim($base, '/'),
        'maxUploadBytes' => (int)(env('MAX_VIDEO_UPLOAD_BYTES', (string)MAX_VIDEO_UPLOAD_BYTES) ?? MAX_VIDEO_UPLOAD_BYTES),
        'chunkBytes' => (int)(env('UPLOAD_CHUNK_BYTES', '5242880') ?? 5242880),
        'sessionTtlHours' => (int)(env('UPLOAD_SESSION_TTL_HOURS', '24') ?? 24),
    ];
}

function mediaEnsureDir(string $path): void {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function mediaPaths(): array {
    $cfg = mediaConfig();
    $paths = [
        'root' => $cfg['root'],
        'tmp' => $cfg['root'] . '/tmp_chunks',
        'originals' => $cfg['root'] . '/originals',
        'playback' => $cfg['root'] . '/playback',
        'thumbnails' => $cfg['root'] . '/thumbnails',
        'derivatives' => $cfg['root'] . '/derivatives',
    ];

    foreach ($paths as $path) {
        mediaEnsureDir($path);
    }

    return $paths;
}

function mediaSafeExt(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = ['mp4', 'mov', 'webm', 'ogg'];
    return in_array($ext, $allowed, true) ? $ext : 'mp4';
}

function mediaDetectMime(string $path): string {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string)finfo_file($finfo, $path) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    return $mime;
}

function mediaAllowedMime(string $mime): bool {
    return in_array($mime, ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'], true);
}

function mediaJson(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function mediaRequireApiAuthAndCsrf(): int {
    requirePost();
    requireLogin();
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        mediaJson(['ok' => false, 'error' => 'Invalid CSRF token'], 422);
    }
    return (int)$_SESSION['user_id'];
}

function mediaCreateUploadSession(PDO $pdo, int $userId, string $title, string $description, string $visibility, int $totalBytes, string $originalName): array {
    $cfg = mediaConfig();
    if ($title === '' || !validateVisibility($visibility)) {
        throw new RuntimeException('Invalid title or visibility.');
    }
    if ($totalBytes < 1 || $totalBytes > $cfg['maxUploadBytes']) {
        throw new RuntimeException('File size outside allowed range.');
    }

    $sessionId = bin2hex(random_bytes(16));
    $paths = mediaPaths();
    $dir = $paths['tmp'] . '/' . $sessionId;
    mediaEnsureDir($dir);

    $stmt = $pdo->prepare('INSERT INTO upload_sessions (session_id, user_id, title, description, visibility, original_filename, total_bytes, status, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))');
    $stmt->execute([$sessionId, $userId, $title, $description, $visibility, $originalName, $totalBytes, MEDIA_STATUS_UPLOADING, $cfg['sessionTtlHours']]);

    auditEvent($pdo, $userId, 'upload_session_created', ['session_id' => $sessionId, 'total_bytes' => $totalBytes]);

    return ['session_id' => $sessionId, 'chunk_bytes' => $cfg['chunkBytes']];
}

function mediaGetUploadSession(PDO $pdo, string $sessionId, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM upload_sessions WHERE session_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$sessionId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mediaAppendChunk(PDO $pdo, array $session, int $chunkIndex, int $totalChunks, string $tmpPath, int $chunkSize): void {
    if (($session['status'] ?? '') === MEDIA_STATUS_FAILED) {
        throw new RuntimeException('Upload session is failed.');
    }
    if ($chunkIndex < 0 || $totalChunks < 1 || $chunkIndex >= $totalChunks) {
        throw new RuntimeException('Invalid chunk index.');
    }

    $paths = mediaPaths();
    $dir = $paths['tmp'] . '/' . $session['session_id'];
    mediaEnsureDir($dir);
    $chunkPath = sprintf('%s/chunk_%06d.part', $dir, $chunkIndex);
    if (!move_uploaded_file($tmpPath, $chunkPath)) {
        throw new RuntimeException('Failed to persist chunk.');
    }

    $stmt = $pdo->prepare('INSERT INTO upload_chunks (upload_session_id, chunk_index, chunk_size, chunk_path) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE chunk_size = VALUES(chunk_size), chunk_path = VALUES(chunk_path), received_at = NOW()');
    $stmt->execute([(int)$session['id'], $chunkIndex, $chunkSize, $chunkPath]);

    $pdo->prepare('UPDATE upload_sessions SET uploaded_bytes = uploaded_bytes + ?, updated_at = NOW() WHERE id = ?')->execute([$chunkSize, (int)$session['id']]);
}

function mediaFinalizeUpload(PDO $pdo, array $session, int $userId): int {
    if ($session['status'] === MEDIA_STATUS_PROCESSING || $session['status'] === MEDIA_STATUS_READY) {
        return (int)$session['video_id'];
    }

    $chunksStmt = $pdo->prepare('SELECT chunk_index, chunk_path FROM upload_chunks WHERE upload_session_id = ? ORDER BY chunk_index ASC');
    $chunksStmt->execute([(int)$session['id']]);
    $chunks = $chunksStmt->fetchAll();
    if (!$chunks) {
        throw new RuntimeException('No chunks uploaded.');
    }

    $paths = mediaPaths();
    $ext = mediaSafeExt((string)$session['original_filename']);
    $sourceRel = 'originals/' . date('Y/m') . '/' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $sourceAbs = $paths['root'] . '/' . $sourceRel;
    mediaEnsureDir(dirname($sourceAbs));

    $out = fopen($sourceAbs, 'wb');
    if ($out === false) {
        throw new RuntimeException('Failed to initialize source file.');
    }
    foreach ($chunks as $chunk) {
        $in = fopen($chunk['chunk_path'], 'rb');
        if ($in === false) {
            fclose($out);
            throw new RuntimeException('Missing chunk during finalize.');
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
    }
    fclose($out);

    $mime = mediaDetectMime($sourceAbs);
    if (!mediaAllowedMime($mime)) {
        @unlink($sourceAbs);
        throw new RuntimeException('Unsupported video format detected.');
    }

    $slug = function_exists('generateUniqueSlug') ? generateUniqueSlug($pdo) : bin2hex(random_bytes(4));

    $pdo->beginTransaction();
    try {
        $assetStmt = $pdo->prepare('INSERT INTO media_assets (asset_kind, storage_disk, storage_path, mime_type, size_bytes, status) VALUES (?,?,?,?,?,?)');
        $assetStmt->execute(['original', 'local', $sourceRel, $mime, filesize($sourceAbs), 'ready']);
        $sourceAssetId = (int)$pdo->lastInsertId();

        $videoStmt = $pdo->prepare('INSERT INTO videos (user_id, slug, title, description, visibility, file_path, processing_status, source_asset_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $videoStmt->execute([$userId, $slug, $session['title'], $session['description'], $session['visibility'], '', MEDIA_STATUS_PROCESSING, $sourceAssetId]);
        $videoId = (int)$pdo->lastInsertId();

        $pdo->prepare('UPDATE upload_sessions SET status = ?, video_id = ?, completed_at = NOW() WHERE id = ?')->execute([MEDIA_STATUS_PROCESSING, $videoId, (int)$session['id']]);
        $pdo->prepare('INSERT INTO media_jobs (video_id, job_type, status, attempts, run_after) VALUES (?, ?, ?, 0, NOW())')->execute([$videoId, 'process_video', 'queued']);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    auditEvent($pdo, $userId, 'upload_finalized', ['session_id' => $session['session_id'], 'video_id' => $videoId]);
    return $videoId;
}

function mediaAssetUrl(int $assetId): string {
    return 'media_asset.php?id=' . $assetId;
}

function videoIsPubliclyWatchable(array $video): bool {
    return ($video['visibility'] ?? 'private') === 'public' && ($video['processing_status'] ?? '') === MEDIA_STATUS_READY;
}

function ensureMediaSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS upload_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL UNIQUE,
        user_id INT NOT NULL,
        video_id INT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        visibility ENUM('public','private') NOT NULL DEFAULT 'public',
        original_filename VARCHAR(255) NOT NULL,
        total_bytes BIGINT NOT NULL,
        uploaded_bytes BIGINT NOT NULL DEFAULT 0,
        status VARCHAR(24) NOT NULL DEFAULT 'uploading',
        error_message VARCHAR(255) NULL,
        expires_at DATETIME NOT NULL,
        completed_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_upload_user_status (user_id, status),
        INDEX idx_upload_expires (expires_at),
        CONSTRAINT fk_upload_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS upload_chunks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        upload_session_id INT NOT NULL,
        chunk_index INT NOT NULL,
        chunk_size INT NOT NULL,
        chunk_path VARCHAR(255) NOT NULL,
        received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_chunk (upload_session_id, chunk_index),
        CONSTRAINT fk_chunk_session FOREIGN KEY (upload_session_id) REFERENCES upload_sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS media_assets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_kind VARCHAR(32) NOT NULL,
        storage_disk VARCHAR(24) NOT NULL DEFAULT 'local',
        storage_path VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NULL,
        size_bytes BIGINT NULL,
        width INT NULL,
        height INT NULL,
        duration_seconds DECIMAL(12,3) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'ready',
        metadata_json TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_asset_kind (asset_kind)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS media_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        job_type VARCHAR(32) NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'queued',
        attempts INT NOT NULL DEFAULT 0,
        max_attempts INT NOT NULL DEFAULT 3,
        run_after DATETIME NOT NULL,
        locked_at DATETIME NULL,
        last_error VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_job_status (status, run_after),
        CONSTRAINT fk_job_video FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $adds = [
        "ALTER TABLE videos ADD COLUMN processing_status VARCHAR(24) NOT NULL DEFAULT 'ready' AFTER file_path",
        "ALTER TABLE videos ADD COLUMN source_asset_id INT NULL AFTER processing_status",
        "ALTER TABLE videos ADD COLUMN playback_asset_id INT NULL AFTER source_asset_id",
        "ALTER TABLE videos ADD COLUMN thumbnail_asset_id INT NULL AFTER playback_asset_id",
        "ALTER TABLE videos ADD COLUMN duration_seconds DECIMAL(12,3) NULL AFTER thumbnail_asset_id",
        "ALTER TABLE videos ADD COLUMN width INT NULL AFTER duration_seconds",
        "ALTER TABLE videos ADD COLUMN height INT NULL AFTER width",
        "ALTER TABLE videos ADD COLUMN processing_error VARCHAR(255) NULL AFTER height",
        "ALTER TABLE videos ADD COLUMN ready_at DATETIME NULL AFTER processing_error",
    ];
    $checks = ['processing_status','source_asset_id','playback_asset_id','thumbnail_asset_id','duration_seconds','width','height','processing_error','ready_at'];
    foreach ($checks as $i => $column) {
        if (!$pdo->query("SHOW COLUMNS FROM videos LIKE '{$column}'")->fetch()) {
            $pdo->exec($adds[$i]);
        }
    }
}

function mediaResolveAbsolutePath(string $storagePath): string {
    $paths = mediaPaths();
    return $paths['root'] . '/' . ltrim($storagePath, '/');
}
