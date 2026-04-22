<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';
$q = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'relevance');
$results = [];
if ($q !== '') {
    $results = searchVideosAndChannels($pdo, $q, $sort, 80);
    trackProductEvent($pdo, 'search_performed', isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, ['query'=>$q,'sort'=>$sort,'count'=>count($results)]);
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Search</title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php renderTopbar('Christube',['index.php'=>'Home','trending.php'=>'Trending','subscriptions.php'=>'Subscriptions','playlists.php'=>'Playlists']); ?>
<div class="page"><main class="main"><div class="panel"><h1>Search</h1><form><input name="q" value="<?php echo e($q); ?>" placeholder="Search videos or creators"><select name="sort"><option value="relevance" <?php echo $sort==='relevance'?'selected':''; ?>>Relevance</option><option value="newest" <?php echo $sort==='newest'?'selected':''; ?>>Newest</option><option value="popular" <?php echo $sort==='popular'?'selected':''; ?>>Popular</option></select><button type="submit">Search</button></form></div>
<div class="panel"><?php if($q===''): ?><p class="muted">Search for videos and creators.</p><?php elseif(!$results): ?><p class="muted">No matching results.</p><?php else: ?><div class="grid"><?php foreach($results as $v){ renderVideoCard($v);} ?></div><?php endif; ?></div></main></div></body></html>
