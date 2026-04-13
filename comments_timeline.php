<?php
require_once 'config.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];

$stmt = $pdo->prepare(
    "SELECT c.comment, c.created_at, cu.username AS commenter,
            v.slug, v.title, v.visibility, vu.username AS uploader
     FROM video_comments c
     INNER JOIN videos v ON v.id = c.video_id
     INNER JOIN users cu ON cu.id = c.user_id
     INNER JOIN users vu ON vu.id = v.user_id
     WHERE v.visibility = 'public' OR v.user_id = ?
     ORDER BY c.created_at DESC
     LIMIT 200"
);
$stmt->execute([$currentUserId]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Comments Timeline</title>
<style>body{margin:0;background:#050505;color:#00ff66;font-family:Arial}.topbar{background:#7a0000;color:#0a0a0a;padding:14px 20px;display:flex;justify-content:space-between}.topbar a{color:#0a0a0a;margin-left:12px;text-decoration:none;font-weight:bold}.wrap{max-width:1000px;margin:20px auto;padding:0 14px}.panel{background:#101010;border:1px solid #7a0000;border-radius:10px;padding:14px;margin-bottom:12px}.meta{color:#87fcb0;font-size:12px}a{color:#00ff66}</style>
</head><body><div class="topbar"><strong>Christube</strong><div>Logged in as <strong><a style="color:#0a0a0a" href="profile.php?u=<?php echo urlencode($currentUsername); ?>"><?php echo htmlspecialchars($currentUsername); ?></a></strong><a href="index.php">Home</a><a href="my_video_comments.php">Comments on My Videos</a><a href="logout.php">Logout</a></div></div>
<div class="wrap"><div class="panel"><h1>Comment Timeline</h1><p class="meta">All recent comments across visible videos.</p></div>
<?php if (!$items): ?><div class="panel"><p>No comments yet.</p></div><?php else: ?>
<?php foreach($items as $item): ?>
<div class="panel">
    <p class="meta">On video: <a href="v.php?s=<?php echo urlencode($item['slug']); ?>"><?php echo htmlspecialchars($item['title']); ?></a> by <a href="profile.php?u=<?php echo urlencode($item['uploader']); ?>"><?php echo htmlspecialchars($item['uploader']); ?></a></p>
    <p><?php echo nl2br(htmlspecialchars($item['comment'])); ?></p>
    <p class="meta">Comment by <a href="profile.php?u=<?php echo urlencode($item['commenter']); ?>"><?php echo htmlspecialchars($item['commenter']); ?></a> · <?php echo htmlspecialchars($item['created_at']); ?></p>
</div>
<?php endforeach; ?>
<?php endif; ?></div></body></html>
