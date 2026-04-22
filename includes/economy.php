<?php

function economyConfig(): array {
    return [
        'xp' => [
            'signup_bonus' => 25,
            'profile_complete' => 20,
            'watch_start' => 1,
            'watch_complete' => 4,
            'comment_posted' => 5,
            'reaction_given' => 2,
            'follow_creator' => 3,
            'upload_published' => 20,
            'cooldown_minutes_default' => 60,
            'daily_cap_viewer' => 120,
            'daily_cap_creator' => 180,
        ],
        'ranking' => [
            'weight_views' => 1.3,
            'weight_likes' => 2.2,
            'weight_completion' => 1.8,
            'weight_creator_tier' => 1.0,
            'weight_recency' => 1.2,
            'weight_affinity' => 2.0,
            'max_same_creator' => 3,
        ],
        'eligibility' => [
            'creator_tier_for_marketplace' => 'Rising Creator',
            'min_creator_exp_for_marketplace' => 500,
            'min_followers_for_payout_review' => 25,
            'min_views_for_payout_review' => 500,
        ],
    ];
}

function ensureEconomySchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS exp_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        actor_user_id INT NULL,
        exp_delta INT NOT NULL,
        balance_after INT NOT NULL,
        event_code VARCHAR(80) NOT NULL,
        reason VARCHAR(160) NOT NULL,
        idempotency_key VARCHAR(160) NOT NULL,
        metadata_json TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_exp_idempotency (idempotency_key),
        INDEX idx_exp_user_time (user_id, created_at),
        CONSTRAINT fk_exp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_progression (
        user_id INT PRIMARY KEY,
        viewer_exp INT NOT NULL DEFAULT 0,
        creator_exp INT NOT NULL DEFAULT 0,
        lifetime_exp INT NOT NULL DEFAULT 0,
        available_exp INT NOT NULL DEFAULT 0,
        viewer_level INT NOT NULL DEFAULT 1,
        creator_level INT NOT NULL DEFAULT 1,
        viewer_rank VARCHAR(60) NOT NULL DEFAULT 'Novice',
        creator_rank VARCHAR(60) NOT NULL DEFAULT 'New Creator',
        streak_days INT NOT NULL DEFAULT 0,
        last_active_date DATE NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(80) UNIQUE NOT NULL,
        title VARCHAR(120) NOT NULL,
        description VARCHAR(255) NOT NULL,
        xp_bonus INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        achievement_id INT NOT NULL,
        awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_achievement (user_id, achievement_id),
        CONSTRAINT fk_user_achievement_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_user_achievement_achievement FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS monetization_profiles (
        user_id INT PRIMARY KEY,
        creator_tier VARCHAR(80) NOT NULL DEFAULT 'New Creator',
        trust_score DECIMAL(8,2) NOT NULL DEFAULT 0,
        monetization_status ENUM('not_eligible','pending_setup','under_review','eligible','suspended') NOT NULL DEFAULT 'not_eligible',
        payout_status ENUM('not_eligible','pending_setup','under_review','eligible','suspended') NOT NULL DEFAULT 'not_eligible',
        payout_provider VARCHAR(60) NULL,
        payout_reference VARCHAR(120) NULL,
        policy_flags_json TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_monetization_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sponsor_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sponsor_name VARCHAR(140) NOT NULL,
        sponsor_contact VARCHAR(180) NULL,
        requester_user_id INT NULL,
        title VARCHAR(160) NOT NULL,
        objective VARCHAR(255) NULL,
        budget_points INT NOT NULL DEFAULT 0,
        category VARCHAR(80) NULL,
        target_creator_id INT NULL,
        target_video_id INT NULL,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        review_status ENUM('draft','submitted','under_review','approved','rejected','active','completed','cancelled') NOT NULL DEFAULT 'draft',
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_campaign_status (review_status),
        CONSTRAINT fk_campaign_requester FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS creator_sponsor_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        creator_user_id INT NOT NULL,
        response_status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
        response_note TEXT NULL,
        responded_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_campaign_creator (campaign_id, creator_user_id),
        CONSTRAINT fk_response_campaign FOREIGN KEY (campaign_id) REFERENCES sponsor_campaigns(id) ON DELETE CASCADE,
        CONSTRAINT fk_response_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payout_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        creator_user_id INT NOT NULL,
        requested_by INT NULL,
        review_status ENUM('pending','approved','rejected','held') NOT NULL DEFAULT 'pending',
        reviewer_user_id INT NULL,
        reviewer_note TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        INDEX idx_payout_creator (creator_user_id, review_status),
        CONSTRAINT fk_payout_creator FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS search_saved_queries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        query_text VARCHAR(200) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_saved_queries_user (user_id, created_at),
        CONSTRAINT fk_saved_query_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS search_trending_queries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        query_text VARCHAR(200) NOT NULL UNIQUE,
        hits INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function progressionLevels(int $exp, bool $creator = false): array {
    $level = (int)floor(sqrt(max(0, $exp) / ($creator ? 140 : 120))) + 1;
    $next = (int)(pow($level, 2) * ($creator ? 140 : 120));
    $ranks = $creator
        ? [1=>'New Creator',4=>'Rising Creator',8=>'Trusted Creator',12=>'Elite Creator',18=>'Studio Pro']
        : [1=>'Novice',4=>'Engaged Viewer',8=>'Core Member',12=>'Community Pillar',18=>'Legend'];
    $rank='Novice';
    foreach ($ranks as $min=>$label) if($level>=$min)$rank=$label;
    return ['level'=>$level,'next_exp'=>$next,'rank'=>$rank];
}

function ensureProgressionRow(PDO $pdo, int $userId): void {
    $pdo->prepare('INSERT IGNORE INTO user_progression (user_id) VALUES (?)')->execute([$userId]);
    $pdo->prepare('INSERT IGNORE INTO monetization_profiles (user_id) VALUES (?)')->execute([$userId]);
}

function awardExp(PDO $pdo, int $userId, int $delta, string $eventCode, string $reason, string $idempotencyKey, array $metadata = [], bool $creatorTrack = false, ?int $actorId = null): bool {
    if ($delta === 0) return false;
    ensureProgressionRow($pdo, $userId);

    $exists = $pdo->prepare('SELECT id FROM exp_ledger WHERE idempotency_key=? LIMIT 1');
    $exists->execute([$idempotencyKey]);
    if ($exists->fetch()) return false;

    $today = $pdo->prepare('SELECT COALESCE(SUM(exp_delta),0) FROM exp_ledger WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $today->execute([$userId]);
    $earnedToday = (int)$today->fetchColumn();
    $cfg = economyConfig()['xp'];
    $cap = $creatorTrack ? $cfg['daily_cap_creator'] : $cfg['daily_cap_viewer'];
    if ($delta > 0 && $earnedToday >= $cap) return false;

    $rowStmt = $pdo->prepare('SELECT * FROM user_progression WHERE user_id=? LIMIT 1');
    $rowStmt->execute([$userId]);
    $p = $rowStmt->fetch();
    $viewerExp = (int)$p['viewer_exp'];
    $creatorExp = (int)$p['creator_exp'];
    $lifetime = (int)$p['lifetime_exp'];
    $available = (int)$p['available_exp'];

    if ($creatorTrack) $creatorExp = max(0, $creatorExp + $delta); else $viewerExp = max(0, $viewerExp + $delta);
    if ($delta > 0) $lifetime += $delta;
    $available = max(0, $available + $delta);

    $viewer = progressionLevels($viewerExp, false);
    $creator = progressionLevels($creatorExp, true);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE user_progression SET viewer_exp=?, creator_exp=?, lifetime_exp=?, available_exp=?, viewer_level=?, creator_level=?, viewer_rank=?, creator_rank=? WHERE user_id=?')
            ->execute([$viewerExp,$creatorExp,$lifetime,$available,$viewer['level'],$creator['level'],$viewer['rank'],$creator['rank'],$userId]);

        $pdo->prepare('INSERT INTO exp_ledger (user_id,actor_user_id,exp_delta,balance_after,event_code,reason,idempotency_key,metadata_json) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$userId,$actorId,$delta,$available,$eventCode,$reason,$idempotencyKey,json_encode($metadata, JSON_UNESCAPED_SLASHES)]);

        $pdo->prepare('UPDATE users SET experience_points=? WHERE id=?')->execute([$available, $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    if ($delta > 0 && in_array($eventCode, ['watch_complete','upload_published','follow_creator'], true)) {
        notifyUser($pdo, $userId, 'progress_milestone', 'EXP updated', 'You earned ' . $delta . ' EXP for ' . $reason . '.', 'exp', null, 'exp-' . $idempotencyKey);
    }

    return true;
}

function milestoneAward(PDO $pdo, int $userId): void {
    ensureProgressionRow($pdo, $userId);
    $p = $pdo->prepare('SELECT viewer_level, creator_level FROM user_progression WHERE user_id=?');
    $p->execute([$userId]);
    $row = $p->fetch() ?: ['viewer_level'=>1,'creator_level'=>1];
    $levels = [(int)$row['viewer_level'], (int)$row['creator_level']];
    foreach ([5,10,15] as $lvl) {
        if (in_array($lvl, $levels, true)) {
            $code = 'level_' . $lvl;
            $ach = $pdo->prepare('SELECT id FROM achievements WHERE code=?');
            $ach->execute([$code]);
            $aid = (int)$ach->fetchColumn();
            if ($aid < 1) {
                $pdo->prepare('INSERT INTO achievements (code,title,description,xp_bonus) VALUES (?,?,?,?)')->execute([$code, 'Level '.$lvl.' milestone', 'Reached level '.$lvl, 10]);
                $aid = (int)$pdo->lastInsertId();
            }
            $u = $pdo->prepare('INSERT IGNORE INTO user_achievements (user_id,achievement_id) VALUES (?,?)');
            $u->execute([$userId,$aid]);
            if ($u->rowCount() > 0) {
                awardExp($pdo,$userId,10,'achievement_unlock','Achievement unlocked','achievement-'.$userId.'-'.$code,['achievement'=>$code],false,null);
            }
        }
    }
}

function recommendationWeights(PDO $pdo): array {
    $cfg = economyConfig()['ranking'];
    if ($pdo->query("SHOW TABLES LIKE 'system_tuning'")->fetch()) {
        $rows = $pdo->query('SELECT tune_key, tune_value FROM system_tuning')->fetchAll();
        foreach ($rows as $row) {
            if (isset($cfg[$row['tune_key']])) $cfg[$row['tune_key']] = (float)$row['tune_value'];
        }
    }
    return $cfg;
}

function creatorEligibility(PDO $pdo, int $userId): array {
    ensureProgressionRow($pdo, $userId);
    $cfg = economyConfig()['eligibility'];
    $p = $pdo->prepare('SELECT creator_exp, creator_rank FROM user_progression WHERE user_id=?');
    $p->execute([$userId]);
    $progress = $p->fetch() ?: ['creator_exp'=>0,'creator_rank'=>'New Creator'];

    $followers = (int)$pdo->query('SELECT COUNT(*) FROM user_follows WHERE followed_id=' . (int)$userId)->fetchColumn();
    $views = (int)$pdo->query('SELECT COUNT(*) FROM video_views vv INNER JOIN videos v ON v.id=vv.video_id WHERE v.user_id=' . (int)$userId)->fetchColumn();
    $flags = [];
    if ((int)$progress['creator_exp'] < (int)$cfg['min_creator_exp_for_marketplace']) $flags[] = 'Need higher creator EXP';
    if ($followers < (int)$cfg['min_followers_for_payout_review']) $flags[] = 'Need more followers';
    if ($views < (int)$cfg['min_views_for_payout_review']) $flags[] = 'Need more views';

    $marketEligible = (int)$progress['creator_exp'] >= (int)$cfg['min_creator_exp_for_marketplace'];
    $payoutReady = $marketEligible && $followers >= (int)$cfg['min_followers_for_payout_review'] && $views >= (int)$cfg['min_views_for_payout_review'];

    return [
        'creator_exp' => (int)$progress['creator_exp'],
        'creator_rank' => (string)$progress['creator_rank'],
        'followers' => $followers,
        'views' => $views,
        'marketplace_eligible' => $marketEligible,
        'payout_review_eligible' => $payoutReady,
        'flags' => $flags,
    ];
}
