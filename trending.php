<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';
$videos = getTrendingVideos($pdo, 48);
trackProductEvent($pdo, 'trending_impression', isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, ['count'=>count($videos)]);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Trending</title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php renderTopbar('Christube',['index.php'=>'Home','search.php'=>'Search','subscriptions.php'=>'Subscriptions','history.php'=>'History']); ?>
<div class="page"><main class="main"><div class="panel"><h1>Trending</h1><?php if(!$videos): ?><p class="muted">No trending videos yet.</p><?php else: ?><div class="grid"><?php foreach($videos as $v){ renderVideoCard($v);} ?></div><?php endif; ?></div></main></div></body></html>
