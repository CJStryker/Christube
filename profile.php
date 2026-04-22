<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';

$username = strtolower(trim($_GET['u'] ?? ($_SESSION['username'] ?? '')));
if ($username === '') { header('Location: index.php'); exit; }
$userStmt = $pdo->prepare('SELECT id, username, display_name, bio, avatar_url, banner_url, role, account_status, profile_visibility, created_at, experience_points FROM users WHERE username = ?');
$userStmt->execute([$username]);
$profileUser = $userStmt->fetch();
if (!$profileUser) { http_response_code(404); exit('Profile not found'); }

$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$profileUserId = (int)$profileUser['id'];
$isOwnProfile = $currentUserId === $profileUserId;
if (($profileUser['profile_visibility'] ?? 'public') === 'private' && !$isOwnProfile) { http_response_code(403); exit('Profile is private.'); }

$uploadedStmt = $pdo->prepare("SELECT v.slug,v.title,v.uploaded_at,v.duration_seconds,u.username,ta.id AS thumbnail_asset_id,
COALESCE(vc.views,0) AS views,SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes
FROM videos v INNER JOIN users u ON u.id=v.user_id LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id
LEFT JOIN video_reactions vr ON vr.video_id=v.id LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
WHERE v.user_id=? AND v.processing_status='ready' AND (v.visibility='public' OR v.user_id=?) GROUP BY v.id ORDER BY v.uploaded_at DESC LIMIT 60");
$uploadedStmt->execute([$profileUserId, $currentUserId]);
$uploadedVideos = $uploadedStmt->fetchAll();

$playlistStmt = $pdo->prepare('SELECT slug,title,visibility,updated_at FROM playlists WHERE user_id=? AND (visibility IN (\'public\',\'unlisted\') OR user_id=?) ORDER BY updated_at DESC LIMIT 20');
$playlistStmt->execute([$profileUserId, $currentUserId]);
$playlists = $playlistStmt->fetchAll();

$followerCount = $pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE followed_id = ?');$followerCount->execute([$profileUserId]);$followers=(int)$followerCount->fetchColumn();
$followingCount = $pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE follower_id = ?');$followingCount->execute([$profileUserId]);$following=(int)$followingCount->fetchColumn();
$isFollowing=false;
if ($currentUserId>0 && !$isOwnProfile){$f=$pdo->prepare('SELECT id FROM user_follows WHERE follower_id=? AND followed_id=?');$f->execute([$currentUserId,$profileUserId]);$isFollowing=(bool)$f->fetch();}
$xp=(int)$profileUser['experience_points'];$level=getLevelFromXp($xp);$nextLevelXp=getXpForNextLevel($xp);
$flash=pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Channel - <?php echo e($profileUser['username']); ?></title><link rel="stylesheet" href="public/styles.css"><style>.channel-header img.avatar{width:90px;height:90px;border-radius:50%}</style></head><body>
<?php renderTopbar('Christube',['index.php'=>'Home','search.php'=>'Search','trending.php'=>'Trending','subscriptions.php'=>'Subscriptions','playlists.php'=>'Playlists']); ?>
<div class="page"><main class="main">
<?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?>
<div class="panel channel-header"><h1>@<?php echo e($profileUser['username']); ?></h1><?php if(!empty($profileUser['banner_url'])): ?><img src="<?php echo e($profileUser['banner_url']); ?>" alt="banner" style="max-width:100%;border-radius:8px"><?php endif; ?>
<p><?php if(!empty($profileUser['avatar_url'])): ?><img class="avatar" src="<?php echo e($profileUser['avatar_url']); ?>" alt="avatar"><?php endif; ?></p>
<p class="muted"><?php echo e((string)($profileUser['display_name'] ?: $profileUser['username'])); ?> · Followers <?php echo $followers; ?> · Following <?php echo $following; ?></p>
<p><?php echo nl2br(e((string)($profileUser['bio'] ?: 'No bio yet.'))); ?></p>
<p class="tiny">Level <?php echo $level; ?> · XP <?php echo $xp; ?> / <?php echo $nextLevelXp; ?></p>
<?php if($currentUserId>0 && !$isOwnProfile): ?><form action="follow.php" method="post"><?php echo csrfInput(); ?><input type="hidden" name="user_id" value="<?php echo $profileUserId; ?>"><input type="hidden" name="action" value="<?php echo $isFollowing?'unfollow':'follow'; ?>"><button><?php echo $isFollowing?'Unfollow':'Follow'; ?></button></form><?php elseif($isOwnProfile): ?><a href="edit_profile.php">Edit profile</a><?php endif; ?>
</div>
<div class="panel"><h2>Videos</h2><?php if(!$uploadedVideos): ?><p class="muted">No public videos yet.</p><?php else: ?><div class="grid"><?php foreach($uploadedVideos as $v){ renderVideoCard($v);} ?></div><?php endif; ?></div>
<div class="panel"><h2>Playlists</h2><?php if(!$playlists): ?><p class="muted">No playlists yet.</p><?php else: ?><ul><?php foreach($playlists as $p): ?><li><a href="playlist.php?p=<?php echo urlencode($p['slug']); ?>"><?php echo e($p['title']); ?></a> (<?php echo e($p['visibility']); ?>)</li><?php endforeach; ?></ul><?php endif; ?></div>
</main></div></body></html>
