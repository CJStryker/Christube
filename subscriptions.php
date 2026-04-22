<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';
requireLogin();
$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT v.slug,v.title,v.uploaded_at,v.duration_seconds,u.username,ta.id AS thumbnail_asset_id,COALESCE(vc.views,0) AS views,
SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes
FROM user_follows f INNER JOIN videos v ON v.user_id=f.followed_id INNER JOIN users u ON u.id=v.user_id
LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id LEFT JOIN video_reactions vr ON vr.video_id=v.id
LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
WHERE f.follower_id=? AND v.processing_status='ready' AND v.visibility='public'
GROUP BY v.id ORDER BY v.uploaded_at DESC LIMIT 60");
$stmt->execute([$userId]);
$videos=$stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Subscriptions</title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php renderTopbar('Christube',['index.php'=>'Home','history.php'=>'History','playlists.php'=>'Playlists','search.php'=>'Search']); ?>
<div class="page"><main class="main"><div class="panel"><h1>Subscriptions Feed</h1><?php if(!$videos): ?><p class="muted">Follow creators to populate this feed.</p><?php else: ?><div class="grid"><?php foreach($videos as $v){ renderVideoCard($v);} ?></div><?php endif; ?></div></main></div></body></html>
