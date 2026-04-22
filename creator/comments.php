<?php
require_once '../includes/layout.php';
requireLogin();
$userId=(int)$_SESSION['user_id'];
$filter=trim((string)($_GET['filter'] ?? 'recent'));
if(!in_array($filter,['recent','reported'],true))$filter='recent';
if($_SERVER['REQUEST_METHOD']==='POST'){
  handleMutation(['requireAuth'=>true,'rateBucket'=>'creator_comment_action','onErrorRedirect'=>'creator/comments.php'], function() use($pdo,$userId): void {
    $commentId=(int)($_POST['comment_id'] ?? 0);
    $action=(string)($_POST['action'] ?? '');
    $own=$pdo->prepare('SELECT c.id FROM video_comments c INNER JOIN videos v ON v.id=c.video_id WHERE c.id=? AND v.user_id=? LIMIT 1');$own->execute([$commentId,$userId]);
    if(!$own->fetch()) throw new RuntimeException('Comment not owned by your channel.');
    if($action==='delete'){
      $pdo->prepare('DELETE FROM video_comments WHERE id=?')->execute([$commentId]);
      $pdo->prepare('INSERT INTO creator_comment_actions (creator_id,comment_id,action) VALUES (?,?,?)')->execute([$userId,$commentId,'delete']);
      setFlash(true,'Comment removed.');
    }
    header('Location: comments.php');exit;
  });
}
$sql="SELECT c.id,c.comment,c.created_at,cu.username AS commenter,v.title,v.slug,
      (SELECT COUNT(*) FROM video_reports vr WHERE vr.video_id=v.id) AS report_count
      FROM videos v INNER JOIN video_comments c ON c.video_id=v.id INNER JOIN users cu ON cu.id=c.user_id
      WHERE v.user_id=? ";
if($filter==='reported')$sql.=' AND (SELECT COUNT(*) FROM video_reports vr WHERE vr.video_id=v.id) > 0 ';
$sql.=' ORDER BY c.created_at DESC LIMIT 300';
$stmt=$pdo->prepare($sql);$stmt->execute([$userId]);$items=$stmt->fetchAll();$flash=pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Comments Inbox</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Creator Studio', creatorNav('../')); ?><div class="page"><main class="main"><?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?><div class="panel"><h1>Comments Inbox</h1><form method="get"><select name="filter"><option value="recent" <?php echo $filter==='recent'?'selected':''; ?>>Recent</option><option value="reported" <?php echo $filter==='reported'?'selected':''; ?>>On reported videos</option></select><button>Filter</button></form></div><?php if(!$items): ?><div class="panel"><p class="muted">No comments in this view.</p></div><?php else: ?><?php foreach($items as $i): ?><div class="panel"><p class="tiny"><a href="../v.php?s=<?php echo urlencode($i['slug']); ?>"><?php echo e($i['title']); ?></a> · reports: <?php echo (int)$i['report_count']; ?></p><p><?php echo nl2br(e($i['comment'])); ?></p><p class="tiny">By @<?php echo e($i['commenter']); ?> at <?php echo e($i['created_at']); ?></p><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="comment_id" value="<?php echo (int)$i['id']; ?>"><button name="action" value="delete">Remove Comment</button></form></div><?php endforeach; ?><?php endif; ?></main></div></body></html>
