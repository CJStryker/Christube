<?php
//require_once '../config.php';

// Require user to be logged in
//requireLogin();

//$user_id = $_SESSION['user_id'];
//$username = $_SESSION['username'];

$user_id = '0001';
$username = 'Zesty';

// Get user's upload directory
//$user_upload_dir = getUserUploadDir($user_id);

$user_upload_dir = './';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Image Gallery</title>
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
            border-radius: 0 0 15px 15px;
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
            max-width: 1200px;
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
            margin-bottom: 10px;
            font-weight: 600;
        }

        .subtitle {
            text-align: center;
            color: #667eea;
            font-weight: 500;
            margin-bottom: 40px;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .image-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .image-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .image-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .image-card:hover img {
            transform: scale(1.05);
        }

        .image-info {
            padding: 15px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
        }

        .image-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            margin-bottom: 5px;
            word-break: break-word;
        }

        .image-date {
            color: #666;
            font-size: 12px;
        }

        .no-images {
            text-align: center;
            color: #666;
            font-size: 18px;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .no-images-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .back-button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s ease;
        }

        .back-button:hover {
            transform: translateY(-2px);
        }

        .stats {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 15px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
            border-radius: 10px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        /* Lightbox styles */
        .lightbox {
            display: none;
            position: fixed;
            z-index: 1000;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }

        .lightbox-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 80%;
            border-radius: 8px;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="user-info">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="container">
        <h1>My Image Gallery</h1>
        <div class="subtitle">Your personal collection</div>

        <?php
        // Get all files in the user's upload directory
        $images = [];
        if (is_dir($user_upload_dir)) {
            $files = array_diff(scandir($user_upload_dir), array('..', '.'));
            
            // Define supported image extensions
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'];
            
            // Filter the files to include only valid image files
            foreach ($files as $file) {
                $filePath = $user_upload_dir . $file;  // Corrected file path
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if (is_file($filePath) && in_array($extension, $imageExtensions)) {
                    $images[] = [
                        'name' => $file,
                        'path' => $filePath,
                        'date' => filemtime($filePath)
                    ];
                }
            }
            
            // Sort by date, newest first
            usort($images, function($a, $b) {
                return $b['date'] - $a['date'];
            });
        }
        
        $imageCount = count($images);
        ?>

        <?php if ($imageCount > 0): ?>
        <div class="stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo $imageCount; ?></div>
                <div class="stat-label">Images</div>
            </div>
        </div>

        <div class="gallery">
            <?php foreach ($images as $image): ?>
            <div class="image-card">
                <img src="<?php echo $user_upload_dir . $image['name']; ?>" 
                     alt="<?php echo htmlspecialchars($image['name']); ?>"
                     onclick="openLightbox('<?php echo $user_upload_dir . $image['name']; ?>')">
                <div class="image-info">
                    <div class="image-name"><?php echo htmlspecialchars($image['name']); ?></div>
                    <div class="image-date"><?php echo date('M j, Y - g:i A', $image['date']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-images">
            <div class="no-images-icon">🖼️</div>
            <p><strong>No images uploaded yet.</strong></p>
            <p>Start building your gallery by uploading your first image!</p>
        </div>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="../index.php" class="back-button">Upload New Image</a>
        </div>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <span class="close">&times;</span>
        <img class="lightbox-content" id="lightbox-img">
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightbox').style.display = 'block';
            document.getElementById('lightbox-img').src = src;
        }

        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
        }

        // Close lightbox when clicking the close button
        document.querySelector('.close').onclick = closeLightbox;

        // Close lightbox with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeLightbox();
            }
        });
    </script>
</body>
</html>
