<?php
require_once dirname(__DIR__) . '/config.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];

$stmt = $pdo->prepare(
    "SELECT v.id, v.slug, v.title, v.description, v.file_path, v.visibility, v.uploaded_at,
            SUM(CASE WHEN vr.reaction = 'like' THEN 1 ELSE 0 END) AS likes,
            SUM(CASE WHEN vr.reaction = 'dislike' THEN 1 ELSE 0 END) AS dislikes
     FROM videos v
     LEFT JOIN video_reactions vr ON vr.video_id = v.id
     WHERE v.user_id = ?
     GROUP BY v.id
     ORDER BY v.uploaded_at DESC"
);
$stmt->execute([$currentUserId]);
$videos = $stmt->fetchAll();
$ads = getActiveVideoAds($pdo, 8);

$userStmt = $pdo->prepare('SELECT experience_points FROM users WHERE id = ?');
$userStmt->execute([$currentUserId]);
$xp = (int)$userStmt->fetchColumn();
$level = getLevelFromXp($xp);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>My Uploads - Christube</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#050505;color:#00ff66}.topbar{background:#7a0000;color:#0a0a0a;padding:14px 20px;display:flex;justify-content:space-between;align-items:center}.topbar a{color:#0a0a0a;text-decoration:none;margin-left:12px;font-weight:bold}.page{display:flex;max-width:1400px;margin:24px auto;padding:0 16px;gap:16px}.left-ads{width:250px}.main{flex:1;min-width:0}.panel{background:#101010;border-radius:10px;border:1px solid #7a0000;padding:16px;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}video{width:100%;border-radius:8px;background:#000;max-height:220px}.muted{color:#87fcb0;font-size:14px}.tiny{font-size:12px;color:#87fcb0}input,select{width:100%;box-sizing:border-box;padding:10px;margin-top:6px;margin-bottom:12px;border:1px solid #7a0000;border-radius:6px;background:#000;color:#00ff66}button{background:#7a0000;color:#0a0a0a;border:0;border-radius:6px;padding:10px 14px;cursor:pointer;font-weight:bold}a{color:#00ff66}.flash{padding:10px;border:1px solid #7a0000;background:#1c1c1c;border-radius:8px;margin-bottom:12px}
@media (max-width:1000px){.page{display:block}.left-ads{width:auto}}
</style>
</head>
<body>
<div class="topbar"><strong>Christube</strong><div>Logged in as <strong><a style="color:#0a0a0a" href="../profile.php?u=<?php echo urlencode($currentUsername); ?>"><?php echo htmlspecialchars($currentUsername); ?></a></strong><a href="../index.php">Home</a><a href="../logout.php">Logout</a></div></div>
<div class="page">
<aside class="left-ads"><div class="panel"><h3>Promoted Videos</h3><?php if(!$ads): ?><p class="muted">No active promotions.</p><?php else: ?><?php foreach($ads as $ad): ?><div style="margin-bottom:10px;border-bottom:1px solid #7a0000;padding-bottom:8px;"><a href="../v.php?s=<?php echo urlencode($ad['slug']); ?>"><?php echo htmlspecialchars($ad['title']); ?></a><div class="tiny">by <?php echo htmlspecialchars($ad['username']); ?></div></div><?php endforeach; ?><?php endif; ?></div></aside>
<main class="main">
<?php if ($flash): ?><div class="flash"><?php echo htmlspecialchars($flash['msg']); ?></div><?php endif; ?>
<div class="panel"><h2>My uploaded videos</h2><p class="muted">Level <?php echo $level; ?> · XP balance: <?php echo $xp; ?>. Spend XP to promote videos in left sidebar ads.</p>
<?php if (!$videos): ?><p class="muted">You have not uploaded videos yet.</p><?php else: ?><div class="grid"><?php foreach ($videos as $video): ?><div class="panel" style="margin:0;">
<video controls preload="metadata" src="../<?php echo htmlspecialchars($video['file_path']); ?>"></video>
<h3><?php echo htmlspecialchars($video['title']); ?></h3>
<p class="muted"><?php echo nl2br(htmlspecialchars($video['description'] ?? '')); ?></p>
<p class="tiny">Uploaded: <?php echo htmlspecialchars($video['uploaded_at']); ?></p>
<p class="tiny">Visibility: <strong><?php echo htmlspecialchars($video['visibility']); ?></strong></p>
<p class="tiny">👍 <?php echo (int)$video['likes']; ?> · 👎 <?php echo (int)$video['dislikes']; ?></p>
<p><a href="../v.php?s=<?php echo urlencode($video['slug']); ?>">Watch page</a></p>
<form action="../promote_video.php" method="post">
    <input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>">
    <label>Spend XP on ad campaign (min 10)</label>
    <input type="number" name="xp_spend" min="10" step="1" value="20" required>
    <button type="submit">Promote This Video</button>
</form>
<form action="../update_visibility.php" method="post"><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><select name="visibility"><option value="public" <?php echo $video['visibility'] === 'public' ? 'selected' : ''; ?>>Public</option><option value="private" <?php echo $video['visibility'] === 'private' ? 'selected' : ''; ?>>Private</option></select><button type="submit">Update privacy</button></form>
</div><?php endforeach; ?></div><?php endif; ?></div>
</main>
</div>
</body>
</html>
