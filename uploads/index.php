<?php
require_once dirname(__DIR__) . '/config.php';
requireLogin();

$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];

$stmt = $pdo->prepare(
    "SELECT v.id, v.slug, v.title, v.description, v.file_path, v.visibility, v.uploaded_at,
            SUM(CASE WHEN vr.reaction = 'like' THEN 1 ELSE 0 END) AS likes,
            SUM(CASE WHEN vr.reaction = 'dislike' THEN 1 ELSE 0 END) AS dislikes
     FROM videos v
     LEFT JOIN video_reactions vr ON vr.video_id = v.id
     WHERE v.user_id = ?
     GROUP BY v.id
     ORDER BY v.uploaded_at DESC"
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
        body { margin: 0; font-family: Arial, sans-serif; background: #050505; color: #00ff66; }
        .topbar { background: #7a0000; color: #0a0a0a; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #0a0a0a; text-decoration: none; margin-left: 12px; font-weight: bold; }
        .wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .panel { background: #101010; border-radius: 10px; border: 1px solid #7a0000; padding: 16px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        video { width: 100%; border-radius: 8px; background: #000; max-height: 220px; }
        .muted { color: #87fcb0; font-size: 14px; }
        .tiny { font-size: 12px; color: #87fcb0; }
        input, select { width: 100%; box-sizing: border-box; padding: 10px; margin-top: 6px; margin-bottom: 12px; border: 1px solid #7a0000; border-radius: 6px; background:#000; color:#00ff66; }
        button { background: #7a0000; color: #0a0a0a; border: 0; border-radius: 6px; padding: 10px 14px; cursor: pointer; font-weight:bold; }
        a { color:#00ff66; }
        .donation-box { background:#140000; border:1px solid #7a0000; border-radius:8px; padding:12px; margin-bottom:14px; }
        .donation-box code { display:block; margin-top:8px; padding:8px; background:#000; color:#00ff66; overflow-wrap:anywhere; }
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

        <div class="donation-box">
            <strong>Support Christube</strong>
            <p class="muted">Donation notice: we currently only accept Monero (XMR).</p>
            <code>86KNpUKopsJTFUj72PQoLYX7xpsKMiyd6G5BKYoG65FaKzUQqf4jqLaS6LPUjh8cq5MQTsQh3V2hVRQSqp8j4JGL4Xf9cvq</code>
        </div>

        <div class="panel">
            <h2>My uploaded videos</h2>
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
                            <p class="tiny">👍 <?php echo (int)$video['likes']; ?> · 👎 <?php echo (int)$video['dislikes']; ?></p>
                            <p><a href="../v.php?s=<?php echo urlencode($video['slug']); ?>">Watch page</a></p>
                            <p class="tiny">Short link: <a href="../v.php?s=<?php echo urlencode($video['slug']); ?>">/v.php?s=<?php echo htmlspecialchars($video['slug']); ?></a></p>

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
