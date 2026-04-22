<?php
require_once 'config.php';
requireLogin();

$targetUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$action = $_POST['action'] ?? '';
$currentUserId = (int)$_SESSION['user_id'];

if ($targetUserId < 1 || $targetUserId === $currentUserId || !in_array($action, ['follow', 'unfollow'], true)) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid follow request.'];
    header('Location: index.php');
    exit;
}

$userStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$userStmt->execute([$targetUserId]);
$target = $userStmt->fetch();
if (!$target) {
    $_SESSION['flash'] = ['ok' => false, 'msg' => 'User not found.'];
    header('Location: index.php');
    exit;
}

if ($action === 'follow') {
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_follows (follower_id, followed_id) VALUES (?, ?)');
    $stmt->execute([$currentUserId, $targetUserId]);
    $_SESSION['flash'] = ['ok' => true, 'msg' => 'Now following @' . $target['username'] . '.'];
} else {
    $stmt = $pdo->prepare('DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?');
    $stmt->execute([$currentUserId, $targetUserId]);
    $_SESSION['flash'] = ['ok' => true, 'msg' => 'Unfollowed @' . $target['username'] . '.'];
}

header('Location: profile.php?u=' . urlencode($target['username']));
exit;
?>
