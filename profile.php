<?php
require_once 'config.php';
requireLogin();

$username = trim($_GET['u'] ?? '');
if ($username === '') {
    $username = $_SESSION['username'];
}

$userStmt = $pdo->prepare('SELECT id, username, bio, created_at, experience_points FROM users WHERE username = ?');
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

$uploadedStmt = $pdo->prepare("SELECT id, slug, title, visibility, uploaded_at FROM videos WHERE user_id = ? AND (visibility = 'public' OR user_id = ?) ORDER BY uploaded_at DESC");
$uploadedStmt->execute([$profileUserId, $currentUserId]);
$uploadedVideos = $uploadedStmt->fetchAll();

$likedStmt = $pdo->prepare("SELECT v.slug, v.title, v.visibility, v.uploaded_at, u.username FROM video_reactions r INNER JOIN videos v ON v.id = r.video_id INNER JOIN users u ON u.id = v.user_id WHERE r.user_id = ? AND r.reaction = 'like' AND (v.visibility = 'public' OR v.user_id = ?) ORDER BY r.created_at DESC");
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
    $followStmt = $pdo->prepare('SELECT id FROM user_follows WHERE follower_id = ? AND followed_id = ?');
    $followStmt->execute([$currentUserId, $profileUserId]);
    $isFollowing = (bool)$followStmt->fetch();
}

$suggestionsStmt = $pdo->prepare(
    "SELECT u.username, COUNT(v.id) AS uploads
     FROM users u
     LEFT JOIN videos v ON v.user_id = u.id AND v.visibility = 'public'
     WHERE u.id != ? AND u.id NOT IN (SELECT followed_id FROM user_follows WHERE follower_id = ?)
     GROUP BY u.id
     ORDER BY uploads DESC, u.created_at DESC
     LIMIT 5"
);
$suggestionsStmt->execute([$currentUserId, $currentUserId]);
$suggestions = $suggestionsStmt->fetchAll();

$xp = (int)$profileUser['experience_points'];
$level = getLevelFromXp($xp);
$nextLevelXp = getXpForNextLevel($xp);
$ads = getActiveVideoAds($pdo, 6);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Profile - <?php echo htmlspecialchars($profileUser['username']); ?></title>
<style>body{margin:0;background:#050505;color:#00ff66;font-family:Arial,sans-serif}.topbar{background:#7a0000;color:#0a0a0a;padding:14px 20px;display:flex;justify-content:space-between}.topbar a{color:#0a0a0a;margin-left:12px;text-decoration:none;font-weight:bold}.page{display:flex;max-width:1400px;margin:20px auto;padding:0 14px;gap:16px}.left{width:250px}.main{flex:1}.panel{background:#101010;border:1px solid #7a0000;border-radius:10px;padding:14px;margin-bottom:14px}.muted{color:#87fcb0}.flash{padding:10px;border:1px solid #7a0000;background:#1c1c1c;border-radius:8px;margin-bottom:12px}button{background:#7a0000;color:#0a0a0a;border:0;border-radius:6px;padding:8px 12px;font-weight:bold;cursor:pointer}a{color:#00ff66}.tiny{font-size:12px;color:#87fcb0}@media (max-width:1000px){.page{display:block}.left{width:auto}}</style>
</head><body>
<div class="topbar"><strong>Christube</strong><div><a href="index.php">Home</a><a href="uploads/index.php">My Uploads</a><a href="comments_timeline.php">Comments Timeline</a><a href="my_video_comments.php">My Video Comments</a><a href="buy_points.php">Buy Points</a><?php if (($_SESSION['username'] ?? "") === "Zesty"): ?><a href="admin_verify_points.php">Verify Points</a><?php endif; ?><a href="logout.php">Logout</a></div></div>
<div class="page">
<aside class="left"><div class="panel"><h3>Promoted Videos</h3><?php if(!$ads): ?><p class="muted">No active promotions.</p><?php else: ?><?php foreach($ads as $ad): ?><div style="margin-bottom:8px;border-bottom:1px solid #7a0000;padding-bottom:6px;"><a href="v.php?s=<?php echo urlencode($ad['slug']); ?>"><?php echo htmlspecialchars($ad['title']); ?></a><div class="tiny">by <?php echo htmlspecialchars($ad['username']); ?></div></div><?php endforeach; ?><?php endif; ?></div></aside>
<main class="main">
<?php if ($flash): ?><div class="flash"><?php echo htmlspecialchars($flash['msg']); ?></div><?php endif; ?>
<div class="panel"><h1>@<?php echo htmlspecialchars($profileUser['username']); ?></h1><p class="muted"><?php echo nl2br(htmlspecialchars($profileUser['bio'] ?: 'No bio yet.')); ?></p><p class="muted">Level <?php echo $level; ?> · XP <?php echo $xp; ?> / <?php echo $nextLevelXp; ?> · Followers: <?php echo $followers; ?> · Following: <?php echo $following; ?></p><?php if($isOwnProfile): ?><p><a href="edit_profile.php">Edit profile</a></p><?php else: ?><form action="follow.php" method="post"><input type="hidden" name="user_id" value="<?php echo $profileUserId; ?>"><input type="hidden" name="action" value="<?php echo $isFollowing ? 'unfollow' : 'follow'; ?>"><button type="submit"><?php echo $isFollowing ? 'Unfollow' : 'Follow'; ?></button></form><?php endif; ?></div>
<div class="panel"><h2>Uploaded videos</h2><?php if(!$uploadedVideos): ?><p class="muted">No uploads.</p><?php else: ?><ul><?php foreach($uploadedVideos as $v): ?><li><a href="v.php?s=<?php echo urlencode($v['slug']); ?>"><?php echo htmlspecialchars($v['title']); ?></a> (<?php echo htmlspecialchars($v['visibility']); ?>)</li><?php endforeach; ?></ul><?php endif; ?></div>
<div class="panel"><h2>Liked videos</h2><?php if(!$likedVideos): ?><p class="muted">No liked videos yet.</p><?php else: ?><ul><?php foreach($likedVideos as $v): ?><li><a href="v.php?s=<?php echo urlencode($v['slug']); ?>"><?php echo htmlspecialchars($v['title']); ?></a> by <a href="profile.php?u=<?php echo urlencode($v['username']); ?>"><?php echo htmlspecialchars($v['username']); ?></a></li><?php endforeach; ?></ul><?php endif; ?></div>
<div class="panel"><h2>Suggested creators to follow</h2><?php if(!$suggestions): ?><p class="muted">No suggestions yet.</p><?php else: ?><ul><?php foreach($suggestions as $s): ?><li><a href="profile.php?u=<?php echo urlencode($s['username']); ?>">@<?php echo htmlspecialchars($s['username']); ?></a> <span class="tiny">(public uploads: <?php echo (int)$s['uploads']; ?>)</span></li><?php endforeach; ?></ul><?php endif; ?></div>
</main>
</div></body></html>
