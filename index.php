<?php
//require_once 'config.php';

// Require user to be logged in
//requireLogin();

//$username = $_SESSION['username'];
$username = 'Zesty';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Image Upload Form</title>
  
  <style>
    /* Resetting default styles */
    body, html {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
    }

    /* Header with user info and logout */
    .header {
      background: rgba(255, 255, 255, 0.95);
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

    /* Styling the container */
    .container {
      width: 100%;
      max-width: 600px;
      margin: 50px auto;
      padding: 30px;
      background: rgba(255, 255, 255, 0.95);
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

    .welcome-text {
      text-align: center;
      color: #667eea;
      font-weight: 500;
      margin-bottom: 20px;
    }

    p {
      font-size: 16px;
      color: #555;
      text-align: center;
    }

    input[type="file"] {
      width: 100%;
      padding: 12px;
      margin: 15px 0;
      border-radius: 8px;
      border: 2px solid #e1e1e1;
      font-size: 14px;
      transition: border-color 0.3s ease;
    }

    input[type="file"]:focus {
      outline: none;
      border-color: #667eea;
    }

    input[type="submit"] {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease;
    }

    input[type="submit"]:hover {
      transform: translateY(-2px);
    }

    .image-preview {
      text-align: center;
      margin: 20px 0;
      padding: 20px;
      background: #f8f9ff;
      border-radius: 8px;
      border: 2px dashed #e1e1e1;
    }

    .image-preview img {
      max-width: 100%;
      max-height: 300px;
      object-fit: contain;
      border-radius: 8px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    /* Button to view uploads */
    .view-uploads-button {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 20px;
      transition: transform 0.2s ease;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }

    .view-uploads-button:hover {
      transform: translateY(-2px);
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="user-info">Welcome, <?php echo htmlspecialchars($username); ?>!</div>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>

  <div class="container">
    <h1>Upload an Image</h1>
    <div class="welcome-text">Your Personal Image Gallery</div>
    <p>Select an image file to upload. Make sure it is in JPG, JPEG, or PNG format and less than 5MB.</p>
    
    <form action="upload.php" method="post" enctype="multipart/form-data" id="uploadForm">
      <input type="file" name="fileToUpload" id="fileToUpload" accept="image/jpeg, image/png, image/jpg" required>
      
      <!-- Image preview will be shown here -->
      <div class="image-preview" id="imagePreview">
        <p>No image selected yet.</p>
      </div>
      
      <input type="submit" value="Upload Image" name="submit">
    </form>
    
    <!-- Button to view uploaded images -->
    <a style="display: block; width: 97%; text-align: center; padding: 10px; background-color: blue; color: white; text-decoration: none;" href="uploads/" class="view-uploads-button">View My Uploaded Images</a>
  </div>

  <script>
    const fileInput = document.getElementById('fileToUpload');
    const imagePreview = document.getElementById('imagePreview');
    
    // Preview image before uploading
    fileInput.addEventListener('change', function (event) {
      const file = event.target.files[0];
      if (file) {
        // Check file type
        const fileType = file.type;
        if (!fileType.startsWith('image/')) {
          alert("Please upload a valid image file (JPEG, PNG).");
          fileInput.value = '';  // Clear the file input
          imagePreview.innerHTML = '<p>No image selected yet.</p>';
          return;
        }
        // Check file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
          alert("The file size should be less than 5MB.");
          fileInput.value = '';  // Clear the file input
          imagePreview.innerHTML = '<p>No image selected yet.</p>';
          return;
        }
        // Create image URL and display the image preview
        const reader = new FileReader();
        reader.onload = function (e) {
          imagePreview.innerHTML = '<img src="' + e.target.result + '" alt="Image Preview">';
        };
        reader.readAsDataURL(file);
      }
    });
  </script>
</body>
</html>