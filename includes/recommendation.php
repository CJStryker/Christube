<?php

function recCandidateLatest(PDO $pdo, int $limit = 120): array {
    $stmt = $pdo->prepare("SELECT v.id,v.user_id,v.slug,v.title,v.description,v.uploaded_at,v.duration_seconds,
        COALESCE(vc.views,0) AS views,
        SUM(CASE WHEN vr.reaction='like' THEN 1 ELSE 0 END) AS likes,
        ta.id AS thumbnail_asset_id
        FROM videos v
        LEFT JOIN video_reactions vr ON vr.video_id=v.id
        LEFT JOIN media_assets ta ON ta.id=v.thumbnail_asset_id
        LEFT JOIN (SELECT video_id, COUNT(*) AS views FROM video_views GROUP BY video_id) vc ON vc.video_id=v.id
        WHERE v.processing_status='ready' AND v.visibility='public' AND v.is_archived=0
        GROUP BY v.id ORDER BY v.uploaded_at DESC LIMIT {$limit}");
    $stmt->execute();
    return $stmt->fetchAll();
}

function recUserSignals(PDO $pdo, int $userId): array {
    if ($userId < 1) return ['liked'=>[], 'watched'=>[], 'followed'=>[], 'creator_affinity'=>[]];
    $liked = $pdo->query('SELECT video_id FROM video_reactions WHERE user_id=' . $userId . " AND reaction='like' LIMIT 500")->fetchAll(PDO::FETCH_COLUMN);
    $watched = $pdo->query('SELECT video_id FROM watch_history WHERE user_id=' . $userId . ' LIMIT 500')->fetchAll(PDO::FETCH_COLUMN);
    $followed = $pdo->query('SELECT followed_id FROM user_follows WHERE follower_id=' . $userId . ' LIMIT 300')->fetchAll(PDO::FETCH_COLUMN);
    $aff = $pdo->query('SELECT v.user_id, COUNT(*) c FROM watch_history h INNER JOIN videos v ON v.id=h.video_id WHERE h.user_id=' . $userId . ' GROUP BY v.user_id ORDER BY c DESC LIMIT 30')->fetchAll();
    $creatorAffinity = [];
    foreach ($aff as $a) $creatorAffinity[(int)$a['user_id']] = (int)$a['c'];
    return [
        'liked' => array_map('intval', $liked),
        'watched' => array_map('intval', $watched),
        'followed' => array_map('intval', $followed),
        'creator_affinity' => $creatorAffinity,
    ];
}

function recScoreRows(PDO $pdo, array $rows, int $userId): array {
    $weights = recommendationWeights($pdo);
    $signals = recUserSignals($pdo, $userId);

    $scored = [];
    $seenCreators = [];
    foreach ($rows as $row) {
        $videoId = (int)$row['id'];
        $creatorId = (int)$row['user_id'];
        if (in_array($videoId, $signals['watched'], true)) continue;

        $score = 0.0;
        $score += (float)$row['views'] * $weights['weight_views'];
        $score += (float)$row['likes'] * $weights['weight_likes'];

        $ageHours = max(1, (time() - strtotime((string)$row['uploaded_at'])) / 3600);
        $score += (1 / $ageHours) * 200 * $weights['weight_recency'];

        if (in_array($creatorId, $signals['followed'], true)) $score += 80 * $weights['weight_affinity'];
        if (isset($signals['creator_affinity'][$creatorId])) $score += min(60, $signals['creator_affinity'][$creatorId] * 5);

        $prog = $pdo->prepare('SELECT creator_level FROM user_progression WHERE user_id=?');
        $prog->execute([$creatorId]);
        $creatorLevel = (int)$prog->fetchColumn();
        $score += $creatorLevel * $weights['weight_creator_tier'];

        $risk = 0;
        if ($row['views'] > 0 && $row['likes'] / max(1, $row['views']) > 0.98) $risk += 40;
        $score -= $risk;

        if (($seenCreators[$creatorId] ?? 0) >= (int)$weights['max_same_creator']) continue;
        $seenCreators[$creatorId] = ($seenCreators[$creatorId] ?? 0) + 1;

        $row['score'] = $score;
        $scored[] = $row;
    }

    usort($scored, fn($a,$b) => ($b['score'] <=> $a['score']));
    return $scored;
}

function personalizedHomepage(PDO $pdo, int $userId, int $limit = 24): array {
    $candidates = recCandidateLatest($pdo, 180);
    $scored = recScoreRows($pdo, $candidates, $userId);
    return array_slice($scored, 0, $limit);
}

function personalizedRelated(PDO $pdo, int $userId, int $videoId, int $limit = 12): array {
    $candidates = recCandidateLatest($pdo, 120);
    $candidates = array_values(array_filter($candidates, fn($r) => (int)$r['id'] !== $videoId));
    $scored = recScoreRows($pdo, $candidates, $userId);
    return array_slice($scored, 0, $limit);
}

function creatorSuggestions(PDO $pdo, int $userId, int $limit = 12): array {
    $signals = recUserSignals($pdo, $userId);
    $followedSet = array_flip($signals['followed']);
    $stmt = $pdo->query("SELECT u.id,u.username,COALESCE(up.creator_level,1) AS creator_level,
        COUNT(v.id) AS public_ready_videos,
        (SELECT COUNT(*) FROM user_follows f WHERE f.followed_id=u.id) AS followers
        FROM users u
        LEFT JOIN videos v ON v.user_id=u.id AND v.processing_status='ready' AND v.visibility='public' AND v.is_archived=0
        LEFT JOIN user_progression up ON up.user_id=u.id
        GROUP BY u.id HAVING public_ready_videos > 0
        ORDER BY followers DESC, creator_level DESC LIMIT 120");
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        if (isset($followedSet[(int)$r['id']])) continue;
        if ((int)$r['id'] === $userId) continue;
        $score = (int)$r['followers'] * 1.4 + (int)$r['creator_level'] * 8 + (int)$r['public_ready_videos'] * 2;
        $rows[] = $r + ['score'=>$score];
    }
    usort($rows, fn($a,$b)=>$b['score']<=>$a['score']);
    return array_slice($rows,0,$limit);
}
