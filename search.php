<?php
require_once 'includes/layout.php';
require_once 'includes/components.php';

$q = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'relevance');
$type = (string)($_GET['type'] ?? 'all');
$duration = (string)($_GET['duration'] ?? 'any');
$resultsVideos = [];
$resultsChannels = [];
$resultsPlaylists = [];

if ($q !== '') {
    $resultsVideos = searchVideosAndChannels($pdo, $q, $sort, 120);

    if ($duration !== 'any') {
        $resultsVideos = array_values(array_filter($resultsVideos, function ($v) use ($duration) {
            $d = (float)($v['duration_seconds'] ?? 0);
            return match ($duration) {
                'short' => $d > 0 && $d < 240,
                'medium' => $d >= 240 && $d < 1200,
                'long' => $d >= 1200,
                default => true,
            };
        }));
    }

    $term = '%' . $q . '%';
    if ($type === 'all' || $type === 'channels') {
        $ch = $pdo->prepare("SELECT u.id,u.username,u.display_name,COALESCE(up.creator_level,1) creator_level,
            (SELECT COUNT(*) FROM user_follows f WHERE f.followed_id=u.id) AS followers
            FROM users u LEFT JOIN user_progression up ON up.user_id=u.id
            WHERE u.username LIKE ? OR u.display_name LIKE ? ORDER BY followers DESC LIMIT 30");
        $ch->execute([$term, $term]);
        $resultsChannels = $ch->fetchAll();
    }

    if ($type === 'all' || $type === 'playlists') {
        $pl = $pdo->prepare("SELECT p.slug,p.title,p.visibility,u.username,
            (SELECT COUNT(*) FROM playlist_videos pv WHERE pv.playlist_id=p.id) AS item_count
            FROM playlists p INNER JOIN users u ON u.id=p.user_id
            WHERE (p.visibility='public' OR p.visibility='unlisted') AND p.title LIKE ? ORDER BY item_count DESC LIMIT 30");
        $pl->execute([$term]);
        $resultsPlaylists = $pl->fetchAll();
    }

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid > 0) {
        $pdo->prepare('INSERT INTO search_saved_queries (user_id, query_text) VALUES (?, ?)')->execute([$uid, $q]);
    }
    $pdo->prepare('INSERT INTO search_trending_queries (query_text, hits) VALUES (?,1) ON DUPLICATE KEY UPDATE hits = hits + 1')->execute([$q]);

    trackProductEvent($pdo, 'search_performed', $uid ?: null, ['query'=>$q,'sort'=>$sort,'type'=>$type,'count_videos'=>count($resultsVideos)]);
}

$recentQueries = [];
if (isset($_SESSION['user_id'])) {
    $rq = $pdo->prepare('SELECT query_text FROM search_saved_queries WHERE user_id=? ORDER BY created_at DESC LIMIT 8');
    $rq->execute([(int)$_SESSION['user_id']]);
    $recentQueries = array_unique(array_map(fn($r)=>(string)$r['query_text'], $rq->fetchAll()));
}
$trendingQueries = $pdo->query('SELECT query_text,hits FROM search_trending_queries ORDER BY hits DESC, updated_at DESC LIMIT 8')->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Search</title><link rel="stylesheet" href="public/styles.css"></head><body>
<?php renderTopbar('Christube',['index.php'=>'Home','trending.php'=>'Trending','subscriptions.php'=>'Subscriptions','playlists.php'=>'Playlists','creator/dashboard.php'=>'Creator Studio']); ?>
<div class="page"><main class="main"><div class="panel"><h1>Search</h1><form><input name="q" value="<?php echo e($q); ?>" placeholder="Search videos, creators, playlists"><select name="type"><option value="all" <?php echo $type==='all'?'selected':''; ?>>All</option><option value="videos" <?php echo $type==='videos'?'selected':''; ?>>Videos</option><option value="channels" <?php echo $type==='channels'?'selected':''; ?>>Channels</option><option value="playlists" <?php echo $type==='playlists'?'selected':''; ?>>Playlists</option></select><select name="sort"><option value="relevance" <?php echo $sort==='relevance'?'selected':''; ?>>Relevance</option><option value="newest" <?php echo $sort==='newest'?'selected':''; ?>>Newest</option><option value="popular" <?php echo $sort==='popular'?'selected':''; ?>>Popular</option></select><select name="duration"><option value="any" <?php echo $duration==='any'?'selected':''; ?>>Any length</option><option value="short" <?php echo $duration==='short'?'selected':''; ?>>Short</option><option value="medium" <?php echo $duration==='medium'?'selected':''; ?>>Medium</option><option value="long" <?php echo $duration==='long'?'selected':''; ?>>Long</option></select><button type="submit">Search</button></form></div>
<div class="panel"><h3>Recent Searches</h3><?php if(!$recentQueries): ?><p class="muted">No recent searches.</p><?php else: ?><p><?php foreach($recentQueries as $qq): ?><a href="search.php?q=<?php echo urlencode($qq); ?>"><?php echo e($qq); ?></a> · <?php endforeach; ?></p><?php endif; ?><h3>Trending Searches</h3><?php if(!$trendingQueries): ?><p class="muted">No trends yet.</p><?php else: ?><p><?php foreach($trendingQueries as $tq): ?><a href="search.php?q=<?php echo urlencode($tq['query_text']); ?>"><?php echo e($tq['query_text']); ?></a> (<?php echo (int)$tq['hits']; ?>) · <?php endforeach; ?></p><?php endif; ?></div>
<div class="panel"><?php if($q===''): ?><p class="muted">Search for videos, creators, and playlists.</p><?php else: ?>
<?php if($type==='all' || $type==='videos'): ?><h2>Videos</h2><?php if(!$resultsVideos): ?><p class="muted">No matching videos.</p><?php else: ?><div class="grid"><?php foreach($resultsVideos as $v){ renderVideoCard($v);} ?></div><?php endif; ?><?php endif; ?>
<?php if($type==='all' || $type==='channels'): ?><h2>Channels</h2><?php if(!$resultsChannels): ?><p class="muted">No matching creators.</p><?php else: ?><ul><?php foreach($resultsChannels as $c): ?><li><a href="profile.php?u=<?php echo urlencode($c['username']); ?>">@<?php echo e($c['username']); ?></a> · Level <?php echo (int)$c['creator_level']; ?> · Followers <?php echo (int)$c['followers']; ?></li><?php endforeach; ?></ul><?php endif; ?><?php endif; ?>
<?php if($type==='all' || $type==='playlists'): ?><h2>Playlists</h2><?php if(!$resultsPlaylists): ?><p class="muted">No matching playlists.</p><?php else: ?><ul><?php foreach($resultsPlaylists as $p): ?><li><a href="playlist.php?p=<?php echo urlencode($p['slug']); ?>"><?php echo e($p['title']); ?></a> by @<?php echo e($p['username']); ?> (<?php echo (int)$p['item_count']; ?> items)</li><?php endforeach; ?></ul><?php endif; ?><?php endif; ?>
<?php endif; ?></div></main></div></body></html>
