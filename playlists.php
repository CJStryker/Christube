<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';
requireLogin();
$userId = (int)$_SESSION['user_id'];
$rows = $pdo->prepare('SELECT p.*, COUNT(pv.video_id) AS video_count FROM playlists p LEFT JOIN playlist_videos pv ON pv.playlist_id=p.id WHERE p.user_id=? GROUP BY p.id ORDER BY p.updated_at DESC');
$rows->execute([$userId]);
$playlists = $rows->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Playlists</title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php renderTopbar('Christube', ['index.php'=>'Home','history.php'=>'History','subscriptions.php'=>'Subscriptions','search.php'=>'Search']); ?>
<div class="page"><main class="main"><div class="panel"><h1>My Playlists</h1><form action="playlist_save.php" method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="create"><label>Title</label><input name="title" required><label>Description</label><textarea name="description"></textarea><label>Visibility</label><select name="visibility"><option value="private">Private</option><option value="public">Public</option><option value="unlisted">Unlisted</option></select><button>Create Playlist</button></form></div>
<div class="panel"><?php if(!$playlists): ?><p class="muted">No playlists yet.</p><?php else: ?><ul><?php foreach($playlists as $p): ?><li><a href="playlist.php?p=<?php echo urlencode($p['slug']); ?>"><?php echo e($p['title']); ?></a> (<?php echo (int)$p['video_count']; ?> videos · <?php echo e($p['visibility']); ?>)</li><?php endforeach; ?></ul><?php endif; ?></div></main></div></body></html>
