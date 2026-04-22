<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleMutation([
        'requireAuth' => true,
        'rateBucket' => 'buy_points',
        'onErrorRedirect' => 'buy_points.php',
    ], function () use ($pdo): void {
        $txHash = strtolower(trim($_POST['tx_hash'] ?? ''));
        $amountXmr = (float)($_POST['amount_xmr'] ?? 0);

        if (!validateTxHash($txHash)) {
            throw new RuntimeException('Invalid TX hash format. Use 64 hex chars.');
        }
        if ($amountXmr <= 0) {
            throw new RuntimeException('Amount must be greater than 0 XMR.');
        }

        $requestedXp = calculateXpFromXmr($amountXmr);
        if ($requestedXp < 1) {
            throw new RuntimeException('Amount too small for XP conversion.');
        }

        $stmt = $pdo->prepare('INSERT INTO xmr_point_requests (user_id, tx_hash, amount_xmr, requested_xp) VALUES (?, ?, ?, ?)');
        $stmt->execute([(int)$_SESSION['user_id'], $txHash, $amountXmr, $requestedXp]);

        setFlash(true, 'Point request submitted. Admin will verify transaction and approve XP.');
        header('Location: my_point_requests.php');
        exit;
    });
}

$flash = pullFlash();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Buy Points</title><link rel="stylesheet" href="public/styles.css"></head><body>
<div class="auth-container" style="max-width:800px;">
<?php if($flash): ?><div class="<?php echo $flash['ok'] ? 'success' : 'error'; ?>"><?php echo e((string)$flash['msg']); ?></div><?php endif; ?>
<h1>Purchase Experience Points (XMR)</h1><p>Send XMR to:</p><code><?php echo e(DONATION_XMR_ADDRESS); ?></code><p>Rate: <?php echo XP_PER_XMR; ?> XP per 1 XMR.</p>
<h2>Submit transaction for verification</h2><p>After payment, submit TX hash and amount. Verification is manual by admin.</p>
<form method="post"><?php echo csrfInput(); ?><label>TX Hash (64 hex)</label><input type="text" name="tx_hash" maxlength="128" required><label>Amount sent (XMR)</label><input type="number" name="amount_xmr" min="0.00000001" step="0.00000001" required><button type="submit">Submit for verification</button></form>
<p><a href="my_point_requests.php">View my requests</a> · <a href="index.php">Home</a></p></div></body></html>
