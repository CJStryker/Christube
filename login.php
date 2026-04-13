<?php
require_once 'config.php';

$error = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #050505;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .auth-container {
            background: #101010;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #00ff66;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #87fcb0;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #7a0000;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #7a0000;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: #7a0000;
            color: #0a0a0a;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        

        .tos-box {
            margin-bottom: 1rem;
            padding: 0.9rem;
            border: 1px solid #7a0000;
            border-radius: 8px;
            background: #090909;
            color: #9dffc3;
            max-height: 280px;
            overflow-y: auto;
            line-height: 1.45;
            font-size: 0.9rem;
        }

        .tos-box h3 {
            margin: 0 0 0.6rem 0;
            color: #00ff66;
        }

        .tos-box p {
            margin: 0 0 0.7rem 0;
        }

        .error {
            background-color: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #fcc;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 1rem;
        }
        
        .auth-links a {
            color: #00ff66;
            text-decoration: none;
            font-weight: 500;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2>Login</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="tos-box">
            <h3>Terms of Service & Content Policy</h3>
            <p>By using Christube, you agree to these Terms of Service. This platform is for lawful video sharing only. Any content involving abuse, exploitation, coercion, non-consensual acts, minors, or any other illegal abusive material is strictly forbidden and will result in immediate enforcement action.</p>
            <p>Adult content (including consensual pornographic content) is permitted on this service only where it is legal, consensual, and compliant with all applicable laws. You are solely responsible for ensuring that any upload you submit is lawful in your jurisdiction and does not violate the rights, privacy, or safety of others.</p>
            <p>Private uploads are supported for user privacy, but private status does <strong>not</strong> exempt content from policy review. Private uploads must not be used to store, share, or distribute prohibited material. Attempts to misuse private uploads for abuse, harassment, exploitation, or illegal distribution are prohibited and may be reported to appropriate authorities.</p>
            <p>We actively monitor and moderate uploads, metadata, and abuse reports. Accounts may be suspended or terminated without notice for policy violations. Content may be removed immediately where risk, harm, or illegality is suspected. We reserve the right to preserve and disclose relevant records to legal authorities where required by law or to protect users and the public.</p>
            <p>By continuing, you confirm that you are legally allowed to access this site, will not upload abusive or illegal material, and understand that violations will be dealt with swiftly, including content removal, account termination, and escalation to law enforcement when appropriate.</p>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="auth-links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>