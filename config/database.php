<?php
// Load configuration from environment or config file
$config = require_once __DIR__ . '/config.php';

define('DB_HOST', $config['db_host'] ?? 'localhost');
define('DB_USER', $config['db_user'] ?? 'root');
define('DB_PASS', $config['db_pass'] ?? '');
define('DB_NAME', $config['db_name'] ?? 'class_portal');

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}
?> 