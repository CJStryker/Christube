<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';

$current = currentUser();
$currentUserId = $current ? (int)$current['id'] : 0;
$currentUsername = $current['username'] ?? '';

$latest = getLatestVideos($pdo, 16);
$trending = getTrendingVideos($pdo, 12);
$fromSubscriptions = [];
$continueWatching = [];
if ($currentUserId > 0) {
    $subStmt = $pdo->prepare("SELECT v.slug,v.title,v.uploaded_at,v.duration_seconds,u.username,ta.id AS thumbnail_asset_id,COALESCE(vc.views,0) AS views,
SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes
FROM user_follows f INNER JOIN videos v ON v.user_id=f.followed_id INNER JOIN users u ON u.id=v.user_id
LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id LEFT JOIN video_reactions vr ON vr.video_id=v.id
LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
WHERE f.follower_id=? AND v.processing_status='ready' AND v.visibility='public' GROUP BY v.id ORDER BY v.uploaded_at DESC LIMIT 12");
    $subStmt->execute([$currentUserId]);
    $fromSubscriptions = $subStmt->fetchAll();

    $histStmt = $pdo->prepare("SELECT v.slug,v.title,v.uploaded_at,v.duration_seconds,u.username,ta.id AS thumbnail_asset_id,
        COALESCE(vc.views,0) AS views,
        SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes,
        h.last_position_seconds
        FROM watch_history h INNER JOIN videos v ON v.id=h.video_id INNER JOIN users u ON u.id=v.user_id
        LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id LEFT JOIN video_reactions vr ON vr.video_id=v.id
        LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
        WHERE h.user_id=? AND v.processing_status='ready' AND (v.visibility='public' OR v.user_id=?)
        GROUP BY v.id ORDER BY h.watched_at DESC LIMIT 8");
    $histStmt->execute([$currentUserId, $currentUserId]);
    $continueWatching = $histStmt->fetchAll();
}

trackProductEvent($pdo, 'homepage_impression', $currentUserId ?: null, ['latest_count'=>count($latest),'trending_count'=>count($trending)]);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Christube</title><link rel="stylesheet" href="public/styles.css"><style>.section-head{display:flex;justify-content:space-between;align-items:center}.video-card .thumb{width:100%;max-height:170px;object-fit:cover;border-radius:8px}.related-row{display:flex;gap:10px;align-items:flex-start}.related-thumb{width:140px;height:80px;object-fit:cover;border-radius:8px}</style></head><body>
<?php
$nav = ['search.php'=>'Search','trending.php'=>'Trending'];
if ($currentUserId > 0) {
    $nav = ['index.php'=>'Home','subscriptions.php'=>'Subscriptions','history.php'=>'History','playlists.php'=>'Playlists','search.php'=>'Search','trending.php'=>'Trending','profile.php?u='.urlencode($currentUsername)=>'Profile'];
}
renderTopbar('Christube', $nav);
if ($currentUserId > 0) {
    echo '<form method="post" action="logout.php" style="display:inline-block; margin:8px 16px;">' . csrfInput() . '<button type="submit">Logout</button></form>';
} else {
    echo '<div style="margin:8px 16px"><a href="login.php">Login</a> · <a href="register.php">Create account</a></div>';
}
?>
<div class="page"><?php renderPromotedSidebar($pdo); ?><main class="main">
<?php renderFlashBlock(); ?>
<?php if ($currentUserId > 0): ?><div class="panel"><h2>Upload a video</h2><form id="chunkUploadForm" action="upload.php" method="post" enctype="multipart/form-data"><?php echo csrfInput(); ?><label>Title</label><input type="text" name="title" maxlength="150" required><label>Description</label><textarea name="description" rows="3" maxlength="2000"></textarea><label>Privacy</label><select name="visibility" required><option value="public">Public</option><option value="private">Private</option></select><label>Video file</label><input type="file" name="videoFile" required><button>Upload Video</button></form><p id="uploadStatus" class="tiny"></p></div><?php endif; ?>
<div class="panel"><?php renderSectionHeader('Latest Videos'); ?><div class="grid"><?php foreach($latest as $v){ renderVideoCard($v);} ?></div></div>
<div class="panel"><?php renderSectionHeader('Trending', 'trending.php'); ?><?php if(!$trending): ?><p class="muted">No trending videos yet.</p><?php else: ?><div class="grid"><?php foreach($trending as $v){ renderVideoCard($v);} ?></div><?php endif; ?></div>
<?php if($currentUserId>0): ?><div class="panel"><?php renderSectionHeader('From Subscriptions', 'subscriptions.php'); ?><?php if(!$fromSubscriptions): ?><p class="muted">Follow creators to personalize this section.</p><?php else: ?><div class="grid"><?php foreach($fromSubscriptions as $v){ renderVideoCard($v);} ?></div><?php endif; ?></div>
<div class="panel"><?php renderSectionHeader('Continue Watching', 'history.php'); ?><?php if(!$continueWatching): ?><p class="muted">Watch videos to build your continue-watching rail.</p><?php else: ?><div class="grid"><?php foreach($continueWatching as $v){ renderVideoCard($v);} ?></div><?php endif; ?></div><?php endif; ?>
</main></div>
<?php if ($currentUserId > 0): ?><script>
const form=document.getElementById('chunkUploadForm');const statusEl=document.getElementById('uploadStatus');
form?.addEventListener('submit',async(e)=>{e.preventDefault();const file=form.videoFile.files[0];if(!file)return;const csrf=form.querySelector('input[name="csrf_token"]').value;
const startBody=new FormData();startBody.append('csrf_token',csrf);startBody.append('title',form.title.value);startBody.append('description',form.description.value);startBody.append('visibility',form.visibility.value);startBody.append('total_bytes',String(file.size));startBody.append('original_name',file.name);
statusEl.textContent='Starting upload session...';const start=await (await fetch('upload_start.php',{method:'POST',body:startBody})).json();if(!start.ok){statusEl.textContent=start.error||'Failed';return;}
const chunkSize=Number(start.chunk_bytes||5*1024*1024),totalChunks=Math.ceil(file.size/chunkSize);
for(let i=0;i<totalChunks;i++){const begin=i*chunkSize;const chunk=file.slice(begin,Math.min(file.size,begin+chunkSize));const chunkBody=new FormData();chunkBody.append('csrf_token',csrf);chunkBody.append('session_id',start.session_id);chunkBody.append('chunk_index',String(i));chunkBody.append('total_chunks',String(totalChunks));chunkBody.append('chunk',chunk,file.name+'.part'+i);const cj=await (await fetch('upload_chunk.php',{method:'POST',body:chunkBody})).json();if(!cj.ok){statusEl.textContent=cj.error||'Chunk failed';return;}statusEl.textContent=`Uploaded chunk ${i+1} / ${totalChunks}`;}
const doneBody=new FormData();doneBody.append('csrf_token',csrf);doneBody.append('session_id',start.session_id);const done=await (await fetch('upload_finalize.php',{method:'POST',body:doneBody})).json();if(!done.ok){statusEl.textContent=done.error||'Finalize failed';return;}window.location.href='uploads/index.php';
});
</script><?php endif; ?>
</body></html>
