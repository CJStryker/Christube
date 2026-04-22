<?php
require_once 'config.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

$since = (string)($argv[1] ?? date('Y-m-d', strtotime('-30 days')));
$users = $pdo->query('SELECT id FROM users')->fetchAll();
foreach ($users as $u) {
    $uid = (int)$u['id'];
    $dayRows = $pdo->prepare("SELECT DATE(vv.viewed_at) AS day, COUNT(*) AS views
      FROM video_views vv INNER JOIN videos v ON v.id=vv.video_id WHERE v.user_id=? AND DATE(vv.viewed_at) >= ? GROUP BY DATE(vv.viewed_at)");
    $dayRows->execute([$uid, $since]);
    foreach ($dayRows->fetchAll() as $row) {
        $day = $row['day'];
        $views = (int)$row['views'];
        $watchStarts = (int)$pdo->query("SELECT COUNT(*) FROM product_events pe INNER JOIN videos v ON v.id=JSON_UNQUOTE(JSON_EXTRACT(pe.payload_json, '$.video_id')) WHERE pe.event_type='watch_start' AND v.user_id={$uid} AND DATE(pe.created_at)='{$day}'")->fetchColumn();
        $likes = (int)$pdo->query("SELECT COUNT(*) FROM video_reactions vr INNER JOIN videos v ON v.id=vr.video_id WHERE v.user_id={$uid} AND vr.reaction='like' AND DATE(vr.created_at)='{$day}'")->fetchColumn();
        $comments = (int)$pdo->query("SELECT COUNT(*) FROM video_comments vc INNER JOIN videos v ON v.id=vc.video_id WHERE v.user_id={$uid} AND DATE(vc.created_at)='{$day}'")->fetchColumn();
        $follows = (int)$pdo->query("SELECT COUNT(*) FROM user_follows WHERE followed_id={$uid} AND DATE(created_at)='{$day}'")->fetchColumn();
        $progress = $pdo->query("SELECT AVG(h.last_position_seconds) AS avg_watch, AVG(CASE WHEN h.duration_seconds>0 THEN LEAST(1,h.last_position_seconds/h.duration_seconds) ELSE 0 END) AS completion FROM watch_history h INNER JOIN videos v ON v.id=h.video_id WHERE v.user_id={$uid} AND DATE(h.watched_at)='{$day}'")->fetch();
        $pdo->prepare("INSERT INTO creator_daily_stats (user_id,stat_date,views_count,watch_starts,likes_count,comments_count,followers_gained,avg_watch_seconds,completion_rate)
          VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE views_count=VALUES(views_count),watch_starts=VALUES(watch_starts),likes_count=VALUES(likes_count),comments_count=VALUES(comments_count),followers_gained=VALUES(followers_gained),avg_watch_seconds=VALUES(avg_watch_seconds),completion_rate=VALUES(completion_rate)")
          ->execute([$uid,$day,$views,$watchStarts,$likes,$comments,$follows,(float)($progress['avg_watch'] ?? 0),(float)($progress['completion'] ?? 0)]);
    }
}

echo "Creator stats rollup completed from {$since}\n";
