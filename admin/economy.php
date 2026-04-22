<?php
require_once '../includes/layout.php';
requireLogin();
requireAdminUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutation(['requireAuth'=>true,'rateBucket'=>'admin_economy','onErrorRedirect'=>'admin/economy.php'], function() use ($pdo): void {
        $mode=(string)($_POST['mode'] ?? '');
        if ($mode==='adjust_exp') {
            $userId=(int)($_POST['user_id'] ?? 0);$delta=(int)($_POST['delta'] ?? 0);$reason=trim((string)($_POST['reason'] ?? 'admin adjustment'));
            if ($userId<1 || $delta===0) throw new RuntimeException('Invalid adjustment.');
            awardExp($pdo,$userId,$delta,'admin_adjustment',$reason,'admin-adjust-'.$userId.'-'.time().'-'.rand(10,99),['admin'=>$_SESSION['username']],false,(int)$_SESSION['user_id']);
            auditEvent($pdo,(int)$_SESSION['user_id'],'exp_adjustment',['user_id'=>$userId,'delta'=>$delta,'reason'=>$reason]);
            setFlash(true,'EXP adjusted.');
        } elseif ($mode==='campaign_review') {
            $id=(int)($_POST['campaign_id'] ?? 0);$status=(string)($_POST['status'] ?? 'under_review');
            if(!in_array($status,['under_review','approved','rejected','active','completed','cancelled'],true)) throw new RuntimeException('Invalid status.');
            $pdo->prepare('UPDATE sponsor_campaigns SET review_status=?, notes=? WHERE id=?')->execute([$status,trim((string)($_POST['note'] ?? '')),$id]);
            setFlash(true,'Campaign status updated.');
        } elseif ($mode==='payout_review') {
            $id=(int)($_POST['review_id'] ?? 0);$status=(string)($_POST['status'] ?? 'pending');
            if(!in_array($status,['approved','rejected','held'],true)) throw new RuntimeException('Invalid payout status.');
            $row=$pdo->prepare('SELECT creator_user_id FROM payout_reviews WHERE id=?');$row->execute([$id]);$creator=(int)$row->fetchColumn();
            $pdo->prepare('UPDATE payout_reviews SET review_status=?, reviewer_user_id=?, reviewer_note=?, reviewed_at=NOW() WHERE id=?')->execute([$status,(int)$_SESSION['user_id'],trim((string)($_POST['note'] ?? '')),$id]);
            $map=['approved'=>'eligible','rejected'=>'not_eligible','held'=>'suspended'];
            $pdo->prepare('UPDATE monetization_profiles SET payout_status=?, monetization_status=? WHERE user_id=?')->execute([$map[$status],$map[$status],$creator]);
            notifyUser($pdo,$creator,'payout_status','Payout review updated','Your payout review status is now '.$status.'.','payout',$id,'payout-review-'.$id);
            setFlash(true,'Payout review updated.');
        }
        header('Location: economy.php');exit;
    });
}

$flash = pullFlash();
$ledger=$pdo->query('SELECT l.*,u.username FROM exp_ledger l INNER JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 200')->fetchAll();
$campaigns=$pdo->query('SELECT c.*,u.username AS requester FROM sponsor_campaigns c LEFT JOIN users u ON u.id=c.requester_user_id ORDER BY c.created_at DESC LIMIT 120')->fetchAll();
$payouts=$pdo->query('SELECT p.*,u.username FROM payout_reviews p INNER JOIN users u ON u.id=p.creator_user_id ORDER BY p.created_at DESC LIMIT 120')->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Economy Ops</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Admin Economy',['../index.php'=>'Home','ops.php'=>'Ops','economy.php'=>'Economy']); ?><div class="page"><main class="main"><?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?><div class="panel"><h1>EXP Ledger Controls</h1><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="adjust_exp"><label>User ID</label><input name="user_id" type="number" required><label>Delta EXP</label><input name="delta" type="number" required><label>Reason</label><input name="reason" required><button>Apply Adjustment</button></form></div><div class="panel"><h2>Recent EXP Ledger</h2><?php if(!$ledger): ?><p class="muted">No EXP entries yet.</p><?php else: ?><table><tr><th>Time</th><th>User</th><th>Event</th><th>Delta</th><th>Balance</th></tr><?php foreach($ledger as $l): ?><tr><td><?php echo e($l['created_at']); ?></td><td>@<?php echo e($l['username']); ?></td><td><?php echo e($l['event_code']); ?></td><td><?php echo (int)$l['exp_delta']; ?></td><td><?php echo (int)$l['balance_after']; ?></td></tr><?php endforeach; ?></table><?php endif; ?></div><div class="panel"><h2>Sponsor Campaign Review</h2><?php if(!$campaigns): ?><p class="muted">No campaigns.</p><?php else: ?><table><tr><th>Campaign</th><th>Requester</th><th>Status</th><th>Action</th></tr><?php foreach($campaigns as $c): ?><tr><td><?php echo e($c['title']); ?></td><td><?php echo e((string)$c['requester']); ?></td><td><?php echo e($c['review_status']); ?></td><td><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="campaign_review"><input type="hidden" name="campaign_id" value="<?php echo (int)$c['id']; ?>"><select name="status"><option>under_review</option><option>approved</option><option>rejected</option><option>active</option><option>completed</option><option>cancelled</option></select><input name="note" placeholder="note"><button>Update</button></form></td></tr><?php endforeach; ?></table><?php endif; ?></div><div class="panel"><h2>Payout Review Queue</h2><?php if(!$payouts): ?><p class="muted">No payout reviews.</p><?php else: ?><table><tr><th>Creator</th><th>Status</th><th>Action</th></tr><?php foreach($payouts as $p): ?><tr><td>@<?php echo e($p['username']); ?></td><td><?php echo e($p['review_status']); ?></td><td><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="payout_review"><input type="hidden" name="review_id" value="<?php echo (int)$p['id']; ?>"><select name="status"><option value="approved">approved</option><option value="rejected">rejected</option><option value="held">held</option></select><input name="note" placeholder="note"><button>Update</button></form></td></tr><?php endforeach; ?></table><?php endif; ?></div></main></div></body></html>
