<?php
require_once 'config.php';
if (env('APP_ENV', 'production') !== 'development') {
    http_response_code(404);
    echo 'Not Found';
    exit;
}
echo 'Test endpoint is enabled (development mode).';
?>
