<?php
require_once 'includes/layout.php';
requireLogin();

$current = currentUser();
$currentUserId = (int)$current['id'];
$currentUsername = $current['username'];

$userStmt = $pdo->prepare('SELECT experience_points FROM users WHERE id = ?');
$userStmt->execute([$currentUserId]);
$xp = (int)$userStmt->fetchColumn();
$level = getLevelFromXp($xp);
$nextLevelXp = getXpForNextLevel($xp);

$stmt = $pdo->prepare(
    "SELECT v.id, v.user_id, v.slug, v.title, v.description, v.file_path, v.visibility, v.uploaded_at, u.username,
            SUM(CASE WHEN vr.reaction = 'like' THEN 1 ELSE 0 END) AS likes,
            SUM(CASE WHEN vr.reaction = 'dislike' THEN 1 ELSE 0 END) AS dislikes
     FROM videos v
     INNER JOIN users u ON u.id = v.user_id
     LEFT JOIN video_reactions vr ON vr.video_id = v.id
     WHERE v.visibility = 'public' OR v.user_id = ?
     GROUP BY v.id
     ORDER BY v.uploaded_at DESC
     LIMIT 30"
);
$stmt->execute([$currentUserId]);
$videos = $stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Christube</title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php
$navLinks = [
    'profile.php?u=' . urlencode($currentUsername) => 'Profile',
    'uploads/index.php' => 'My Uploads',
    'comments_timeline.php' => 'Comments Timeline',
    'my_video_comments.php' => 'My Video Comments',
    'buy_points.php' => 'Buy Points',
];
if ($currentUsername === 'Zesty') {
    $navLinks['admin_verify_points.php'] = 'Verify Points';
}
renderTopbar('Christube', $navLinks);
?>
<form method="post" action="logout.php" style="display:inline-block; margin:8px 16px;"><?php echo csrfInput(); ?><button type="submit">Logout</button></form>
<div class="page">
<?php renderPromotedSidebar($pdo); ?>
<main class="main">
<?php renderFlashBlock(); ?>
<div class="panel"><h3>Your Progress</h3><p class="muted">Level <?php echo $level; ?> · XP: <?php echo $xp; ?> / <?php echo $nextLevelXp; ?> to next level.</p><p class="tiny">Earn XP by uploading (+25), commenting (+5), reacting (+2). Spend XP on promotions.</p></div>
<div class="panel"><h2>Upload a video</h2><form action="upload.php" method="post" enctype="multipart/form-data"><?php echo csrfInput(); ?><label>Title</label><input type="text" name="title" maxlength="150" required><label>Description</label><textarea name="description" rows="3" maxlength="2000"></textarea><label>Privacy</label><select name="visibility" required><option value="public">Public</option><option value="private">Private</option></select><label>Video file</label><input type="file" name="videoFile" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov" required><button type="submit" name="submit">Upload Video</button></form></div>
<div class="panel"><h2>Recently uploaded videos</h2><?php if(!$videos): ?><p class="muted">No videos uploaded yet.</p><?php else: ?><div class="grid"><?php foreach($videos as $video): ?><div class="panel" style="margin:0;"><video controls preload="metadata" src="<?php echo e($video['file_path']); ?>"></video><h3><?php echo e($video['title']); ?></h3><p class="muted"><?php echo nl2br(e($video['description'] ?? '')); ?></p><p class="tiny">By <a href="profile.php?u=<?php echo urlencode($video['username']); ?>"><?php echo e($video['username']); ?></a> · <?php echo e($video['uploaded_at']); ?></p><p class="tiny">👍 <?php echo (int)$video['likes']; ?> · 👎 <?php echo (int)$video['dislikes']; ?></p><p><a href="v.php?s=<?php echo urlencode($video['slug']); ?>">Watch page</a></p><?php if ((int)$video['user_id'] === $currentUserId): ?><form action="update_visibility.php" method="post"><?php echo csrfInput(); ?><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><select name="visibility"><option value="public" <?php echo $video['visibility']==='public'?'selected':''; ?>>Public</option><option value="private" <?php echo $video['visibility']==='private'?'selected':''; ?>>Private</option></select><button type="submit">Update privacy</button></form><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></div>
</main></div>
</body></html>
