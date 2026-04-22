<?php
require_once 'config.php';
requireLogin();

if (($_SESSION['username'] ?? '') !== 'Zesty') {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Only Zesty can verify point purchases.'];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['admin_note'] ?? '');

    $stmt = $pdo->prepare("SELECT id, user_id, requested_xp, status FROM xmr_point_requests WHERE id = ?");
    $stmt->execute([$id]);
    $req = $stmt->fetch();

    if (!$req || $req['status'] !== 'pending') {
        $_SESSION['flash'] = ['ok' => false, 'msg' => 'Request missing or already processed.'];
        header('Location: admin_verify_points.php');
        exit;
    }

    if ($action === 'approve') {
        addExperience($pdo, (int)$req['user_id'], (int)$req['requested_xp'], 'xmr_purchase_approved');
        $upd = $pdo->prepare("UPDATE xmr_point_requests SET status='approved', admin_note=?, processed_by=?, processed_at=NOW() WHERE id=?");
        $upd->execute([$note, (int)$_SESSION['user_id'], $id]);
    } elseif ($action === 'reject') {
        $upd = $pdo->prepare("UPDATE xmr_point_requests SET status='rejected', admin_note=?, processed_by=?, processed_at=NOW() WHERE id=?");
        $upd->execute([$note, (int)$_SESSION['user_id'], $id]);
    }

    $_SESSION['flash'] = ['ok' => true, 'msg' => 'Request processed.'];
    header('Location: admin_verify_points.php');
    exit;
}

$requests = $pdo->query("SELECT r.id, r.tx_hash, r.amount_xmr, r.requested_xp, r.status, r.created_at, u.username FROM xmr_point_requests r INNER JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC LIMIT 300")->fetchAll();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify Point Purchases</title>
<style>body{margin:0;background:#050505;color:#00ff66;font-family:Arial}.wrap{max-width:1100px;margin:24px auto;padding:0 14px}.panel{background:#101010;border:1px solid #7a0000;border-radius:10px;padding:14px;margin-bottom:12px}a{color:#00ff66}input,textarea{width:100%;background:#000;color:#00ff66;border:1px solid #7a0000;border-radius:6px;padding:8px;margin:6px 0}button{background:#7a0000;color:#0a0a0a;border:0;border-radius:6px;padding:8px 12px;font-weight:bold}.flash{padding:10px;border:1px solid #7a0000;background:#1c1c1c;border-radius:8px;margin-bottom:12px}</style></head><body><div class="wrap">
<?php if($flash): ?><div class="flash"><?php echo htmlspecialchars($flash['msg']); ?></div><?php endif; ?>
<div class="panel"><h1>Admin XMR Point Verification</h1><p><a href="index.php">Home</a></p></div>
<?php foreach($requests as $r): ?><div class="panel"><p><strong>#<?php echo (int)$r['id']; ?></strong> @<?php echo htmlspecialchars($r['username']); ?> · <?php echo htmlspecialchars($r['amount_xmr']); ?> XMR · <?php echo (int)$r['requested_xp']; ?> XP · <?php echo htmlspecialchars($r['status']); ?></p><p>TX: <?php echo htmlspecialchars($r['tx_hash']); ?></p><?php if($r['status']==='pending'): ?><form method="post"><input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>"><label>Admin note (optional)</label><textarea name="admin_note" rows="2"></textarea><button name="action" value="approve" type="submit">Approve + Credit XP</button> <button name="action" value="reject" type="submit">Reject</button></form><?php endif; ?></div><?php endforeach; ?>
</div></body></html>
