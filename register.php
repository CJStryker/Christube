<?php
require_once 'config.php';

$error = '';
$success = '';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutation([
        'requireAuth' => false,
        'rateBucket' => 'register',
        'rateLimit' => 20,
        'rateWindow' => 300,
        'onErrorRedirect' => 'register.php',
    ], function () use ($pdo, &$error, &$success): void {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $displayName = trim($_POST['display_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            throw new RuntimeException('Username must be 3-30 chars and contain only lowercase letters, numbers, or _.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters long.');
        }
        if ($password !== $confirmPassword) {
            throw new RuntimeException('Passwords do not match.');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Username or email already exists.');
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, display_name, email, password, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$username, $displayName !== '' ? $displayName : $username, $email, $hashed]);

        setFlash(true, 'Registration successful. Please login.');
        header('Location: login.php');
        exit;
    });
}

$flash = pullFlash();
if ($flash && !$flash['ok']) {
    $error = (string)$flash['msg'];
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Register</title>
<link rel="stylesheet" href="public/styles.css"></head>
<body>
<div class="auth-container">
    <h2>Create Account</h2>
    <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>
    <form method="POST" action=""><?php echo csrfInput(); ?>
        <div class="form-group"><label for="username">Username</label><input type="text" id="username" name="username" required></div>
        <div class="form-group"><label for="display_name">Display Name</label><input type="text" id="display_name" name="display_name"></div>
        <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" required></div>
        <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
        <div class="form-group"><label for="confirm_password">Confirm Password</label><input type="password" id="confirm_password" name="confirm_password" required></div>
        <button type="submit" class="btn">Register</button>
    </form>
    <div class="auth-links"><p>Already have an account? <a href="login.php">Login here</a></p></div>
</div>
</body></html>
