<?php
require_once 'config.php';
$slug = trim((string)($_GET['s'] ?? ''));
$list = trim((string)($_GET['list'] ?? ''));
if ($slug === '') { header('Location: index.php'); exit; }
$url = 'view.php?v=' . urlencode($slug);
if ($list !== '') { $url .= '&list=' . urlencode($list); }
header('Location: ' . $url);
exit;
?>
