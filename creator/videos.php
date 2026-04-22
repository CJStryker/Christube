<?php
require_once '../includes/layout.php';
requireLogin();
$userId = (int)$_SESSION['user_id'];
$status = trim((string)($_GET['status'] ?? 'all'));
$q = trim((string)($_GET['q'] ?? ''));
$allowed = ['all','ready','processing','failed','archived'];
if (!in_array($status,$allowed,true)) $status='all';
$where = 'v.user_id=?';
$params = [$userId];
if ($status === 'archived') { $where .= ' AND v.is_archived=1'; }
elseif ($status !== 'all') { $where .= ' AND v.processing_status=? AND v.is_archived=0'; $params[] = $status; }
if ($q !== '') { $where .= ' AND (v.title LIKE ? OR v.slug LIKE ?)'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; }
$stmt = $pdo->prepare("SELECT v.*,ta.id AS thumbnail_asset_id,COALESCE(vc.views,0) AS views FROM videos v LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id LEFT JOIN (SELECT video_id,COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id WHERE {$where} ORDER BY v.uploaded_at DESC LIMIT 200");
$stmt->execute($params);
$videos = $stmt->fetchAll();
$flash = pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Videos</title><link rel="stylesheet" href="../public/styles.css"></head><body>
<?php renderTopbar('Creator Studio', creatorNav('../')); ?>
<div class="page"><main class="main"><?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?>
<div class="panel"><h1>My Videos</h1><form method="get"><input name="q" value="<?php echo e($q); ?>" placeholder="Search owned videos"><select name="status"><?php foreach($allowed as $s): ?><option value="<?php echo $s; ?>" <?php echo $status===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?></select><button>Filter</button></form><p class="tiny">Bulk actions scaffolding: archive, unarchive, publish, private.</p></div>
<?php if(!$videos): ?><div class="panel"><p class="muted">No videos match this filter.</p></div><?php endif; ?>
<?php foreach($videos as $v): ?><div class="panel"><h3><a href="../v.php?s=<?php echo urlencode($v['slug']); ?>"><?php echo e($v['title']); ?></a></h3><p class="tiny">Status: <strong><?php echo e($v['processing_status']); ?></strong> · Visibility <?php echo e($v['visibility']); ?> · Archived <?php echo (int)$v['is_archived']; ?> · Views <?php echo (int)$v['views']; ?></p><p><a href="video_edit.php?id=<?php echo (int)$v['id']; ?>">Edit metadata</a> · <a href="video_analytics.php?id=<?php echo (int)$v['id']; ?>">Video analytics</a></p><?php if(!empty($v['processing_error'])): ?><p class="tiny">Processing error: <?php echo e($v['processing_error']); ?></p><?php endif; ?></div><?php endforeach; ?>
</main></div></body></html>
