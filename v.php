<?php
require_once 'config.php';
$slug = trim($_GET['s'] ?? '');
if ($slug === '') {
    header('Location: index.php');
    exit;
}
header('Location: view.php?v=' . urlencode($slug));
exit;
?>
