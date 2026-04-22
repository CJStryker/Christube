<?php
require_once 'config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$paths = mediaPaths();
$stmt = $pdo->query("SELECT id, session_id FROM upload_sessions WHERE status = 'uploading' AND expires_at < NOW()");
$sessions = $stmt->fetchAll();

foreach ($sessions as $session) {
    $dir = $paths['tmp'] . '/' . $session['session_id'];
    if (is_dir($dir)) {
        $files = glob($dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
    $pdo->prepare("UPDATE upload_sessions SET status='failed', error_message='Expired upload session' WHERE id=?")->execute([(int)$session['id']]);
}

echo 'Cleaned ' . count($sessions) . " expired sessions\n";
