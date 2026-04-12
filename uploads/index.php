<?php
require_once dirname(__DIR__) . '/config.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];

$stmt = $pdo->prepare(
    "SELECT id, title, description, file_path, visibility, uploaded_at
     FROM videos
     WHERE user_id = ?
     ORDER BY uploaded_at DESC"
);
$stmt->execute([$currentUserId]);
$videos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Uploads - Christube</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7fb; color: #1c1f2a; }
        .topbar { background: #1f3fb3; color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #fff; text-decoration: none; margin-left: 12px; }
        .wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 16px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        video { width: 100%; border-radius: 8px; background: #000; max-height: 220px; }
        .muted { color: #586071; font-size: 14px; }
        .tiny { font-size: 12px; }
    </style>
</head>
<body>
    <div class="topbar">
        <strong>Christube</strong>
        <div>
            Logged in as <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
            <a href="../index.php">Home</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="wrap">
        <div class="panel">
            <h2>My uploaded videos</h2>
            <p class="muted">All videos uploaded by your account, including private videos.</p>

            <?php if (!$videos): ?>
                <p class="muted">You have not uploaded videos yet.</p>
            <?php else: ?>
                <div class="grid">
                    <?php foreach ($videos as $video): ?>
                        <div class="panel" style="margin:0;">
                            <video controls preload="metadata" src="../<?php echo htmlspecialchars($video['file_path']); ?>"></video>
                            <h3><?php echo htmlspecialchars($video['title']); ?></h3>
                            <p class="muted"><?php echo nl2br(htmlspecialchars($video['description'] ?? '')); ?></p>
                            <p class="tiny">Uploaded: <?php echo htmlspecialchars($video['uploaded_at']); ?></p>
                            <p class="tiny">Visibility: <strong><?php echo htmlspecialchars($video['visibility']); ?></strong></p>

                            <form action="../update_visibility.php" method="post">
                                <input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>">
                                <select name="visibility">
                                    <option value="public" <?php echo $video['visibility'] === 'public' ? 'selected' : ''; ?>>Public</option>
                                    <option value="private" <?php echo $video['visibility'] === 'private' ? 'selected' : ''; ?>>Private</option>
                                </select>
                                <button type="submit">Update privacy</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
