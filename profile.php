<?php
require_once 'includes/layout.php';
requireLogin();

$username = strtolower(trim($_GET['u'] ?? $_SESSION['username']));
$userStmt = $pdo->prepare('SELECT id, username, display_name, bio, avatar_url, banner_url, role, account_status, profile_visibility, created_at, experience_points FROM users WHERE username = ?');
$userStmt->execute([$username]);
$profileUser = $userStmt->fetch();
if (!$profileUser) {
    http_response_code(404);
    echo 'Profile not found';
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$profileUserId = (int)$profileUser['id'];
$isOwnProfile = $currentUserId === $profileUserId;
if (($profileUser['profile_visibility'] ?? 'public') === 'private' && !$isOwnProfile) {
    http_response_code(403);
    echo 'Profile is private.';
    exit;
}

$uploadedStmt = $pdo->prepare("SELECT slug, title, visibility, uploaded_at FROM videos WHERE user_id = ? AND (visibility = 'public' OR user_id = ?) ORDER BY uploaded_at DESC");
$uploadedStmt->execute([$profileUserId, $currentUserId]);
$uploadedVideos = $uploadedStmt->fetchAll();

$likedStmt = $pdo->prepare("SELECT v.slug, v.title, u.username FROM video_reactions r INNER JOIN videos v ON v.id = r.video_id INNER JOIN users u ON u.id=v.user_id WHERE r.user_id = ? AND r.reaction='like' AND (v.visibility='public' OR v.user_id = ?) ORDER BY r.created_at DESC");
$likedStmt->execute([$profileUserId, $currentUserId]);
$likedVideos = $likedStmt->fetchAll();

$followerCount = $pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE followed_id = ?');
$followerCount->execute([$profileUserId]);
$followers = (int)$followerCount->fetchColumn();
$followingCount = $pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE follower_id = ?');
$followingCount->execute([$profileUserId]);
$following = (int)$followingCount->fetchColumn();
$isFollowing = false;
if (!$isOwnProfile) {
    $f = $pdo->prepare('SELECT id FROM user_follows WHERE follower_id = ? AND followed_id = ?');
    $f->execute([$currentUserId, $profileUserId]);
    $isFollowing = (bool)$f->fetch();
}

$xp = (int)$profileUser['experience_points'];
$level = getLevelFromXp($xp);
$nextLevelXp = getXpForNextLevel($xp);

$flash = pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profile - <?php echo e($profileUser['username']); ?></title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php
$nav = ['index.php'=>'Home','uploads/index.php'=>'My Uploads','comments_timeline.php'=>'Comments Timeline','my_video_comments.php'=>'My Video Comments','buy_points.php'=>'Buy Points'];
if (($_SESSION['username'] ?? '') === 'Zesty') { $nav['admin_verify_points.php']='Verify Points'; }
renderTopbar('Christube', $nav);
?>
<form method="post" action="logout.php" style="display:inline-block; margin:8px 16px;"><?php echo csrfInput(); ?><button type="submit">Logout</button></form>
<div class="page"><?php renderPromotedSidebar($pdo); ?><main class="main">
<?php if($flash): ?><div class="<?php echo $flash['ok']?'success':'error'; ?>"><?php echo e((string)$flash['msg']); ?></div><?php endif; ?>
<div class="panel"><h1>@<?php echo e($profileUser['username']); ?> <span class="muted">(Channel)</span></h1>
<p class="muted">Display name: <?php echo e((string)($profileUser['display_name'] ?: $profileUser['username'])); ?></p>
<p class="muted">Role: <?php echo e((string)$profileUser['role']); ?> · Status: <?php echo e((string)$profileUser['account_status']); ?></p>
<?php if(!empty($profileUser['avatar_url'])): ?><p><img src="<?php echo e((string)$profileUser['avatar_url']); ?>" alt="avatar" style="max-width:90px;border-radius:50%;"></p><?php endif; ?>
<?php if(!empty($profileUser['banner_url'])): ?><p><img src="<?php echo e((string)$profileUser['banner_url']); ?>" alt="banner" style="max-width:100%;border-radius:8px;"></p><?php endif; ?>
<p class="muted"><?php echo nl2br(e((string)($profileUser['bio'] ?: 'No bio yet.'))); ?></p>
<p class="muted">Level <?php echo $level; ?> · XP <?php echo $xp; ?> / <?php echo $nextLevelXp; ?> · Followers: <?php echo $followers; ?> · Following: <?php echo $following; ?></p>
<p class="tiny">Creator tools placeholders: playlists, community posts, channel analytics, subscriptions (Phase 3+).</p>
<?php if($isOwnProfile): ?><p><a href="edit_profile.php">Edit profile</a> · <a href="channel.php?u=<?php echo urlencode($profileUser['username']); ?>">View channel route</a></p><?php else: ?><form action="follow.php" method="post"><?php echo csrfInput(); ?><input type="hidden" name="user_id" value="<?php echo $profileUserId; ?>"><input type="hidden" name="action" value="<?php echo $isFollowing?'unfollow':'follow'; ?>"><button type="submit"><?php echo $isFollowing?'Unfollow':'Follow'; ?></button></form><?php endif; ?>
</div>
<div class="panel"><h2>Uploaded videos</h2><?php if(!$uploadedVideos): ?><p class="muted">No uploads yet.</p><?php else: ?><ul><?php foreach($uploadedVideos as $v): ?><li><a href="v.php?s=<?php echo urlencode($v['slug']); ?>"><?php echo e($v['title']); ?></a> (<?php echo e($v['visibility']); ?>)</li><?php endforeach; ?></ul><?php endif; ?></div>
<div class="panel"><h2>Liked videos</h2><?php if(!$likedVideos): ?><p class="muted">No liked videos yet.</p><?php else: ?><ul><?php foreach($likedVideos as $v): ?><li><a href="v.php?s=<?php echo urlencode($v['slug']); ?>"><?php echo e($v['title']); ?></a> by <a href="profile.php?u=<?php echo urlencode($v['username']); ?>"><?php echo e($v['username']); ?></a></li><?php endforeach; ?></ul><?php endif; ?></div>
</main></div>
</body></html>
