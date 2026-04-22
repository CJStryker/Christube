<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    $stmt = $pdo->prepare('UPDATE users SET bio = ? WHERE id = ?');
    $stmt->execute([$bio, (int)$_SESSION['user_id']]);
    $_SESSION['flash'] = ['ok' => true, 'msg' => 'Profile updated.'];
    header('Location: profile.php?u=' . urlencode($_SESSION['username']));
    exit;
}

$stmt = $pdo->prepare('SELECT bio FROM users WHERE id = ?');
$stmt->execute([(int)$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Profile</title>
<style>body{margin:0;background:#050505;color:#00ff66;font-family:Arial}.wrap{max-width:700px;margin:30px auto;padding:0 14px}.panel{background:#101010;border:1px solid #7a0000;border-radius:10px;padding:14px}textarea{width:100%;min-height:140px;background:#000;color:#00ff66;border:1px solid #7a0000;border-radius:6px;padding:10px}button{background:#7a0000;color:#0a0a0a;border:0;border-radius:6px;padding:8px 12px;font-weight:bold;cursor:pointer}a{color:#00ff66}</style>
</head><body><div class="wrap"><div class="panel"><h1>Edit profile bio</h1><form method="post"><textarea name="bio" maxlength="4000"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea><p><button type="submit">Save Bio</button> <a href="profile.php?u=<?php echo urlencode($_SESSION['username']); ?>">Back to profile</a></p></form></div></div></body></html>
