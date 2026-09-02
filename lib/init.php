<?php
$CONFIG = require __DIR__ . '/../config.php';
date_default_timezone_set($CONFIG['timezone']);

session_start();

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

require __DIR__ . '/helpers.php';

if (basename($_SERVER['SCRIPT_NAME']) !== 'login.php' && empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
