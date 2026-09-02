<?php
require __DIR__ . '/lib/init.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (password_verify($_POST['password'] ?? '', $CONFIG['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        redirect('index.php');
    }
    $error = 'Wrong password.';
}
$title = 'Log in';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GiftZy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css" rel="stylesheet">
</head>
<body>
<div class="login-shell">
  <form class="login-card" method="post">
    <img src="assets/giftzy-logo.svg" alt="GiftZy">
    <p>Sign in to manage your shop</p>
    <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
    <div>
      <label for="password">Password</label>
      <input class="input" type="password" name="password" id="password" placeholder="Enter password" autofocus>
    </div>
    <button class="btn primary" type="submit">Log in</button>
  </form>
</div>
</body>
</html>
