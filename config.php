<?php
// Database configuration - It's better to use environment variables for sensitive data
$host = 'localhost';        // Host (usually localhost)
$dbname = 'user_auth';      // Database name
$username = 'user';         // Database username
$password = 'poopie';       // Database password (use hashed passwords for real apps)

// Setting up DSN for PDO
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";

// Options for PDO
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch associative arrays by default
    PDO::ATTR_EMULATE_PREPARES => false // Disable emulated prepared statements
];

try {
    // Establishing PDO connection
    $pdo = new PDO($dsn, $username, $password, $options);
    // Uncomment the next line for debugging (only in a development environment)
    // echo "Database connected successfully!";
} catch (PDOException $e) {
    // In case of a connection error, display a user-friendly message
    die("Connection failed: " . $e->getMessage());
}

// Start session for user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();  // Start session if not already started
}

// Function to check if the user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);  // Check if 'user_id' exists in session
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        // Redirect to login page if not logged in
        header('Location: login.php');
        exit;  // Make sure no further code is executed after redirection
    }
}

// Function to get the user's upload directory (e.g., based on their user ID)
function getUserUploadDir($user_id) {
    // User-specific upload directory
    $dir = "uploads/user_" . $user_id . "/";

    // Create the directory if it doesn't exist
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);  // Create directory with full permissions
    }

    return $dir;  // Return the directory path
}
?>
