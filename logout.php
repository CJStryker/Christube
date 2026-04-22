<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutation([
        'requireAuth' => false,
        'rateBucket' => 'logout',
        'rateLimit' => 120,
        'rateWindow' => 60,
        'onErrorRedirect' => 'index.php',
    ], function (): void {
        session_unset();
        session_destroy();
        session_start();
        setFlash(true, 'You have been logged out.');
        header('Location: login.php');
        exit;
    });
}

// backward-compatible GET logout support with CSRF fallback notice
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
