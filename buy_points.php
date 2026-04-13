<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txHash = strtolower(trim($_POST['tx_hash'] ?? ''));
    $amountXmr = (float)($_POST['amount_xmr'] ?? 0);

    if (!preg_match('/^[a-f0-9]{64}$/', $txHash)) {
        $_SESSION['flash'] = ['ok' => false, 'msg' => 'Invalid TX hash format. Use 64 hex chars.'];
        header('Location: buy_points.php');
        exit;
    }

    if ($amountXmr <= 0) {
        $_SESSION['flash'] = ['ok' => false, 'msg' => 'Amount must be greater than 0 XMR.'];
        header('Location: buy_points.php');
        exit;
    }

    $requestedXp = calculateXpFromXmr($amountXmr);
    if ($requestedXp < 1) {
        $_SESSION['flash'] = ['ok' => false, 'msg' => 'Amount too small for XP conversion.'];
        header('Location: buy_points.php');
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO xmr_point_requests (user_id, tx_hash, amount_xmr, requested_xp) VALUES (?, ?, ?, ?)');
    try {
        $stmt->execute([(int)$_SESSION['user_id'], $txHash, $amountXmr, $requestedXp]);
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['ok' => false, 'msg' => 'This TX hash already exists or request failed.'];
        header('Location: buy_points.php');
        exit;
    }

    $_SESSION['flash'] = ['ok' => true, 'msg' => 'Point request submitted. Admin will verify transaction and approve XP.'];
    header('Location: my_point_requests.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Buy Points</title>
<style>body{margin:0;background:#050505;color:#00ff66;font-family:Arial}.wrap{max-width:800px;margin:24px auto;padding:0 14px}.panel{background:#101010;border:1px solid #7a0000;border-radius:10px;padding:14px;margin-bottom:12px}input{width:100%;background:#000;color:#00ff66;border:1px solid #7a0000;border-radius:6px;padding:10px;margin:6px 0 12px}button{background:#7a0000;color:#0a0a0a;border:0;border-radius:6px;padding:10px 14px;font-weight:bold}a{color:#00ff66}.flash{padding:10px;border:1px solid #7a0000;background:#1c1c1c;border-radius:8px;margin-bottom:12px}code{display:block;overflow-wrap:anywhere}</style></head><body>
<div class="wrap">
<?php if($flash): ?><div class="flash"><?php echo htmlspecialchars($flash['msg']); ?></div><?php endif; ?>
<div class="panel"><h1>Purchase Experience Points (XMR)</h1><p>Send XMR to this address:</p><code><?php echo htmlspecialchars(DONATION_XMR_ADDRESS); ?></code><p>Current rate: <?php echo XP_PER_XMR; ?> XP per 1 XMR.</p></div>
<div class="panel"><h2>Submit transaction for verification</h2><p>After payment, submit your TX hash and amount. Verification is currently manual by admin.</p>
<form method="post"><label>TX Hash (64 hex)</label><input type="text" name="tx_hash" maxlength="128" required><label>Amount sent (XMR)</label><input type="number" name="amount_xmr" min="0.00000001" step="0.00000001" required><button type="submit">Submit for verification</button></form>
<p><a href="my_point_requests.php">View my requests</a> · <a href="index.php">Home</a></p></div>
</div></body></html>
