<?php
require_once 'config.php';
requireLogin();

$videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
$xpSpend = isset($_POST['xp_spend']) ? (int)$_POST['xp_spend'] : 0;
$userId = (int)$_SESSION['user_id'];

if ($videoId < 1 || $xpSpend < 10) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid promotion request. Minimum spend is 10 XP.'];
    header('Location: uploads/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, slug FROM videos WHERE id = ? AND user_id = ?');
$stmt->execute([$videoId, $userId]);
$video = $stmt->fetch();
if (!$video) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'You can only promote your own videos.'];
    header('Location: uploads/index.php');
    exit;
}

if (!spendExperience($pdo, $userId, $xpSpend, 'video_ad_campaign')) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Not enough XP to run this ad campaign.'];
    header('Location: uploads/index.php');
    exit;
}

$hours = max(12, min(240, $xpSpend * 2));
$activeUntil = (new DateTime())->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');

$ins = $pdo->prepare('INSERT INTO video_ads (video_id, user_id, points_spent, active_until) VALUES (?, ?, ?, ?)');
$ins->execute([$videoId, $userId, $xpSpend, $activeUntil]);

$_SESSION['flash'] = ['ok' => true, 'msg' => 'Ad campaign started for ' . $hours . ' hours.'];
header('Location: uploads/index.php');
exit;
?>
