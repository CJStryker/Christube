<?php
require_once '../includes/layout.php';
requireLogin();
$userId=(int)$_SESSION['user_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 handleMutation(['requireAuth'=>true,'rateBucket'=>'promotion_request','onErrorRedirect'=>'creator/promotions.php'], function() use($pdo,$userId): void {
   $videoId=(int)($_POST['video_id'] ?? 0);$xp=(int)($_POST['xp_spend'] ?? 0);
   if($videoId<1 || $xp<10) throw new RuntimeException('Invalid promotion request.');
   $v=$pdo->prepare("SELECT id,title FROM videos WHERE id=? AND user_id=? AND processing_status='ready' AND is_archived=0 LIMIT 1");$v->execute([$videoId,$userId]);$video=$v->fetch();
   if(!$video) throw new RuntimeException('Only ready owned videos can be promoted.');
   $active=$pdo->prepare("SELECT id FROM video_ads WHERE video_id=? AND user_id=? AND active_until>=NOW() LIMIT 1");$active->execute([$videoId,$userId]);
   if($active->fetch()) throw new RuntimeException('Video already has an active promotion.');
   if(!spendExperience($pdo,$userId,$xp,'video_ad_campaign')) throw new RuntimeException('Insufficient XP.');
   $hours=max(12,min(240,$xp*2));$activeUntil=(new DateTime())->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
   $pdo->prepare('INSERT INTO video_ads (video_id,user_id,points_spent,active_until) VALUES (?,?,?,?)')->execute([$videoId,$userId,$xp,$activeUntil]);
   auditEvent($pdo,$userId,'promotion_created',['video_id'=>$videoId,'xp_spend'=>$xp]);
   notifyUser($pdo,$userId,'promotion_status','Promotion activated','Your promotion for "'.$video['title'].'" is active for '.$hours.' hours.','video',$videoId,'promotion-active-'.$videoId);
   setFlash(true,'Promotion activated.'); header('Location: promotions.php'); exit;
 });
}
$videos=$pdo->prepare("SELECT id,title FROM videos WHERE user_id=? AND processing_status='ready' AND is_archived=0 ORDER BY uploaded_at DESC LIMIT 100");$videos->execute([$userId]);$videos=$videos->fetchAll();
$history=$pdo->prepare("SELECT a.*,v.title,v.slug FROM video_ads a INNER JOIN videos v ON v.id=a.video_id WHERE a.user_id=? ORDER BY a.created_at DESC LIMIT 200");$history->execute([$userId]);$history=$history->fetchAll();
$flash=pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Promotions</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Creator Studio', creatorNav('../')); ?><div class="page"><main class="main"><?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?><div class="panel"><h1>Promotions</h1><p class="tiny">Spend XP to boost ready public videos in promoted sidebars.</p><form method="post"><?php echo csrfInput(); ?><label>Video</label><select name="video_id" required><?php foreach($videos as $v): ?><option value="<?php echo (int)$v['id']; ?>"><?php echo e($v['title']); ?></option><?php endforeach; ?></select><label>XP Spend (min 10)</label><input type="number" name="xp_spend" min="10" value="20" required><button>Start Promotion</button></form></div><div class="panel"><h2>Promotion History</h2><?php if(!$history): ?><p class="muted">No promotions yet.</p><?php else: ?><table><tr><th>Video</th><th>XP</th><th>Active Until</th><th>Status</th></tr><?php foreach($history as $h): ?><tr><td><a href="../v.php?s=<?php echo urlencode($h['slug']); ?>"><?php echo e($h['title']); ?></a></td><td><?php echo (int)$h['points_spent']; ?></td><td><?php echo e($h['active_until']); ?></td><td><?php echo strtotime($h['active_until']) >= time() ? 'Active' : 'Ended'; ?></td></tr><?php endforeach; ?></table><?php endif; ?></div></main></div></body></html>
