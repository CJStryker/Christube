<?php
require_once 'config.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];

$stmt = $pdo->prepare(
    "SELECT v.id, v.user_id, v.title, v.description, v.file_path, v.visibility, v.uploaded_at, u.username
     FROM videos v
     INNER JOIN users u ON u.id = v.user_id
     WHERE v.visibility = 'public' OR v.user_id = ?
     ORDER BY v.uploaded_at DESC
     LIMIT 30"
);
$stmt->execute([$currentUserId]);
$videos = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Christube</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f6f7fb; color: #1c1f2a; }
        .topbar { background: #1f3fb3; color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #fff; text-decoration: none; margin-left: 12px; }
        .wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .panel { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 16px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        input, textarea, select { width: 100%; box-sizing: border-box; padding: 10px; margin-top: 6px; margin-bottom: 12px; border: 1px solid #ced4df; border-radius: 6px; }
        button { background: #1f3fb3; color: #fff; border: 0; border-radius: 6px; padding: 10px 14px; cursor: pointer; }
        .muted { color: #586071; font-size: 14px; }
        video { width: 100%; border-radius: 8px; background: #000; max-height: 220px; }
        .flash { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
        .ok { background: #e8f8ec; border: 1px solid #b7e5c4; }
        .err { background: #fdecec; border: 1px solid #f7b8b8; }
        .tiny { font-size: 12px; }
    </style>
</head>
<body>
    <div class="topbar">
        <strong>Christube</strong>
        <div>
            Logged in as <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
            <a href="uploads/index.php">My Uploads</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="wrap">
        <?php if ($flash): ?>
            <div class="flash <?php echo $flash['ok'] ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($flash['msg']); ?></div>
        <?php endif; ?>

        <div class="panel">
            <h2>Upload a video</h2>
            <form action="upload.php" method="post" enctype="multipart/form-data">
                <label>Title</label>
                <input type="text" name="title" maxlength="150" required>

                <label>Description</label>
                <textarea name="description" rows="3" maxlength="2000" placeholder="Optional"></textarea>

                <label>Privacy</label>
                <select name="visibility" required>
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>

                <label>Video file (mp4, webm, mov, ogg)</label>

                <input type="file" name="videoFile" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov" required>

                <button type="submit" name="submit">Upload Video</button>
            </form>
        </div>

        <div class="panel">
            <h2>Recently uploaded videos</h2>
            <p class="muted">Shows all public uploads and your private uploads, newest first.</p>

            <?php if (!$videos): ?>
                <p class="muted">No videos uploaded yet.</p>
            <?php else: ?>
                <div class="grid">
                    <?php foreach ($videos as $video): ?>
                        <div class="panel" style="margin:0;">
                            <video controls preload="metadata" src="<?php echo htmlspecialchars($video['file_path']); ?>"></video>
                            <h3><?php echo htmlspecialchars($video['title']); ?></h3>
                            <p class="muted"><?php echo nl2br(htmlspecialchars($video['description'] ?? '')); ?></p>
                            <p class="tiny">By <?php echo htmlspecialchars($video['username']); ?> · <?php echo htmlspecialchars($video['uploaded_at']); ?></p>
                            <p class="tiny">Visibility: <strong><?php echo htmlspecialchars($video['visibility']); ?></strong></p>

                            <?php if ((int)$video['user_id'] === $currentUserId): ?>
                                <form action="update_visibility.php" method="post">
                                    <input type="hidden" name="video_id" value="<?php echo (int)$video['id']; ?>">
                                    <select name="visibility">
                                        <option value="public" <?php echo $video['visibility'] === 'public' ? 'selected' : ''; ?>>Public</option>
                                        <option value="private" <?php echo $video['visibility'] === 'private' ? 'selected' : ''; ?>>Private</option>
                                    </select>
                                    <button type="submit">Update privacy</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
