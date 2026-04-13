<?php
require_once 'config.php';

$slug = trim($_GET['v'] ?? '');
if ($slug === '') {
    header('Location: index.php');
    exit;
}

$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$stmt = $pdo->prepare(
    "SELECT v.id, v.user_id, v.slug, v.title, v.description, v.file_path, v.visibility, v.uploaded_at, u.username,
            SUM(CASE WHEN vr.reaction = 'like' THEN 1 ELSE 0 END) AS likes,
            SUM(CASE WHEN vr.reaction = 'dislike' THEN 1 ELSE 0 END) AS dislikes
     FROM videos v
     INNER JOIN users u ON u.id = v.user_id
     LEFT JOIN video_reactions vr ON vr.video_id = v.id
     WHERE v.slug = ?
     GROUP BY v.id"
);
$stmt->execute([$slug]);
$video = $stmt->fetch();
if (!$video) {
    http_response_code(404);
    echo 'Video not found';
    exit;
}
if ($video['visibility'] === 'private' && $currentUserId !== (int)$video['user_id']) {
    http_response_code(403);
    echo 'This video is private.';
    exit;
}

$userReaction = null;
if ($currentUserId > 0) {
    $reactionStmt = $pdo->prepare('SELECT reaction FROM video_reactions WHERE video_id = ? AND user_id = ?');
    $reactionStmt->execute([(int)$video['id'], $currentUserId]);
    $userReaction = $reactionStmt->fetchColumn() ?: null;
}

$commentsStmt = $pdo->prepare("SELECT c.comment, c.created_at, u.username FROM video_comments c INNER JOIN users u ON u.id = c.user_id WHERE c.video_id = ? ORDER BY c.created_at DESC");
$commentsStmt->execute([(int)$video['id']]);
$comments = $commentsStmt->fetchAll();

$recommendStmt = $pdo->prepare(
    "SELECT slug, title, uploaded_at
     FROM videos
     WHERE visibility = 'public' AND id != ?
     ORDER BY uploaded_at DESC
     LIMIT 12"
);
$recommendStmt->execute([(int)$video['id']]);
$recommended = $recommendStmt->fetchAll();

$ads = getActiveVideoAds($pdo, 6);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo htmlspecialchars($video['title']); ?> - Christube</title>
<style>body{margin:0;background:#050505;color:#00ff66;font-family:Arial,sans-serif}.topbar{background:#7a0000;color:#0a0a0a;padding:12px 18px;display:flex;justify-content:space-between}.topbar a{color:#0a0a0a;margin-left:12px;text-decoration:none;font-weight:bold}.page{display:flex;max-width:1500px;margin:20px auto;padding:0 14px;gap:16px}.left{width:230px}.main{flex:1;min-width:0}.right{width:300px}.panel{background:#101010;border:1px solid #7a0000;border-radius:10px;padding:14px;margin-bottom:16px}video{width:100%;background:#000;border-radius:8px}textarea{width:100%;background:#000;color:#00ff66;border:1px solid #7a0000;border-radius:6px;padding:10px}button{background:#7a0000;color:#0a0a0a;border:none;padding:8px 12px;border-radius:6px;font-weight:bold;cursor:pointer}.flash{padding:10px;border-radius:6px;margin-bottom:12px;background:#1c1c1c;border:1px solid #7a0000}.meta{font-size:13px;color:#89ffb3}a{color:#00ff66}.donation-box{background:#140000;border:1px solid #7a0000;border-radius:8px;padding:12px;margin-bottom:14px}.donation-box code{display:block;margin-top:8px;padding:8px;background:#000;color:#00ff66;overflow-wrap:anywhere}.tiny{font-size:12px;color:#89ffb3}@media (max-width:1100px){.page{display:block}.left,.right{width:auto}}</style>
</head><body>
<div class="topbar"><strong>Christube</strong><div><a href="index.php">Home</a><?php if ($currentUserId>0): ?><a href="profile.php?u=<?php echo urlencode($_SESSION['username']); ?>">Profile</a><a href="logout.php">Logout</a><?php else: ?><a href="login.php">Login</a><?php endif; ?></div></div>
<div class="page">
<aside class="left"><div class="panel"><h3>Promoted Videos</h3><?php if(!$ads): ?><p class="tiny">No active promotions.</p><?php else: ?><?php foreach($ads as $ad): ?><div style="margin-bottom:8px;border-bottom:1px solid #7a0000;padding-bottom:6px;"><a href="v.php?s=<?php echo urlencode($ad['slug']); ?>"><?php echo htmlspecialchars($ad['title']); ?></a><div class="tiny">by <?php echo htmlspecialchars($ad['username']); ?></div></div><?php endforeach; ?><?php endif; ?></div></aside>
<main class="main">
<?php if ($flash): ?><div class="flash"><?php echo htmlspecialchars($flash['msg']); ?></div><?php endif; ?>
<div class="donation-box"><strong>Support Christube</strong><p class="meta">Donation notice: we currently only accept Monero (XMR).</p><code>86KNpUKopsJTFUj72PQoLYX7xpsKMiyd6G5BKYoG65FaKzUQqf4jqLaS6LPUjh8cq5MQTsQh3V2hVRQSqp8j4JGL4Xf9cvq</code></div>
<div class="panel"><h1><?php echo htmlspecialchars($video['title']); ?></h1><video controls preload="metadata" src="<?php echo htmlspecialchars($video['file_path']); ?>"></video><p><?php echo nl2br(htmlspecialchars($video['description'] ?? '')); ?></p><p class="meta">By <a href="profile.php?u=<?php echo urlencode($video['username']); ?>"><?php echo htmlspecialchars($video['username']); ?></a> · <?php echo htmlspecialchars($video['uploaded_at']); ?></p><p class="meta">Likes: <?php echo (int)$video['likes']; ?> · Dislikes: <?php echo (int)$video['dislikes']; ?></p><p class="meta">Share link: <a href="v.php?s=<?php echo urlencode($video['slug']); ?>">v.php?s=<?php echo htmlspecialchars($video['slug']); ?></a></p><?php if ($currentUserId>0): ?><form action="react.php" method="post" style="display:inline-block;margin-right:8px;"><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><input type="hidden" name="reaction" value="like"><button type="submit"><?php echo $userReaction==='like'?'Liked ✓':'Like'; ?></button></form><form action="react.php" method="post" style="display:inline-block;"><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><input type="hidden" name="reaction" value="dislike"><button type="submit"><?php echo $userReaction==='dislike'?'Disliked ✓':'Dislike'; ?></button></form><?php endif; ?></div>
<div class="panel"><h2>Comments</h2><?php if ($currentUserId>0): ?><form action="comment.php" method="post"><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><textarea name="comment" rows="4" maxlength="2000" required></textarea><p><button type="submit">Post Comment</button></p></form><?php else: ?><p>Please login to comment.</p><?php endif; ?><?php if(!$comments): ?><p>No comments yet.</p><?php else: ?><?php foreach($comments as $comment): ?><div class="panel" style="margin:10px 0;"><p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p><p class="meta">— <a href="profile.php?u=<?php echo urlencode($comment['username']); ?>"><?php echo htmlspecialchars($comment['username']); ?></a> at <?php echo htmlspecialchars($comment['created_at']); ?></p></div><?php endforeach; ?><?php endif; ?></div>
</main>
<aside class="right"><div class="panel"><h3>More videos to watch</h3><?php if(!$recommended): ?><p class="tiny">No recommendations yet.</p><?php else: ?><?php foreach($recommended as $rec): ?><div style="margin-bottom:10px;border-bottom:1px solid #7a0000;padding-bottom:8px;"><a href="v.php?s=<?php echo urlencode($rec['slug']); ?>"><?php echo htmlspecialchars($rec['title']); ?></a><div class="tiny"><?php echo htmlspecialchars($rec['uploaded_at']); ?></div></div><?php endforeach; ?><?php endif; ?></div></aside>
</div>
</body></html>
