<?php
require_once 'config.php';
requireLogin();

handleMutation([
    'requireAuth' => true,
    'rateBucket' => 'follow',
    'onErrorRedirect' => 'index.php',
], function () use ($pdo): void {
    $targetUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $action = $_POST['action'] ?? '';
    $currentUserId = (int)$_SESSION['user_id'];

    if ($targetUserId < 1 || $targetUserId === $currentUserId || !in_array($action, ['follow', 'unfollow'], true)) {
        throw new RuntimeException('Invalid follow request.');
    }

    $userStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $userStmt->execute([$targetUserId]);
    $target = $userStmt->fetch();
    if (!$target) {
        throw new RuntimeException('User not found.');
    }

    if ($action === 'follow') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_follows (follower_id, followed_id) VALUES (?, ?)');
        $stmt->execute([$currentUserId, $targetUserId]);
        trackProductEvent($pdo, 'followed_creator', $currentUserId, ['target_user_id'=>$targetUserId]);
        notifyUser($pdo, $targetUserId, 'new_follower', 'You have a new follower', '@' . $_SESSION['username'] . ' followed your channel.', 'user', $currentUserId, 'follow-' . $currentUserId);
        setFlash(true, 'Now following @' . $target['username'] . '.');
    } else {
        $stmt = $pdo->prepare('DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?');
        $stmt->execute([$currentUserId, $targetUserId]);
        trackProductEvent($pdo, 'unfollowed_creator', $currentUserId, ['target_user_id'=>$targetUserId]);
        setFlash(true, 'Unfollowed @' . $target['username'] . '.');
    }

    header('Location: profile.php?u=' . urlencode($target['username']));
    exit;
});
?>
