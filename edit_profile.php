<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutation([
        'requireAuth' => true,
        'rateBucket' => 'edit_profile',
        'onErrorRedirect' => 'edit_profile.php',
    ], function () use ($pdo): void {
        $userId = (int)$_SESSION['user_id'];
        $displayName = trim($_POST['display_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $avatar = trim($_POST['avatar_url'] ?? '');
        $banner = trim($_POST['banner_url'] ?? '');
        $visibility = $_POST['profile_visibility'] ?? 'public';

        if (!in_array($visibility, ['public', 'private'], true)) {
            throw new RuntimeException('Invalid profile visibility.');
        }
        if ($displayName === '') {
            throw new RuntimeException('Display name is required.');
        }

        $stmt = $pdo->prepare('UPDATE users SET display_name = ?, bio = ?, avatar_url = ?, banner_url = ?, profile_visibility = ? WHERE id = ?');
        $stmt->execute([$displayName, $bio, $avatar, $banner, $visibility, $userId]);

        setFlash(true, 'Profile updated.');
        header('Location: profile.php?u=' . urlencode($_SESSION['username']));
        exit;
    });
}

$stmt = $pdo->prepare('SELECT username, display_name, bio, avatar_url, banner_url, profile_visibility FROM users WHERE id = ?');
$stmt->execute([(int)$_SESSION['user_id']]);
$user = $stmt->fetch() ?: [];
$flash = pullFlash();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Profile</title><link rel="stylesheet" href="public/styles.css"></head>
<body><div class="auth-container" style="max-width:700px;"><h1>Edit Profile</h1>
<?php if($flash): ?><div class="<?php echo $flash['ok'] ? 'success' : 'error'; ?>"><?php echo e((string)$flash['msg']); ?></div><?php endif; ?>
<form method="post"><?php echo csrfInput(); ?>
<div class="form-group"><label>Username (read-only)</label><input type="text" value="<?php echo e((string)($user['username'] ?? '')); ?>" disabled></div>
<div class="form-group"><label>Display Name</label><input type="text" name="display_name" maxlength="80" value="<?php echo e((string)($user['display_name'] ?? '')); ?>" required></div>
<div class="form-group"><label>Bio</label><textarea name="bio" maxlength="4000"><?php echo e((string)($user['bio'] ?? '')); ?></textarea></div>
<div class="form-group"><label>Avatar URL (placeholder hook)</label><input type="url" name="avatar_url" value="<?php echo e((string)($user['avatar_url'] ?? '')); ?>"></div>
<div class="form-group"><label>Banner URL (placeholder hook)</label><input type="url" name="banner_url" value="<?php echo e((string)($user['banner_url'] ?? '')); ?>"></div>
<div class="form-group"><label>Profile Visibility</label><select name="profile_visibility"><option value="public" <?php echo (($user['profile_visibility'] ?? 'public')==='public')?'selected':''; ?>>Public</option><option value="private" <?php echo (($user['profile_visibility'] ?? 'public')==='private')?'selected':''; ?>>Private</option></select></div>
<button type="submit" class="btn">Save Profile</button>
</form>
<p><a href="profile.php?u=<?php echo urlencode($_SESSION['username']); ?>">Back to profile</a></p>
</div></body></html>
