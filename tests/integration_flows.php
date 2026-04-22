<?php
/**
 * Phase-2 lightweight integration checks.
 *
 * NOTE: These checks validate route wiring, security guards, and shared mutation
 * pipeline usage without requiring a running HTTP stack.
 */

$root = dirname(__DIR__);

function mustContain(string $file, string $needle): void {
    $content = file_get_contents($file);
    if ($content === false || strpos($content, $needle) === false) {
        throw new RuntimeException("Assertion failed: {$file} must contain `{$needle}`");
    }
}

function mustNotContain(string $file, string $needle): void {
    $content = file_get_contents($file);
    if ($content !== false && strpos($content, $needle) !== false) {
        throw new RuntimeException("Assertion failed: {$file} must not contain `{$needle}`");
    }
}

$mutationEndpoints = [
    'register.php',
    'login.php',
    'upload.php',
    'comment.php',
    'react.php',
    'follow.php',
    'promote_video.php',
    'buy_points.php',
    'admin_verify_points.php',
    'update_visibility.php',
    'delete_own_video.php',
    'delete_video.php',
    'edit_profile.php',
];

foreach ($mutationEndpoints as $endpoint) {
    mustContain($root . '/' . $endpoint, 'handleMutation([');
}

$formPages = [
    'index.php',
    'register.php',
    'login.php',
    'uploads/index.php',
    'buy_points.php',
    'admin_verify_points.php',
    'edit_profile.php',
    'view.php',
    'profile.php',
];

foreach ($formPages as $page) {
    mustContain($root . '/' . $page, 'csrfInput()');
}

// auth flow basics
mustContain($root . '/register.php', 'password_hash(');
mustContain($root . '/login.php', 'password_verify(');
mustContain($root . '/login.php', 'session_regenerate_id');

// csrf and mutation middleware pipeline
mustContain($root . '/config.php', 'function verifyCsrfToken');
mustContain($root . '/config.php', 'function handleMutation');
mustContain($root . '/config.php', 'Invalid or expired form token');

// profile/channel foundation
mustContain($root . '/profile.php', '(Channel)');
mustContain($root . '/channel.php', 'Location: profile.php');

// layout extraction
mustContain($root . '/includes/layout.php', 'function renderTopbar');
mustContain($root . '/public/styles.css', '.topbar');

// sanity
mustNotContain($root . '/index.php', 'csrfInput(); ?><?php echo csrfInput()');

echo "Phase-2 integration checks passed.\n";
