<?php
require_once 'config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$jobStmt = $pdo->query("SELECT * FROM media_jobs WHERE status = 'queued' AND job_type = 'process_video' AND run_after <= NOW() ORDER BY id ASC LIMIT 5");
$jobs = $jobStmt->fetchAll();

foreach ($jobs as $job) {
    $jobId = (int)$job['id'];
    $videoId = (int)$job['video_id'];
    $pdo->prepare("UPDATE media_jobs SET status='running', attempts=attempts+1, locked_at=NOW() WHERE id=?")->execute([$jobId]);

    try {
        $vStmt = $pdo->prepare('SELECT * FROM videos WHERE id = ? LIMIT 1');
        $vStmt->execute([$videoId]);
        $video = $vStmt->fetch();
        if (!$video) {
            throw new RuntimeException('Video not found');
        }

        $assetStmt = $pdo->prepare('SELECT * FROM media_assets WHERE id = ? LIMIT 1');
        $assetStmt->execute([(int)$video['source_asset_id']]);
        $source = $assetStmt->fetch();
        if (!$source) {
            throw new RuntimeException('Source asset missing');
        }

        $sourceAbs = mediaResolveAbsolutePath((string)$source['storage_path']);
        if (!is_file($sourceAbs)) {
            throw new RuntimeException('Source file missing on disk');
        }

        $paths = mediaPaths();
        $playRel = 'playback/' . date('Y/m') . '/video_' . $videoId . '_720p.mp4';
        $thumbRel = 'thumbnails/' . date('Y/m') . '/video_' . $videoId . '.jpg';
        $playAbs = $paths['root'] . '/' . $playRel;
        $thumbAbs = $paths['root'] . '/' . $thumbRel;
        mediaEnsureDir(dirname($playAbs));
        mediaEnsureDir(dirname($thumbAbs));

        $ffmpeg = trim((string)shell_exec('command -v ffmpeg'));
        $ffprobe = trim((string)shell_exec('command -v ffprobe'));

        if ($ffmpeg !== '') {
            $cmd = sprintf('%s -y -i %s -vf "scale=1280:-2" -c:v libx264 -preset veryfast -crf 23 -c:a aac -movflags +faststart %s 2>&1', escapeshellcmd($ffmpeg), escapeshellarg($sourceAbs), escapeshellarg($playAbs));
            exec($cmd, $out, $code);
            if ($code !== 0) {
                copy($sourceAbs, $playAbs);
            }

            $thumbCmd = sprintf('%s -y -ss 00:00:01 -i %s -vframes 1 -q:v 2 %s 2>&1', escapeshellcmd($ffmpeg), escapeshellarg($sourceAbs), escapeshellarg($thumbAbs));
            exec($thumbCmd);
        } else {
            copy($sourceAbs, $playAbs);
        }

        $duration = null;
        $width = null;
        $height = null;
        if ($ffprobe !== '') {
            $probe = sprintf('%s -v quiet -print_format json -show_streams -show_format %s', escapeshellcmd($ffprobe), escapeshellarg($sourceAbs));
            $json = shell_exec($probe);
            $meta = is_string($json) ? json_decode($json, true) : null;
            if (is_array($meta)) {
                $duration = isset($meta['format']['duration']) ? (float)$meta['format']['duration'] : null;
                foreach (($meta['streams'] ?? []) as $stream) {
                    if (($stream['codec_type'] ?? '') === 'video') {
                        $width = isset($stream['width']) ? (int)$stream['width'] : null;
                        $height = isset($stream['height']) ? (int)$stream['height'] : null;
                        break;
                    }
                }
            }
        }

        $pdo->beginTransaction();
        $insertAsset = $pdo->prepare('INSERT INTO media_assets (asset_kind, storage_disk, storage_path, mime_type, size_bytes, width, height, duration_seconds, status) VALUES (?,?,?,?,?,?,?,?,?)');
        $insertAsset->execute(['playback', 'local', $playRel, 'video/mp4', is_file($playAbs) ? filesize($playAbs) : null, $width, $height, $duration, 'ready']);
        $playId = (int)$pdo->lastInsertId();

        $thumbId = null;
        if (is_file($thumbAbs)) {
            $insertAsset->execute(['thumbnail', 'local', $thumbRel, 'image/jpeg', filesize($thumbAbs), $width, $height, null, 'ready']);
            $thumbId = (int)$pdo->lastInsertId();
        }

        $update = $pdo->prepare('UPDATE videos SET playback_asset_id=?, thumbnail_asset_id=?, processing_status=?, duration_seconds=?, width=?, height=?, ready_at=NOW(), file_path=? WHERE id=?');
        $update->execute([$playId, $thumbId, MEDIA_STATUS_READY, $duration, $width, $height, $playRel, $videoId]);
        $pdo->prepare("UPDATE media_jobs SET status='completed', last_error=NULL WHERE id=?")->execute([$jobId]);
        $pdo->commit();

        auditEvent($pdo, (int)$video['user_id'], 'video_processing_completed', ['video_id' => $videoId]);
        notifyUser($pdo, (int)$video['user_id'], 'upload_processed', 'Upload processing complete', 'Your video is ready to watch.', 'video', $videoId, 'processing-ok-' . $videoId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        $pdo->prepare("UPDATE media_jobs SET status = IF(attempts >= max_attempts, 'failed', 'queued'), run_after = DATE_ADD(NOW(), INTERVAL 2 MINUTE), last_error=? WHERE id=?")
            ->execute([substr($e->getMessage(), 0, 250), $jobId]);
        $pdo->prepare('UPDATE videos SET processing_status=?, processing_error=? WHERE id=?')->execute([MEDIA_STATUS_FAILED, substr($e->getMessage(), 0, 250), $videoId]);
        $ownerId = (int)$pdo->query('SELECT user_id FROM videos WHERE id=' . $videoId)->fetchColumn();
        if ($ownerId > 0) {
            notifyUser($pdo, $ownerId, 'upload_failed', 'Upload processing failed', 'A video failed processing. You can review it in Creator Studio.', 'video', $videoId, 'processing-failed-' . $videoId);
        }
    }
}

echo 'Processed ' . count($jobs) . " jobs\n";
