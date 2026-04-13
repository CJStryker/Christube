<?php
require_once 'config.php';
requireLogin();

$stmt = $pdo->prepare('SELECT tx_hash, amount_xmr, requested_xp, status, admin_note, created_at, processed_at FROM xmr_point_requests WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([(int)$_SESSION['user_id']]);
$rows = $stmt->fetchAll();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Point Requests</title>
<style>body{margin:0;background:#050505;color:#00ff66;font-family:Arial}.wrap{max-width:1000px;margin:24px auto;padding:0 14px}.panel{background:#101010;border:1px solid #7a0000;border-radius:10px;padding:14px;margin-bottom:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #7a0000;padding:8px;text-align:left}a{color:#00ff66}.flash{padding:10px;border:1px solid #7a0000;background:#1c1c1c;border-radius:8px;margin-bottom:12px}</style></head><body>
<div class="wrap">
<?php if($flash): ?><div class="flash"><?php echo htmlspecialchars($flash['msg']); ?></div><?php endif; ?>
<div class="panel"><h1>My XMR Point Requests</h1><p><a href="buy_points.php">Submit new request</a> · <a href="index.php">Home</a></p>
<?php if(!$rows): ?><p>No requests yet.</p><?php else: ?><table><tr><th>TX</th><th>XMR</th><th>XP</th><th>Status</th><th>Admin Note</th><th>Created</th><th>Processed</th></tr><?php foreach($rows as $r): ?><tr><td><?php echo htmlspecialchars(substr($r['tx_hash'],0,16)); ?>...</td><td><?php echo htmlspecialchars($r['amount_xmr']); ?></td><td><?php echo (int)$r['requested_xp']; ?></td><td><?php echo htmlspecialchars($r['status']); ?></td><td><?php echo htmlspecialchars((string)$r['admin_note']); ?></td><td><?php echo htmlspecialchars($r['created_at']); ?></td><td><?php echo htmlspecialchars((string)$r['processed_at']); ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
</div></body></html>
