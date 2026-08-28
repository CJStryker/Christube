<?php
require_once 'config.php';

$error = '';
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutation([
        'requireAuth' => false,
        'rateBucket' => 'login',
        'rateLimit' => 40,
        'rateWindow' => 300,
        'onErrorRedirect' => 'login.php',
    ], function () use ($pdo): void {
        $usernameOrEmail = strtolower(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';

        if ($usernameOrEmail === '' || $password === '') {
            throw new RuntimeException('Please enter both username/email and password.');
        }

        $stmt = $pdo->prepare('SELECT id, username, password, account_status FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            throw new RuntimeException('Invalid username/email or password.');
        }

        if (($user['account_status'] ?? 'active') !== 'active') {
            throw new RuntimeException('Account is not active.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        setFlash(true, 'Welcome back!');
        header('Location: index.php');
        exit;
    });
}

$flash = pullFlash();
if ($flash && !$flash['ok']) {
    $error = (string)$flash['msg'];
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Login</title>
<link rel="stylesheet" href="public/styles.css"></head>
<body>
<div class="auth-container">
    <h2>Login</h2>
    <?php if ($error): ?><div class="error"><?php echo e($error); ?></div><?php endif; ?>
    <div class="tos-box"><h3>Terms of Service & Content Policy</h3><p>By using Christube, you agree to platform rules. Abuse material is forbidden. Consensual legal adult content may be allowed where lawful. Private uploads are still moderated. Abuse will result in swift enforcement.</p></div>
    <form method="POST" action=""><?php echo csrfInput(); ?>
        <div class="form-group"><label for="username">Username or Email</label><input type="text" id="username" name="username" required></div>
        <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
        <button type="submit" class="btn">Login</button>
    </form>
    <div class="auth-links"><p>Don't have an account? <a href="register.php">Register here</a></p></div>
</div>
</body></html>
