<?php
require_once '../config.php';
requireLogin();
$userId=(int)$_SESSION['user_id'];
$videoId=(int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt=$pdo->prepare('SELECT * FROM videos WHERE id=? AND user_id=? LIMIT 1');$stmt->execute([$videoId,$userId]);$video=$stmt->fetch();
if(!$video){http_response_code(404);exit('Video not found');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 handleMutation(['requireAuth'=>true,'rateBucket'=>'creator_video_edit','onErrorRedirect'=>'creator/video_edit.php?id=' . $videoId], function() use($pdo,$videoId,$userId): void {
  $title=trim((string)($_POST['title'] ?? ''));$description=trim((string)($_POST['description'] ?? ''));$visibility=(string)($_POST['visibility'] ?? 'private');$archive=(int)($_POST['is_archived'] ?? 0);
  if($title===''||!in_array($visibility,['public','private','unlisted'],true)){throw new RuntimeException('Invalid metadata.');}
  $publishAt = null;
  if ($visibility==='public') { $publishAt = date('Y-m-d H:i:s'); }
  $pdo->prepare('UPDATE videos SET title=?, description=?, visibility=?, is_archived=?, published_at=COALESCE(published_at, ?) WHERE id=? AND user_id=?')->execute([$title,$description,$visibility,$archive,$publishAt,$videoId,$userId]);
  auditEvent($pdo,$userId,'video_updated',['video_id'=>$videoId,'visibility'=>$visibility,'is_archived'=>$archive]);
  trackProductEvent($pdo,'creator_video_updated',$userId,['video_id'=>$videoId]);
  setFlash(true,'Video updated.'); header('Location: videos.php'); exit;
 });
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Video</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Creator Studio', creatorNav('../')); ?><div class="auth-container" style="max-width:900px"><h1>Edit Video</h1><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="id" value="<?php echo (int)$videoId; ?>"><label>Title</label><input name="title" value="<?php echo e($video['title']); ?>" required><label>Description</label><textarea name="description" rows="6"><?php echo e((string)$video['description']); ?></textarea><label>Visibility</label><select name="visibility"><option value="public" <?php echo $video['visibility']==='public'?'selected':''; ?>>Public</option><option value="private" <?php echo $video['visibility']==='private'?'selected':''; ?>>Private</option><option value="unlisted" <?php echo $video['visibility']==='unlisted'?'selected':''; ?>>Unlisted</option></select><label><input type="checkbox" name="is_archived" value="1" <?php echo (int)$video['is_archived']===1?'checked':''; ?>> Archive video (keeps record but removes from public surfaces)</label><button>Save Changes</button></form><p><a href="videos.php">Back to videos</a></p></div></body></html>
