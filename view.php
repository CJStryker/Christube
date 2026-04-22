<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';

$slug = trim($_GET['v'] ?? '');
if ($slug === '') { header('Location: index.php'); exit; }
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$playlistSlug = trim((string)($_GET['list'] ?? ''));

$stmt = $pdo->prepare("SELECT v.id, v.user_id, v.slug, v.title, v.description, v.visibility, v.uploaded_at, v.processing_status, v.processing_error,
    v.playback_asset_id, v.thumbnail_asset_id, v.duration_seconds, u.username,
    SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes,
    SUM(CASE WHEN vr.reaction='dislike' THEN 1 ELSE 0 END) AS dislikes,
    COALESCE(vc.views,0) AS views
FROM videos v INNER JOIN users u ON u.id=v.user_id
LEFT JOIN video_reactions vr ON vr.video_id=v.id
LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
WHERE v.slug=? GROUP BY v.id");
$stmt->execute([$slug]);
$video = $stmt->fetch();
if (!$video) { http_response_code(404); exit('Video not found'); }
if (!canUserAccessVideo($video, $currentUserId)) {
    http_response_code(($video['visibility'] ?? '') === 'private' ? 403 : 404);
    exit((($video['visibility'] ?? '') === 'private') ? 'This video is private.' : 'Video is still processing.');
}

recordVideoView($pdo, (int)$video['id'], $currentUserId ?: null);
trackProductEvent($pdo, 'watch_start', $currentUserId ?: null, ['video_id'=>(int)$video['id']]);

$playbackUrl = (int)$video['playback_asset_id'] > 0 ? mediaAssetUrl((int)$video['playback_asset_id']) : '';
$thumbUrl = (int)$video['thumbnail_asset_id'] > 0 ? mediaAssetUrl((int)$video['thumbnail_asset_id']) : '';

$userReaction = null;
if ($currentUserId > 0) {
    $reactionStmt = $pdo->prepare('SELECT reaction FROM video_reactions WHERE video_id = ? AND user_id = ?');
    $reactionStmt->execute([(int)$video['id'], $currentUserId]);
    $userReaction = $reactionStmt->fetchColumn() ?: null;
}

$commentsStmt = $pdo->prepare("SELECT c.id, c.user_id, c.comment, c.created_at, u.username FROM video_comments c INNER JOIN users u ON u.id = c.user_id WHERE c.video_id = ? ORDER BY c.created_at DESC LIMIT 120");
$commentsStmt->execute([(int)$video['id']]);
$comments = $commentsStmt->fetchAll();

$related = personalizedRelated($pdo, $currentUserId, (int)$video['id'], 12);
$ads = getActiveVideoAds($pdo, 6);
$flash = pullFlash();

$followerCountStmt = $pdo->prepare('SELECT COUNT(*) FROM user_follows WHERE followed_id=?');
$followerCountStmt->execute([(int)$video['user_id']]);
$creatorFollowers = (int)$followerCountStmt->fetchColumn();
$isFollowingCreator = false;
if ($currentUserId > 0 && $currentUserId !== (int)$video['user_id']) {
    $f = $pdo->prepare('SELECT id FROM user_follows WHERE follower_id=? AND followed_id=?');
    $f->execute([$currentUserId, (int)$video['user_id']]);
    $isFollowingCreator = (bool)$f->fetch();
}

$playlistContext = null;
$playlistItems = [];
if ($playlistSlug !== '') {
    $plist = $pdo->prepare('SELECT * FROM playlists WHERE slug=? LIMIT 1');
    $plist->execute([$playlistSlug]);
    $playlistContext = $plist->fetch();
    if ($playlistContext && (($playlistContext['visibility'] === 'public' || $playlistContext['visibility'] === 'unlisted') || $currentUserId === (int)$playlistContext['user_id'])) {
        $itemsStmt = $pdo->prepare("SELECT v.slug,v.title,v.uploaded_at,u.username,ta.id AS thumbnail_asset_id
            FROM playlist_videos pv INNER JOIN videos v ON v.id=pv.video_id INNER JOIN users u ON u.id=v.user_id
            LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id
            WHERE pv.playlist_id=? AND v.processing_status='ready' AND (v.visibility='public' OR v.user_id=?) ORDER BY pv.position_index ASC,pv.id ASC");
        $itemsStmt->execute([(int)$playlistContext['id'], $currentUserId]);
        $playlistItems = $itemsStmt->fetchAll();
    }
}

$myPlaylists = [];
if ($currentUserId > 0) {
    $mp = $pdo->prepare('SELECT id,title FROM playlists WHERE user_id=? ORDER BY updated_at DESC LIMIT 30');
    $mp->execute([$currentUserId]);
    $myPlaylists = $mp->fetchAll();
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo e($video['title']); ?> - Christube</title><link rel="stylesheet" href="public/styles.css"><style>.player-wrap video{width:100%;max-height:65vh;border-radius:8px;background:#000}.related-row{display:flex;gap:10px;margin-bottom:12px}.related-thumb{width:140px;height:80px;object-fit:cover;border-radius:8px}.playlist-box{max-height:260px;overflow:auto}.kbd{background:#000;padding:2px 5px;border-radius:4px}</style></head><body>
<?php
$nav=['index.php'=>'Home','search.php'=>'Search','trending.php'=>'Trending'];
if ($currentUserId>0) { $nav=['index.php'=>'Home','subscriptions.php'=>'Subscriptions','history.php'=>'History','playlists.php'=>'Playlists','search.php'=>'Search','creator/dashboard.php'=>'Creator Studio','profile.php?u='.urlencode($_SESSION['username'])=>'Profile']; }
renderTopbar('Christube',$nav);
?>
<div class="page"><aside class="left"><?php renderPromotedSidebar($pdo); ?></aside><main class="main">
<?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?>
<div class="panel player-wrap"><h1><?php echo e($video['title']); ?></h1>
<?php if($playbackUrl): ?><video id="watchPlayer" controls preload="metadata" src="<?php echo e($playbackUrl); ?>" poster="<?php echo e($thumbUrl); ?>"></video><?php else: ?><p class="meta">Playback not ready. <?php echo e((string)$video['processing_error']); ?></p><?php endif; ?>
<p class="meta">By <a href="profile.php?u=<?php echo urlencode($video['username']); ?>">@<?php echo e($video['username']); ?></a> · <?php echo e($video['uploaded_at']); ?> · 👁 <?php echo (int)$video['views']; ?> views · Duration <?php echo (int)round((float)$video['duration_seconds']); ?>s</p>
<p><?php echo nl2br(e((string)$video['description'])); ?></p>
<div>
<?php if($currentUserId>0): ?>
<form action="react.php" method="post" style="display:inline-block"><?php echo csrfInput(); ?><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><input type="hidden" name="reaction" value="like"><button><?php echo $userReaction==='like'?'Liked ✓':'Like'; ?> (<?php echo (int)$video['likes']; ?>)</button></form>
<form action="react.php" method="post" style="display:inline-block"><?php echo csrfInput(); ?><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><input type="hidden" name="reaction" value="dislike"><button><?php echo $userReaction==='dislike'?'Disliked ✓':'Dislike'; ?> (<?php echo (int)$video['dislikes']; ?>)</button></form>
<?php if($currentUserId !== (int)$video['user_id']): ?><form action="follow.php" method="post" style="display:inline-block"><?php echo csrfInput(); ?><input type="hidden" name="user_id" value="<?php echo (int)$video['user_id']; ?>"><input type="hidden" name="action" value="<?php echo $isFollowingCreator?'unfollow':'follow'; ?>"><button><?php echo $isFollowingCreator?'Unfollow':'Follow'; ?> Creator (<?php echo $creatorFollowers; ?>)</button></form><?php endif; ?>
<form action="playlist_save.php" method="post" style="display:inline-block"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="add_video"><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><select name="playlist_id"><option value="0">Watch Later</option><?php foreach($myPlaylists as $pl): ?><option value="<?php echo (int)$pl['id']; ?>"><?php echo e($pl['title']); ?></option><?php endforeach; ?></select><button>Save</button></form>
<form action="report_video.php" method="post" style="display:inline-block"><?php echo csrfInput(); ?><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><select name="reason"><option value="spam">Spam</option><option value="abuse">Abuse</option><option value="copyright">Copyright</option><option value="other">Other</option></select><button>Report</button></form>
<?php else: ?><p class="tiny"><a href="login.php">Log in</a> to react, follow creators, comment, and save to playlists.</p><?php endif; ?>
</div>
<p class="tiny">Shortcuts: <span class="kbd">Space</span> play/pause · <span class="kbd">←/→</span> seek ±5s · <span class="kbd">f</span> fullscreen · <span class="kbd">m</span> mute</p>
</div>

<?php if($playlistContext): ?><div class="panel"><h3>Playlist: <?php echo e($playlistContext['title']); ?></h3><div class="playlist-box"><?php if(!$playlistItems): ?><p class="tiny">No playlist items visible.</p><?php else: ?><?php foreach($playlistItems as $item){ renderRelatedRow($item);} ?><?php endif; ?></div></div><?php endif; ?>

<div class="panel"><h2>Comments (<?php echo count($comments); ?>)</h2>
<?php if($currentUserId>0): ?><form action="comment.php" method="post"><?php echo csrfInput(); ?><input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>"><textarea name="comment" rows="4" maxlength="2000" required></textarea><p><button>Post Comment</button></p></form><?php else: ?><p>Please <a href="login.php">login</a> to comment.</p><?php endif; ?>
<?php if(!$comments): ?><p>No comments yet.</p><?php else: ?><?php foreach($comments as $comment){ renderCommentItem($comment, (int)$video['user_id']); } ?><?php endif; ?></div>
</main>
<aside class="right"><div class="panel"><h3>Related Videos</h3><?php if(!$related): ?><p class="tiny">No related videos yet.</p><?php else: ?><?php foreach($related as $r){ renderRelatedRow($r);} ?><?php endif; ?></div></aside></div>
<?php if($currentUserId>0): ?><script>
const player=document.getElementById('watchPlayer');
if(player){
  const key='resume_<?php echo (int)$video['id']; ?>';
  const saved=Number(localStorage.getItem(key)||'0'); if(saved>5) player.currentTime=saved;
  const sendProgress=()=>{const fd=new FormData();fd.append('csrf_token','<?php echo e(csrfToken()); ?>');fd.append('video_id','<?php echo (int)$video['id']; ?>');fd.append('position_seconds',String(player.currentTime||0));fd.append('duration_seconds',String(player.duration||0));fetch('history_update.php',{method:'POST',body:fd});};
  player.addEventListener('timeupdate',()=>{localStorage.setItem(key,String(player.currentTime||0));if(Math.floor(player.currentTime)%15===0){sendProgress();}});
  player.addEventListener('ended',()=>{localStorage.removeItem(key);sendProgress();});
  player.addEventListener('error',()=>{console.warn('Playback failed gracefully');});
  document.addEventListener('keydown',(e)=>{if(!player)return;if(e.target.tagName==='TEXTAREA'||e.target.tagName==='INPUT')return; if(e.key===' '){e.preventDefault(); player.paused?player.play():player.pause();} if(e.key==='ArrowRight')player.currentTime+=5; if(e.key==='ArrowLeft')player.currentTime=Math.max(0,player.currentTime-5); if(e.key==='f') player.requestFullscreen?.(); if(e.key==='m') player.muted=!player.muted;});
}
</script><?php endif; ?>
</body></html>
