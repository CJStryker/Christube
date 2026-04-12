<?php
// Hardcoded user info for testing purposes
$user_id = '0001';
$username = 'Zesty';

// Define a simple directory for user uploads
// You can change this as needed
$target_dir = 'uploads/';

// Initialize upload flag and message variables
$uploadOk = 1;
$message = '';
$messageType = '';

// Check if form was submitted
if (isset($_POST["submit"])) {
    // Get the file extension of the uploaded file
    $imageFileType = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));
    
    // Check if file is an actual image
    $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
    if ($check !== false) {
        $message .= "File is an image - " . $check["mime"] . ".<br>";
        $messageType = 'success';
    } else {
        $message .= "File is not an image.<br>";
        $messageType = 'error';
        $uploadOk = 0;
    }
    
    // Create unique filename to avoid overwriting existing files
    $target_file = $target_dir . uniqid() . '.' . $imageFileType;
    
    // Check file size (limit: 5MB)
    if ($_FILES["fileToUpload"]["size"] > 5000000) {
        $message .= "Sorry, your file is too large.<br>";
        $messageType = 'error';
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    $allowed_types = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($imageFileType, $allowed_types)) {
        $message .= "Sorry, only JPG, JPEG, PNG & GIF files are allowed.<br>";
        $messageType = 'error';
        $uploadOk = 0;
    }
    
    // Attempt to upload if all checks are passed
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            $message .= "The file <strong>" . htmlspecialchars(basename($_FILES["fileToUpload"]["name"])) . "</strong> has been uploaded successfully!<br>";
            $messageType = 'success';
            $uploaded_image = $target_file;
        } else {
            $message .= "Sorry, there was an error uploading your file.<br>";
            $messageType = 'error';
        }
    }
} else {
    $message = "No file uploaded.<br>";
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Result</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin: -20px -20px 40px -20px;
        }

        .user-info {
            color: #333;
            font-weight: 600;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: transform 0.2s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .uploaded-image {
            text-align: center;
            margin: 20px 0;
        }

        .uploaded-image img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            transition: transform 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="user-info">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="container">
        <h1>Upload Result</h1>
        
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>

        <?php if ($messageType == 'success' && isset($uploaded_image)): ?>
        <div class="uploaded-image">
            <img src="<?php echo $uploaded_image; ?>" alt="Uploaded Image">
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">Upload Another Image</a>
            <a href="uploads/" class="btn btn-secondary">View My Gallery</a>
        </div>
    </div>
</body>
</html>
