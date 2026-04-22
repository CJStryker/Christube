<?php
require_once 'config.php';
requireLogin();

if (($_SESSION['username'] ?? '') !== 'Zesty') {
    setFlash(false, 'Only Zesty can verify point purchases.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutation([
        'requireAuth' => true,
        'rateBucket' => 'admin_verify_points',
        'onErrorRedirect' => 'admin_verify_points.php',
    ], function () use ($pdo): void {
        $id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        $action = $_POST['action'] ?? '';
        $note = trim($_POST['admin_note'] ?? '');

        $stmt = $pdo->prepare('SELECT id, user_id, requested_xp, status FROM xmr_point_requests WHERE id = ?');
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req || $req['status'] !== 'pending') {
            throw new RuntimeException('Request missing or already processed.');
        }

        if ($action === 'approve') {
            addExperience($pdo, (int)$req['user_id'], (int)$req['requested_xp'], 'xmr_purchase_approved');
            $upd = $pdo->prepare("UPDATE xmr_point_requests SET status='approved', admin_note=?, processed_by=?, processed_at=NOW() WHERE id=?");
            $upd->execute([$note, (int)$_SESSION['user_id'], $id]);
            notifyUser($pdo, (int)$req['user_id'], 'promotion_status', 'Point purchase approved', 'Your XMR point request was approved.', 'xmr_request', $id, 'xmr-approved-' . $id);
        } elseif ($action === 'reject') {
            $upd = $pdo->prepare("UPDATE xmr_point_requests SET status='rejected', admin_note=?, processed_by=?, processed_at=NOW() WHERE id=?");
            $upd->execute([$note, (int)$_SESSION['user_id'], $id]);
            notifyUser($pdo, (int)$req['user_id'], 'promotion_status', 'Point purchase rejected', 'Your XMR point request was rejected.', 'xmr_request', $id, 'xmr-rejected-' . $id);
        } else {
            throw new RuntimeException('Invalid action.');
        }

        setFlash(true, 'Request processed.');
        header('Location: admin_verify_points.php');
        exit;
    });
}

$requests = $pdo->query("SELECT r.id, r.tx_hash, r.amount_xmr, r.requested_xp, r.status, r.created_at, u.username FROM xmr_point_requests r INNER JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC LIMIT 300")->fetchAll();
$flash = pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify Point Purchases</title><link rel="stylesheet" href="public/styles.css"></head><body>
<div class="auth-container" style="max-width:1100px;">
<?php if($flash): ?><div class="<?php echo $flash['ok'] ? 'success' : 'error'; ?>"><?php echo e((string)$flash['msg']); ?></div><?php endif; ?>
<h1>Admin XMR Point Verification</h1><p><a href="index.php">Home</a> · <a href="admin/ops.php">Admin Ops</a></p>
<?php foreach($requests as $r): ?><div class="panel"><p><strong>#<?php echo (int)$r['id']; ?></strong> @<?php echo e($r['username']); ?> · <?php echo e((string)$r['amount_xmr']); ?> XMR · <?php echo (int)$r['requested_xp']; ?> XP · <?php echo e($r['status']); ?></p><p>TX: <?php echo e($r['tx_hash']); ?></p><?php if($r['status']==='pending'): ?><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>"><label>Admin note (optional)</label><textarea name="admin_note" rows="2"></textarea><button name="action" value="approve" type="submit">Approve + Credit XP</button> <button name="action" value="reject" type="submit">Reject</button></form><?php endif; ?></div><?php endforeach; ?>
</div></body></html>
