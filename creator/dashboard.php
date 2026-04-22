<?php
require_once '../includes/layout.php';
require_once '../includes/components.php';
requireLogin();
$userId = (int)$_SESSION['user_id'];
$stats = getCreatorOverviewStats($pdo, $userId);
$analytics = getCreatorAnalytics($pdo, $userId, '7d');
$elig = creatorEligibility($pdo, $userId);

$recentComments = $pdo->prepare("SELECT c.comment,c.created_at,u.username,v.slug,v.title FROM video_comments c INNER JOIN videos v ON v.id=c.video_id INNER JOIN users u ON u.id=c.user_id WHERE v.user_id=? ORDER BY c.created_at DESC LIMIT 5");
$recentComments->execute([$userId]);
$recentComments = $recentComments->fetchAll();

$recentFollowers = $pdo->prepare("SELECT u.username,f.created_at FROM user_follows f INNER JOIN users u ON u.id=f.follower_id WHERE f.followed_id=? ORDER BY f.created_at DESC LIMIT 5");
$recentFollowers->execute([$userId]);
$recentFollowers = $recentFollowers->fetchAll();

$myTop = [];
foreach ($analytics['top_videos'] as $v) {
    $cardStmt = $pdo->prepare("SELECT v.slug,v.title,v.uploaded_at,v.duration_seconds,u.username,ta.id AS thumbnail_asset_id,? AS views,0 AS likes FROM videos v INNER JOIN users u ON u.id=v.user_id LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id WHERE v.id=?");
    $cardStmt->execute([(int)$v['views'], (int)$v['id']]);
    if ($row = $cardStmt->fetch()) $myTop[] = $row;
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Creator Dashboard</title><link rel="stylesheet" href="../public/styles.css"></head><body>
<?php renderTopbar('Creator Studio', creatorNav('../')); ?>
<div class="page"><main class="main">
<div class="panel"><h1>Dashboard Overview</h1><p class="muted">Welcome back, @<?php echo e($_SESSION['username']); ?>. Manage your channel, uploads, analytics, and promotions here.</p>
<p><a href="videos.php">Manage Videos</a> · <a href="analytics.php">Analytics</a> · <a href="promotions.php">Promotions</a> · <a href="sponsors.php">Sponsors</a> · <a href="monetization.php">Monetization</a> · <a href="settings.php">Channel Settings</a> · <a href="../index.php">Back to Site</a></p><p class="tiny">Creator tier: <?php echo e($elig['creator_rank']); ?> · EXP <?php echo (int)$elig['creator_exp']; ?> · Marketplace <?php echo $elig['marketplace_eligible']?'Eligible':'Locked'; ?> · Payout review <?php echo $elig['payout_review_eligible']?'Eligible':'Not yet'; ?></p></div>
<div class="grid">
<div class="panel"><h3>Total Videos</h3><p><?php echo (int)$stats['total_videos']; ?></p></div>
<div class="panel"><h3>Published</h3><p><?php echo (int)$stats['published_videos']; ?></p></div>
<div class="panel"><h3>Processing</h3><p><?php echo (int)$stats['processing_videos']; ?></p></div>
<div class="panel"><h3>Failed</h3><p><?php echo (int)$stats['failed_videos']; ?></p></div>
<div class="panel"><h3>Followers</h3><p><?php echo (int)$stats['followers']; ?></p></div>
<div class="panel"><h3>Unread Notifications</h3><p><?php echo (int)$stats['unread_notifications']; ?></p></div>
</div>
<div class="panel"><h2>Last 7 Days Performance</h2><p class="tiny">Views <?php echo (int)$analytics['views']; ?> · Watch starts <?php echo (int)$analytics['watch_starts']; ?> · Likes <?php echo (int)$analytics['likes']; ?> · Comments <?php echo (int)$analytics['comments']; ?> · New followers <?php echo (int)$analytics['follows_gained']; ?></p></div>
<div class="grid"><div class="panel"><h3>Recent Comments</h3><?php if(!$recentComments): ?><p class="muted">No comments yet.</p><?php else: ?><ul><?php foreach($recentComments as $c): ?><li><a href="../v.php?s=<?php echo urlencode($c['slug']); ?>"><?php echo e($c['title']); ?></a> — <?php echo e($c['username']); ?></li><?php endforeach; ?></ul><?php endif; ?><p><a href="comments.php">Open comments inbox</a></p></div>
<div class="panel"><h3>Recent Followers</h3><?php if(!$recentFollowers): ?><p class="muted">No new followers yet.</p><?php else: ?><ul><?php foreach($recentFollowers as $f): ?><li><a href="../profile.php?u=<?php echo urlencode($f['username']); ?>">@<?php echo e($f['username']); ?></a> · <?php echo e($f['created_at']); ?></li><?php endforeach; ?></ul><?php endif; ?></div></div>
<div class="panel"><h3>Next Best Actions</h3><ul><li><?php echo $stats['published_videos'] < 3 ? 'Publish more videos to improve discovery and EXP.' : 'Maintain your upload cadence for creator streak rewards.'; ?></li><li><?php echo $stats['unread_notifications'] > 0 ? 'Review unread notifications and engagement updates.' : 'Engage with comments to grow trust and EXP.'; ?></li><li><?php echo $elig['marketplace_eligible'] ? 'You can submit sponsor campaigns.' : 'Reach higher creator EXP to unlock sponsor marketplace.'; ?></li></ul></div><div class="panel"><h3>Top Videos This Week</h3><?php if(!$myTop): ?><p class="muted">No view data yet.</p><?php else: ?><div class="grid"><?php foreach($myTop as $v){ renderVideoCard($v);} ?></div><?php endif; ?></div>
</main></div></body></html>
