<?php
// Setup diagnostics. Delete this file once the app is running.
header('Content-Type: text/plain; charset=utf-8');

function line($label, $value)
{
    echo str_pad($label, 26) . $value . "\n";
}

line('PHP version', PHP_VERSION);
line('pdo_mysql', extension_loaded('pdo_mysql') ? 'yes' : 'MISSING');
line('mbstring', extension_loaded('mbstring') ? 'yes' : 'missing (optional)');

$configFile = __DIR__ . '/config.php';
line('config.php', is_readable($configFile) ? 'found' : 'MISSING or unreadable');

if (!is_readable($configFile)) {
    exit("\nCopy config.sample.php to config.php and fill in the database values.\n");
}

$CONFIG = require $configFile;
foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'password_hash'] as $key) {
    $set = !empty($CONFIG[$key]) && $CONFIG[$key] !== 'CHANGE_ME';
    line('config ' . $key, $set ? 'set' : 'NOT SET');
}

try {
    $db = new PDO(
        "mysql:host={$CONFIG['db_host']};dbname={$CONFIG['db_name']};charset=utf8mb4",
        $CONFIG['db_user'],
        $CONFIG['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    line('database', 'connected');
    foreach (['sales', 'expenses', 'investments', 'monthly_collections', 'settings'] as $table) {
        try {
            $n = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            line('table ' . $table, $n . ' rows');
        } catch (PDOException $e) {
            line('table ' . $table, 'MISSING');
        }
    }
} catch (PDOException $e) {
    line('database', 'FAILED');
    echo "\n" . $e->getMessage() . "\n";
}

echo "\nDelete check.php when you are done.\n";
