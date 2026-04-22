<?php
require_once '../includes/layout.php';
requireLogin();
requireAdminUser();

$reportFilter = trim((string)($_GET['report_status'] ?? 'open'));
if (!in_array($reportFilter, ['open', 'all'], true)) $reportFilter = 'open';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutation([
        'requireAuth' => true,
        'rateBucket' => 'admin_ops',
        'onErrorRedirect' => 'admin/ops.php',
    ], function () use ($pdo): void {
        $mode = (string)($_POST['mode'] ?? '');
        if ($mode === 'mark_report_reviewed') {
            $id = (int)($_POST['report_id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM video_reports WHERE id=?')->execute([$id]);
                auditEvent($pdo, (int)$_SESSION['user_id'], 'report_reviewed', ['report_id' => $id]);
            }
            setFlash(true, 'Report marked reviewed.');
        }
        header('Location: ops.php');
        exit;
    });
}

$counters = [
    'pending_reports' => (int)$pdo->query('SELECT COUNT(*) FROM video_reports')->fetchColumn(),
    'failed_processing' => (int)$pdo->query("SELECT COUNT(*) FROM videos WHERE processing_status='failed'")->fetchColumn(),
    'pending_xmr' => (int)$pdo->query("SELECT COUNT(*) FROM xmr_point_requests WHERE status='pending'")->fetchColumn(),
    'new_users_7d' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'uploads_7d' => (int)$pdo->query("SELECT COUNT(*) FROM videos WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
];

$reportsSql = "SELECT r.id,r.reason,r.details,r.created_at,v.id AS video_id,v.title,v.slug,u.username AS reporter,owner.username AS owner_name
    FROM video_reports r
    INNER JOIN videos v ON v.id=r.video_id
    INNER JOIN users u ON u.id=r.user_id
    INNER JOIN users owner ON owner.id=v.user_id";
if ($reportFilter === 'open') $reportsSql .= ' ORDER BY r.created_at DESC LIMIT 200';
$reports = $pdo->query($reportsSql)->fetchAll();

$audit = $pdo->query("SELECT id,event_type,payload_json,created_at FROM audit_logs ORDER BY created_at DESC LIMIT 120")->fetchAll();
$flash = pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Ops</title><link rel="stylesheet" href="../public/styles.css"></head><body>
<?php renderTopbar('Admin Ops', ['../index.php'=>'Home','../admin_verify_points.php'=>'XMR Verification','ops.php'=>'Ops Dashboard']); ?>
<div class="page"><main class="main">
<?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?>
<div class="panel"><h1>Operational Summary</h1><div class="grid"><div class="panel"><h3>Pending Reports</h3><p><?php echo $counters['pending_reports']; ?></p></div><div class="panel"><h3>Failed Processing</h3><p><?php echo $counters['failed_processing']; ?></p></div><div class="panel"><h3>Pending XMR Requests</h3><p><?php echo $counters['pending_xmr']; ?></p></div><div class="panel"><h3>New Creators (7d)</h3><p><?php echo $counters['new_users_7d']; ?></p></div><div class="panel"><h3>Recent Uploads (7d)</h3><p><?php echo $counters['uploads_7d']; ?></p></div></div></div>
<div class="panel"><h2>Reports Queue</h2><?php if(!$reports): ?><p class="muted">No reports in queue.</p><?php else: ?><table><tr><th>When</th><th>Reason</th><th>Video</th><th>Reporter</th><th>Owner</th><th>Action</th></tr><?php foreach($reports as $r): ?><tr><td><?php echo e($r['created_at']); ?></td><td><?php echo e($r['reason']); ?></td><td><a href="../v.php?s=<?php echo urlencode($r['slug']); ?>"><?php echo e($r['title']); ?></a></td><td><?php echo e($r['reporter']); ?></td><td><a href="../profile.php?u=<?php echo urlencode($r['owner_name']); ?>">@<?php echo e($r['owner_name']); ?></a></td><td><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="mark_report_reviewed"><input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>"><button>Mark reviewed</button></form></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<div class="panel"><h2>Audit Log (Recent)</h2><?php if(!$audit): ?><p class="muted">No audit events.</p><?php else: ?><table><tr><th>Time</th><th>Event</th><th>Payload</th></tr><?php foreach($audit as $a): ?><tr><td><?php echo e($a['created_at']); ?></td><td><?php echo e($a['event_type']); ?></td><td><code><?php echo e((string)$a['payload_json']); ?></code></td></tr><?php endforeach; ?></table><?php endif; ?></div>
</main></div></body></html>
