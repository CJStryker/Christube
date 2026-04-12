<?php
require_once 'config.php'; // Include the config file

// Test database connection
try {
    $pdo->query("SELECT 1");
    echo "Connection is successful!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
