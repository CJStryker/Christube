<?php
require_once 'config.php';
requireLogin();

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'promote',
    'onErrorRedirect' => 'creator/promotions.php',
], function () use ($pdo): void {
    $videoId = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;
    $xpSpend = isset($_POST['xp_spend']) ? (int)$_POST['xp_spend'] : 0;
    $userId = (int)$_SESSION['user_id'];

    if ($videoId < 1 || $xpSpend < 10) {
        throw new RuntimeException('Invalid promotion request. Minimum spend is 10 XP.');
    }

    $stmt = $pdo->prepare('SELECT id FROM videos WHERE id = ? AND user_id = ?');
    $stmt->execute([$videoId, $userId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('You can only promote your own videos.');
    }

    if (!spendExperience($pdo, $userId, $xpSpend, 'video_ad_campaign')) {
        throw new RuntimeException('Not enough XP to run this ad campaign.');
    }

    $hours = max(12, min(240, $xpSpend * 2));
    $activeUntil = (new DateTime())->modify('+' . $hours . ' hours')->format('Y-m-d H:i:s');
    $ins = $pdo->prepare('INSERT INTO video_ads (video_id, user_id, points_spent, active_until) VALUES (?, ?, ?, ?)');
    $ins->execute([$videoId, $userId, $xpSpend, $activeUntil]);

    notifyUser($pdo, $userId, 'promotion_status', 'Promotion activated', 'Your promotion is active for ' . $hours . ' hours.', 'video', $videoId, 'promotion-active-' . $videoId);
    setFlash(true, 'Ad campaign started for ' . $hours . ' hours.');
    header('Location: creator/promotions.php');
    exit;
});
?>
