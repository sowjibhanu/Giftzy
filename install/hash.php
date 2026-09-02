<?php
// Usage (locally or over SSH):  php install/hash.php "my new password"
// Or upload it, open install/hash.php?p=my+new+password in the browser, copy
// the hash into config.php, then DELETE this file from the server.
$plain = $argv[1] ?? ($_GET['p'] ?? null);
if (!$plain) {
    exit("Pass a password: php install/hash.php \"my password\"\n");
}
echo password_hash($plain, PASSWORD_DEFAULT), "\n";
