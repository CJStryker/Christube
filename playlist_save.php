<?php
require_once 'config.php';
requireLogin();
handleMutation([
 'requireAuth'=>true,
 'rateBucket'=>'playlist_save',
 'onErrorRedirect'=>'playlists.php',
], function() use ($pdo): void {
    $mode = (string)($_POST['mode'] ?? '');
    $userId = (int)$_SESSION['user_id'];

    if ($mode === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $visibility = (string)($_POST['visibility'] ?? 'private');
        if ($title === '' || !in_array($visibility,['public','private','unlisted'],true)) throw new RuntimeException('Invalid playlist.');
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $title), '-')) . '-' . bin2hex(random_bytes(3));
        $pdo->prepare('INSERT INTO playlists (user_id,slug,title,description,visibility) VALUES (?,?,?,?,?)')->execute([$userId,$slug,$title,$description,$visibility]);
        trackProductEvent($pdo,'playlist_created',$userId,['playlist_slug'=>$slug]);
        setFlash(true,'Playlist created.');
        header('Location: playlist.php?p=' . urlencode($slug));
        exit;
    }

    if ($mode === 'add_video') {
        $videoId = (int)($_POST['video_id'] ?? 0);
        $playlistId = (int)($_POST['playlist_id'] ?? 0);
        if ($playlistId < 1) {
            $playlistId = ensureWatchLaterPlaylist($pdo, $userId);
        }
        $own = $pdo->prepare('SELECT id,slug FROM playlists WHERE id=? AND user_id=? LIMIT 1');
        $own->execute([$playlistId,$userId]);
        $playlist = $own->fetch();
        if (!$playlist || $videoId < 1) throw new RuntimeException('Invalid playlist add.');
        $pos = (int)$pdo->query('SELECT COALESCE(MAX(position_index),0)+1 FROM playlist_videos WHERE playlist_id=' . $playlistId)->fetchColumn();
        $pdo->prepare('INSERT IGNORE INTO playlist_videos (playlist_id,video_id,position_index) VALUES (?,?,?)')->execute([$playlistId,$videoId,$pos]);
        trackProductEvent($pdo,'playlist_add_video',$userId,['playlist_id'=>$playlistId,'video_id'=>$videoId]);
        setFlash(true, 'Saved to playlist.');
        $slug = (string)$pdo->query('SELECT slug FROM videos WHERE id=' . $videoId)->fetchColumn();
        header('Location: v.php?s=' . urlencode($slug));
        exit;
    }

    if ($mode === 'remove_video') {
        $playlistId=(int)($_POST['playlist_id'] ?? 0);
        $videoSlug=trim((string)($_POST['video_slug'] ?? ''));
        $v=$pdo->prepare('SELECT id FROM videos WHERE slug=?');$v->execute([$videoSlug]);$videoId=(int)$v->fetchColumn();
        $pdo->prepare('DELETE pv FROM playlist_videos pv INNER JOIN playlists p ON p.id=pv.playlist_id WHERE pv.playlist_id=? AND pv.video_id=? AND p.user_id=?')->execute([$playlistId,$videoId,$userId]);
        setFlash(true,'Removed from playlist.');
        $slug = (string)$pdo->query('SELECT slug FROM playlists WHERE id=' . $playlistId)->fetchColumn();
        header('Location: playlist.php?p=' . urlencode($slug));
        exit;
    }

    throw new RuntimeException('Unsupported playlist action.');
});
