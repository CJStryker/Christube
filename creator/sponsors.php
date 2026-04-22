<?php
require_once '../includes/layout.php';
requireLogin();
$userId=(int)$_SESSION['user_id'];
$elig = creatorEligibility($pdo, $userId);

if($_SERVER['REQUEST_METHOD']==='POST'){
  handleMutation(['requireAuth'=>true,'rateBucket'=>'sponsor_tools','onErrorRedirect'=>'creator/sponsors.php'], function() use($pdo,$userId,$elig): void {
    $mode=(string)($_POST['mode'] ?? '');
    if($mode==='create_campaign'){
      if(!$elig['marketplace_eligible']) throw new RuntimeException('Marketplace access requires creator progression.');
      $title=trim((string)($_POST['title'] ?? ''));
      $budget=(int)($_POST['budget_points'] ?? 0);
      if($title===''||$budget<50) throw new RuntimeException('Campaign title and minimum budget required.');
      $pdo->prepare("INSERT INTO sponsor_campaigns (sponsor_name,sponsor_contact,requester_user_id,title,objective,budget_points,category,starts_at,ends_at,review_status) VALUES (?,?,?,?,?,?,?,?,?,'submitted')")
          ->execute([trim((string)($_POST['sponsor_name'] ?? $_SESSION['username'])),trim((string)($_POST['sponsor_contact'] ?? '')),$userId,$title,trim((string)($_POST['objective'] ?? '')), $budget, trim((string)($_POST['category'] ?? 'general')), $_POST['starts_at'] ?: null, $_POST['ends_at'] ?: null]);
      auditEvent($pdo,$userId,'campaign_submitted',['campaign_id'=>(int)$pdo->lastInsertId()]);
      setFlash(true,'Campaign submitted for review.');
    } elseif($mode==='respond') {
      $campaignId=(int)($_POST['campaign_id'] ?? 0);
      $response=(string)($_POST['response'] ?? 'pending');
      if(!in_array($response,['accepted','declined'],true)) throw new RuntimeException('Invalid response.');
      $pdo->prepare('INSERT INTO creator_sponsor_responses (campaign_id,creator_user_id,response_status,response_note,responded_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE response_status=VALUES(response_status),response_note=VALUES(response_note),responded_at=NOW()')
          ->execute([$campaignId,$userId,$response,trim((string)($_POST['response_note'] ?? ''))]);
      setFlash(true,'Response saved.');
    }
    header('Location: sponsors.php');exit;
  });
}

$campaigns = $pdo->prepare("SELECT c.*, r.response_status, r.response_note FROM sponsor_campaigns c LEFT JOIN creator_sponsor_responses r ON r.campaign_id=c.id AND r.creator_user_id=? WHERE c.requester_user_id=? OR c.target_creator_id IS NULL OR c.target_creator_id=? ORDER BY c.created_at DESC LIMIT 120");
$campaigns->execute([$userId,$userId,$userId]);
$campaigns=$campaigns->fetchAll();
$flash=pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sponsor Tools</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Creator Studio', creatorNav('../')); ?><div class="page"><main class="main"><?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?><div class="panel"><h1>Sponsor & Campaign Tools</h1><p class="tiny">Tier: <?php echo e($elig['creator_rank']); ?> · Creator EXP <?php echo (int)$elig['creator_exp']; ?> · Marketplace <?php echo $elig['marketplace_eligible']?'Eligible':'Not Eligible'; ?></p><?php if(!$elig['marketplace_eligible']): ?><p class="tiny">Unlock by improving creator activity and EXP.</p><?php endif; ?></div><div class="panel"><h2>Create Campaign Request</h2><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="create_campaign"><label>Sponsor Name</label><input name="sponsor_name" value="<?php echo e($_SESSION['username']); ?>"><label>Contact</label><input name="sponsor_contact"><label>Title</label><input name="title" required><label>Objective</label><textarea name="objective"></textarea><label>Budget Points</label><input type="number" name="budget_points" min="50" value="100"><label>Category</label><input name="category" value="general"><label>Start</label><input type="datetime-local" name="starts_at"><label>End</label><input type="datetime-local" name="ends_at"><button>Submit Campaign</button></form></div><div class="panel"><h2>Campaign Opportunities / History</h2><?php if(!$campaigns): ?><p class="muted">No campaigns yet.</p><?php else: ?><table><tr><th>Campaign</th><th>Status</th><th>Budget</th><th>Response</th><th>Action</th></tr><?php foreach($campaigns as $c): ?><tr><td><?php echo e($c['title']); ?></td><td><?php echo e($c['review_status']); ?></td><td><?php echo (int)$c['budget_points']; ?></td><td><?php echo e((string)($c['response_status'] ?? 'pending')); ?></td><td><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="respond"><input type="hidden" name="campaign_id" value="<?php echo (int)$c['id']; ?>"><select name="response"><option value="accepted">Accept</option><option value="declined">Decline</option></select><input name="response_note" placeholder="Optional note"><button>Save</button></form></td></tr><?php endforeach; ?></table><?php endif; ?></div></main></div></body></html>
