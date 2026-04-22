<?php
require_once '../includes/layout.php';
requireLogin();
$userId=(int)$_SESSION['user_id'];
$videoId=(int)($_GET['id'] ?? 0);
$v=$pdo->prepare('SELECT id,slug,title FROM videos WHERE id=? AND user_id=? LIMIT 1');$v->execute([$videoId,$userId]);$video=$v->fetch();
if(!$video){http_response_code(404);exit('Video not found');}
$views=(int)$pdo->query('SELECT COUNT(*) FROM video_views WHERE video_id=' . $videoId)->fetchColumn();
$likes=(int)$pdo->query("SELECT COUNT(*) FROM video_reactions WHERE video_id={$videoId} AND reaction='like'")->fetchColumn();
$comments=(int)$pdo->query('SELECT COUNT(*) FROM video_comments WHERE video_id=' . $videoId)->fetchColumn();
$progress=$pdo->query("SELECT AVG(last_position_seconds) AS avg_watch, AVG(CASE WHEN duration_seconds>0 THEN LEAST(1,last_position_seconds/duration_seconds) ELSE 0 END) AS completion FROM watch_history WHERE video_id={$videoId}")->fetch();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Video Analytics</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Creator Studio', creatorNav('../')); ?><div class="auth-container" style="max-width:900px"><h1>Video Analytics: <?php echo e($video['title']); ?></h1><p><a href="../v.php?s=<?php echo urlencode($video['slug']); ?>">Open watch page</a> · <a href="videos.php">Back to videos</a></p><div class="panel"><p>Views: <?php echo $views; ?></p><p>Likes: <?php echo $likes; ?></p><p>Comments: <?php echo $comments; ?></p><p>Avg watch seconds: <?php echo number_format((float)($progress['avg_watch'] ?? 0),1); ?></p><p>Completion rate: <?php echo number_format(((float)($progress['completion'] ?? 0))*100,1); ?>%</p><p class="tiny">Traffic source breakdown placeholder hooks will use future event payload enrichments.</p></div></div></body></html>
