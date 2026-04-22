<?php
require_once 'config.php';
// Backward-compatible channel alias to profile route.
$u = $_GET['u'] ?? '';
header('Location: profile.php?u=' . urlencode((string)$u));
exit;
?>
