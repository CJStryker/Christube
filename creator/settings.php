<?php
require_once '../includes/layout.php';
requireLogin();
$user=(string)$_SESSION['username'];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Channel Settings</title><link rel="stylesheet" href="../public/styles.css"></head><body><?php renderTopbar('Creator Studio', creatorNav('../')); ?><div class="auth-container" style="max-width:900px"><h1>Channel Settings</h1><p>Manage your channel identity and presentation settings.</p><p><a href="../edit_profile.php">Edit Profile / Avatar / Banner / Bio</a></p><p><a href="../profile.php?u=<?php echo urlencode($user); ?>">Preview Public Channel</a></p><div class="panel"><h3>Future Scaffolding</h3><p class="tiny">Channel sections/tabs customization, social links, and branding presets will be added in a future phase.</p></div></div></body></html>
