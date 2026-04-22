<?php
require_once '../includes/layout.php';
requireLogin();
$userId=(int)$_SESSION['user_id'];
$elig=creatorEligibility($pdo,$userId);
$profile=$pdo->prepare('SELECT * FROM monetization_profiles WHERE user_id=?');$profile->execute([$userId]);$profile=$profile->fetch();
if($_SERVER['REQUEST_METHOD']==='POST'){
  handleMutation(['requireAuth'=>true,'rateBucket'=>'monetization_setup','onErrorRedirect'=>'creator/monetization.php'], function() use($pdo,$userId,$elig): void {
    $mode=(string)($_POST['mode'] ?? '');
    if($mode==='request_review'){
      if(!$elig['payout_review_eligible']) throw new RuntimeException('Not eligible for payout review yet.');
      $pdo->prepare('INSERT INTO payout_reviews (creator_user_id,requested_by,review_status) VALUES (?,?,\'pending\')')->execute([$userId,$userId]);
      $pdo->prepare("UPDATE monetization_profiles SET monetization_status='under_review', payout_status='under_review' WHERE user_id=?")->execute([$userId]);
      notifyUser($pdo,$userId,'payout_status','Payout review requested','Your payout readiness review was submitted.','payout',null,'payout-review-'.$userId);
      setFlash(true,'Payout review requested.');
    } elseif($mode==='save_payout'){
      $provider=trim((string)($_POST['payout_provider'] ?? ''));
      $reference=trim((string)($_POST['payout_reference'] ?? ''));
      $pdo->prepare('UPDATE monetization_profiles SET payout_provider=?, payout_reference=?, payout_status=IF(payout_status=\'not_eligible\',\'pending_setup\',payout_status) WHERE user_id=?')->execute([$provider,$reference,$userId]);
      setFlash(true,'Payout setup info saved.');
    }
    header('Location: monetization.php');exit;
  });
}
$flash=pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Monetization Readiness</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Creator Studio', creatorNav('../')); ?><div class="page"><main class="main"><?php if($flash): ?><div class="flash"><?php echo e($flash['msg']); ?></div><?php endif; ?><div class="panel"><h1>Monetization & Payout Readiness</h1><p class="tiny">Creator rank: <?php echo e($elig['creator_rank']); ?> · Creator EXP <?php echo (int)$elig['creator_exp']; ?> · Followers <?php echo (int)$elig['followers']; ?> · Views <?php echo (int)$elig['views']; ?></p><p class="tiny">Marketplace eligibility: <?php echo $elig['marketplace_eligible']?'Yes':'No'; ?> · Payout review eligibility: <?php echo $elig['payout_review_eligible']?'Yes':'No'; ?></p><?php if($elig['flags']): ?><ul><?php foreach($elig['flags'] as $f): ?><li><?php echo e($f); ?></li><?php endforeach; ?></ul><?php endif; ?></div><div class="panel"><h2>Payout Setup Placeholder</h2><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="save_payout"><label>Payout Provider</label><input name="payout_provider" value="<?php echo e((string)($profile['payout_provider'] ?? '')); ?>" placeholder="future provider"><label>Payout Reference</label><input name="payout_reference" value="<?php echo e((string)($profile['payout_reference'] ?? '')); ?>" placeholder="account handle"><button>Save Setup</button></form><p class="tiny">No funds are transferred in this phase. This is eligibility and workflow scaffolding only.</p></div><div class="panel"><h2>Request Payout Eligibility Review</h2><form method="post"><?php echo csrfInput(); ?><input type="hidden" name="mode" value="request_review"><button <?php echo !$elig['payout_review_eligible']?'disabled':''; ?>>Request Review</button></form><p class="tiny">Current status: monetization <?php echo e((string)($profile['monetization_status'] ?? 'not_eligible')); ?> · payout <?php echo e((string)($profile['payout_status'] ?? 'not_eligible')); ?></p></div></main></div></body></html>
