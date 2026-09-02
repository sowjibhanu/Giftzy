<?php
$configFile = __DIR__ . '/../config.php';

if (!is_readable($configFile)) {
    http_response_code(500);
    exit('Setup: copy config.sample.php to config.php and fill in the database values.');
}

$CONFIG = require $configFile;

if (!extension_loaded('pdo_mysql')) {
    http_response_code(500);
    exit('Setup: the pdo_mysql PHP extension is not enabled on this server.');
}

date_default_timezone_set($CONFIG['timezone']);

session_start();

try {
    $db = new PDO(
        "mysql:host={$CONFIG['db_host']};dbname={$CONFIG['db_name']};charset=utf8mb4",
        $CONFIG['db_user'],
        $CONFIG['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES));
}

require __DIR__ . '/helpers.php';

if (basename($_SERVER['SCRIPT_NAME']) !== 'login.php' && empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
