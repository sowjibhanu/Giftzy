<?php
$nav = [
    'index.php' => ['Dashboard', '<path d="M4 14V8M10 14V4M16 14v-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'],
    'sales.php' => ['Sales', '<path d="M9 3H4a1 1 0 00-1 1v5l8.5 8.5a1 1 0 001.4 0l4.6-4.6a1 1 0 000-1.4L9 3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="6.5" cy="6.5" r="1" fill="currentColor"/>'],
    'expenses.php' => ['Expenses', '<path d="M5 2h10v16l-2-1.3L11 18l-2-1.3L7 18l-2-1.3V2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7 6h6M7 9h6M7 12h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>'],
    'investments.php' => ['Investments', '<path d="M3 13l5-5 3 3 6-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 5h4v4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>'],
    'monthly.php' => ['Monthly', '<rect x="3" y="4" width="14" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M3 8h14M7 2v3M13 2v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>'],
    'balances.php' => ['Balances', '<rect x="2" y="6" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M2 9h16" stroke="currentColor" stroke-width="1.4"/><circle cx="14" cy="12.5" r="1.1" fill="currentColor"/>'],
];
$formPages = [
    'sale_form.php' => 'sales.php',
    'expense_form.php' => 'expenses.php',
    'investment_form.php' => 'investments.php',
];
$current = basename($_SERVER['SCRIPT_NAME']);
$active = $formPages[$current] ?? $current;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title ?? 'GiftZy') ?> &middot; GiftZy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/app.css" rel="stylesheet">
</head>
<body>
<div class="app">
  <div class="topbar">
    <div class="topbar-left">
      <button class="hamburger" type="button" aria-label="Menu" aria-controls="drawer" aria-expanded="false" id="menu-button">
        <span></span><span></span><span></span>
      </button>
      <a class="brand" href="index.php"><img src="assets/giftzy-logo.svg" alt="GiftZy"></a>
    </div>
    <a class="btn" href="logout.php">Log out</a>
  </div>

  <div class="scrim" id="scrim"></div>
  <nav class="drawer" id="drawer">
    <div class="drawer-brand"><img src="assets/giftzy-logo.svg" alt="GiftZy"></div>
    <?php foreach ($nav as $file => [$label, $icon]): ?>
      <a class="nav-link<?= $active === $file ? ' active' : '' ?>" href="<?= h($file) ?>">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none"><?= $icon ?></svg><?= h($label) ?>
      </a>
    <?php endforeach; ?>
    <div class="drawer-spacer"></div>
    <a class="btn" href="logout.php">Log out</a>
  </nav>

  <div class="page">
  <?= flash() ?>
