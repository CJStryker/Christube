<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';
$slug = trim((string)($_GET['p'] ?? ''));
if ($slug === '') { header('Location: playlists.php'); exit; }
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$stmt = $pdo->prepare('SELECT p.*, u.username FROM playlists p INNER JOIN users u ON u.id=p.user_id WHERE p.slug=? LIMIT 1');
$stmt->execute([$slug]);
$playlist = $stmt->fetch();
if (!$playlist) { http_response_code(404); exit('Playlist not found'); }
if ($playlist['visibility'] === 'private' && $currentUserId !== (int)$playlist['user_id']) { http_response_code(403); exit('Private playlist'); }
$items = $pdo->prepare("SELECT v.slug,v.title,v.uploaded_at,v.duration_seconds,v.user_id,u.username,ta.id AS thumbnail_asset_id
FROM playlist_videos pv INNER JOIN videos v ON v.id=pv.video_id INNER JOIN users u ON u.id=v.user_id LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id
WHERE pv.playlist_id=? AND v.processing_status='ready' AND (v.visibility='public' OR v.user_id=?) ORDER BY pv.position_index ASC, pv.id ASC");
$items->execute([(int)$playlist['id'], $currentUserId]);
$videos = $items->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo e($playlist['title']); ?></title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php renderTopbar('Christube', ['index.php'=>'Home','playlists.php'=>'My Playlists','search.php'=>'Search']); ?>
<div class="page"><main class="main"><div class="panel"><h1><?php echo e($playlist['title']); ?></h1><p class="muted">By <a href="profile.php?u=<?php echo urlencode($playlist['username']); ?>"><?php echo e($playlist['username']); ?></a> · <?php echo e($playlist['visibility']); ?></p><p><?php echo nl2br(e((string)$playlist['description'])); ?></p></div>
<div class="panel"><?php if(!$videos): ?><p class="muted">No videos in playlist yet.</p><?php else: ?><?php foreach($videos as $v): ?><div class="related-row"><?php renderRelatedRow($v); ?><?php if ($currentUserId === (int)$playlist['user_id']): ?><form method="post" action="playlist_save.php"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="remove_video"><input type="hidden" name="playlist_id" value="<?php echo (int)$playlist['id']; ?>"><input type="hidden" name="video_slug" value="<?php echo e($v['slug']); ?>"><button>Remove</button></form><?php endif; ?></div><?php endforeach; ?><?php endif; ?></div></main></div></body></html>
