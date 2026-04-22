<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';
requireLogin();
$userId = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    $videoId = (int)($_POST['video_id'] ?? 0);
    $pdo->prepare('DELETE FROM watch_history WHERE user_id=? AND video_id=?')->execute([$userId,$videoId]);
}
$stmt = $pdo->prepare("SELECT h.last_position_seconds,h.duration_seconds,h.watched_at,v.id,v.slug,v.title,v.uploaded_at,u.username,ta.id AS thumbnail_asset_id
FROM watch_history h INNER JOIN videos v ON v.id=h.video_id INNER JOIN users u ON u.id=v.user_id LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id
WHERE h.user_id=? AND v.processing_status='ready' AND (v.visibility='public' OR v.user_id=?) ORDER BY h.watched_at DESC");
$stmt->execute([$userId,$userId]);
$items = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>History</title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php renderTopbar('Christube', ['index.php'=>'Home','subscriptions.php'=>'Subscriptions','playlists.php'=>'Playlists','search.php'=>'Search']); ?>
<div class="page"><main class="main"><div class="panel"><h1>Watch History</h1><?php if(!$items): ?><p class="muted">Nothing watched yet.</p><?php else: ?><?php foreach($items as $v): ?><div class="related-row"><?php renderRelatedRow($v); ?><div class="tiny">Progress: <?php echo (int)$v['last_position_seconds']; ?>s / <?php echo (int)$v['duration_seconds']; ?>s</div><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="video_id" value="<?php echo (int)$v['id']; ?>"><button>Remove</button></form></div><?php endforeach; ?><?php endif; ?></div></main></div></body></html>
