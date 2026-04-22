<?php
require_once '../includes/layout.php';
requireLogin();
$userId=(int)$_SESSION['user_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 handleMutation(['requireAuth'=>true,'rateBucket'=>'notifications','onErrorRedirect'=>'creator/notifications.php'], function() use($pdo,$userId): void {
   $mode=(string)($_POST['mode'] ?? '');
   if($mode==='mark_all_read'){
      $pdo->prepare('UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0')->execute([$userId]);
      setFlash(true,'All notifications marked read.');
   } elseif($mode==='mark_read') {
      $id=(int)($_POST['notification_id'] ?? 0);
      $pdo->prepare('UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?')->execute([$id,$userId]);
      setFlash(true,'Notification marked read.');
   } elseif($mode==='save_prefs') {
      $pdo->prepare('INSERT INTO notification_preferences (user_id,in_app_comments,in_app_follows,in_app_processing,in_app_promotions) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE in_app_comments=VALUES(in_app_comments),in_app_follows=VALUES(in_app_follows),in_app_processing=VALUES(in_app_processing),in_app_promotions=VALUES(in_app_promotions)')
        ->execute([$userId,isset($_POST['in_app_comments'])?1:0,isset($_POST['in_app_follows'])?1:0,isset($_POST['in_app_processing'])?1:0,isset($_POST['in_app_promotions'])?1:0]);
      setFlash(true,'Notification preferences saved.');
   }
   header('Location: notifications.php');exit;
 });
}
$items=$pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 300');$items->execute([$userId]);$items=$items->fetchAll();
$prefs=$pdo->prepare('SELECT * FROM notification_preferences WHERE user_id=?');$prefs->execute([$userId]);$prefs=$prefs->fetch() ?: ['in_app_comments'=>1,'in_app_follows'=>1,'in_app_processing'=>1,'in_app_promotions'=>1];
$flash=pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notifications</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Creator Studio', creatorNav('../')); ?><div class="page"><main class="main"><?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?><div class="panel"><h1>Notifications</h1><form method="post" style="display:inline-block"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="mark_all_read"><button>Mark All Read</button></form></div><div class="panel"><h2>Preferences</h2><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="save_prefs"><label><input type="checkbox" name="in_app_comments" <?php echo (int)$prefs['in_app_comments']===1?'checked':''; ?>> Comments</label><label><input type="checkbox" name="in_app_follows" <?php echo (int)$prefs['in_app_follows']===1?'checked':''; ?>> Follows</label><label><input type="checkbox" name="in_app_processing" <?php echo (int)$prefs['in_app_processing']===1?'checked':''; ?>> Upload processing</label><label><input type="checkbox" name="in_app_promotions" <?php echo (int)$prefs['in_app_promotions']===1?'checked':''; ?>> Promotions</label><button>Save Preferences</button></form></div><div class="panel"><h2>Recent Notifications</h2><?php if(!$items): ?><p class="muted">No notifications yet.</p><?php else: ?><?php foreach($items as $n): ?><div class="panel" style="margin:8px 0;border-color:<?php echo (int)$n['is_read']===1?'#444':'#7a0000'; ?>"><p><strong><?php echo e($n['title']); ?></strong></p><p class="tiny"><?php echo e((string)$n['body']); ?></p><p class="tiny"><?php echo e($n['created_at']); ?> · <?php echo (int)$n['is_read']===1?'Read':'Unread'; ?></p><?php if((int)$n['is_read']===0): ?><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="mark_read"><input type="hidden" name="notification_id" value="<?php echo (int)$n['id']; ?>"><button>Mark Read</button></form><?php endif; ?></div><?php endforeach; ?><?php endif; ?></div></main></div></body></html>
