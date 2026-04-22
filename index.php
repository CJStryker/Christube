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
    "SELECT v.id, v.user_id, v.slug, v.title, v.description, v.visibility, v.uploaded_at, v.processing_status,
            u.username,
            pa.id AS playback_asset_id,
            ta.id AS thumbnail_asset_id,
            SUM(CASE WHEN vr.reaction = 'like' THEN 1 ELSE 0 END) AS likes,
            SUM(CASE WHEN vr.reaction = 'dislike' THEN 1 ELSE 0 END) AS dislikes
     FROM videos v
     INNER JOIN users u ON u.id = v.user_id
     LEFT JOIN video_reactions vr ON vr.video_id = v.id
     LEFT JOIN media_assets pa ON pa.id = v.playback_asset_id
     LEFT JOIN media_assets ta ON ta.id = v.thumbnail_asset_id
     WHERE (v.visibility = 'public' AND v.processing_status = 'ready') OR v.user_id = ?
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
<div class="panel">
  <h2>Upload a video</h2>
  <form id="chunkUploadForm" action="upload.php" method="post" enctype="multipart/form-data"><?php echo csrfInput(); ?>
    <label>Title</label><input type="text" name="title" maxlength="150" required>
    <label>Description</label><textarea name="description" rows="3" maxlength="2000"></textarea>
    <label>Privacy</label><select name="visibility" required><option value="public">Public</option><option value="private">Private</option></select>
    <label>Video file</label><input type="file" name="videoFile" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov" required>
    <button type="submit" name="submit">Upload Video</button>
  </form>
  <p id="uploadStatus" class="tiny"></p>
</div>
<div class="panel"><h2>Recently uploaded videos</h2><?php if(!$videos): ?><p class="muted">No videos uploaded yet.</p><?php else: ?><div class="grid"><?php foreach($videos as $video): ?><div class="panel" style="margin:0;"><?php if ((int)$video['thumbnail_asset_id'] > 0): ?><img src="<?php echo e(mediaAssetUrl((int)$video['thumbnail_asset_id'])); ?>" alt="thumbnail" style="width:100%;border-radius:8px;max-height:170px;object-fit:cover;"><?php endif; ?><h3><?php echo e($video['title']); ?></h3><p class="muted"><?php echo nl2br(e($video['description'] ?? '')); ?></p><p class="tiny">By <a href="profile.php?u=<?php echo urlencode($video['username']); ?>"><?php echo e($video['username']); ?></a> · <?php echo e($video['uploaded_at']); ?></p><p class="tiny">Status: <strong><?php echo e($video['processing_status']); ?></strong></p><p class="tiny">👍 <?php echo (int)$video['likes']; ?> · 👎 <?php echo (int)$video['dislikes']; ?></p><p><a href="v.php?s=<?php echo urlencode($video['slug']); ?>">Watch page</a></p><?php if ((int)$video['user_id'] === $currentUserId): ?><form action="update_visibility.php" method="post"><?php echo csrfInput(); ?><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><select name="visibility"><option value="public" <?php echo $video['visibility']==='public'?'selected':''; ?>>Public</option><option value="private" <?php echo $video['visibility']==='private'?'selected':''; ?>>Private</option></select><button type="submit">Update privacy</button></form><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></div>
</main></div>
<script>
const form = document.getElementById('chunkUploadForm');
const statusEl = document.getElementById('uploadStatus');
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fileInput = form.querySelector('input[name="videoFile"]');
  const file = fileInput.files[0];
  if (!file) return;
  const csrf = form.querySelector('input[name="csrf_token"]').value;
  const startBody = new FormData();
  startBody.append('csrf_token', csrf);
  startBody.append('title', form.title.value);
  startBody.append('description', form.description.value);
  startBody.append('visibility', form.visibility.value);
  startBody.append('total_bytes', String(file.size));
  startBody.append('original_name', file.name);

  statusEl.textContent = 'Starting upload session...';
  const startRes = await fetch('upload_start.php', { method: 'POST', body: startBody });
  const start = await startRes.json();
  if (!start.ok) { statusEl.textContent = start.error || 'Failed to start upload'; return; }

  const chunkSize = Number(start.chunk_bytes || 5 * 1024 * 1024);
  const totalChunks = Math.ceil(file.size / chunkSize);
  for (let i = 0; i < totalChunks; i++) {
    const begin = i * chunkSize;
    const chunk = file.slice(begin, Math.min(file.size, begin + chunkSize));
    const chunkBody = new FormData();
    chunkBody.append('csrf_token', csrf);
    chunkBody.append('session_id', start.session_id);
    chunkBody.append('chunk_index', String(i));
    chunkBody.append('total_chunks', String(totalChunks));
    chunkBody.append('chunk', chunk, file.name + '.part' + i);
    const chunkRes = await fetch('upload_chunk.php', { method: 'POST', body: chunkBody });
    const chunkJson = await chunkRes.json();
    if (!chunkJson.ok) { statusEl.textContent = chunkJson.error || 'Chunk failed'; return; }
    statusEl.textContent = `Uploaded chunk ${i + 1} / ${totalChunks}`;
  }

  const doneBody = new FormData();
  doneBody.append('csrf_token', csrf);
  doneBody.append('session_id', start.session_id);
  const doneRes = await fetch('upload_finalize.php', { method: 'POST', body: doneBody });
  const done = await doneRes.json();
  if (!done.ok) { statusEl.textContent = done.error || 'Finalize failed'; return; }
  statusEl.textContent = 'Upload complete. Video is now processing.';
  window.location.href = 'uploads/index.php';
});
</script>
</body></html>
