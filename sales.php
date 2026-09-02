<?php
require __DIR__ . '/lib/init.php';
csrf_check();

if (($_POST['action'] ?? '') === 'delete') {
    $st = $db->prepare('DELETE FROM sales WHERE id = ?');
    $st->execute([(int) $_POST['id']]);
    redirect('sales.php?' . http_build_query($_GET), 'Sale entry deleted.');
}

$period = q('period');
$search = q('search');
$payment = q('payment_type');

[$where, $params] = build_where([
    ["DATE_FORMAT(sale_date,'%Y-%m') = ?", $period],
    ['(item LIKE ? OR customer LIKE ?)', $search === '' ? '' : ["%$search%", "%$search%"]],
    ['payment_type = ?', $payment],
]);

$perPage = 100;
$page = max(1, (int) q('page', 1));
$count = (int) scalar($db, "SELECT COUNT(*) FROM sales$where", $params);
$pages = max(1, (int) ceil($count / $perPage));
$page = min($page, $pages);

$list = rows($db, "SELECT * FROM sales$where ORDER BY sale_date DESC, id DESC
                   LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$totals = one($db, "SELECT COALESCE(SUM(total_amount),0) sales, COALESCE(SUM(profit),0) profit,
                           COALESCE(SUM(pending_amount),0) pending FROM sales$where", $params);

$title = 'Sales';
require __DIR__ . '/partials/header.php';
?>
<div class="page-head">
  <h1 class="title">Sales</h1>
  <a class="btn primary" href="sale_form.php">Add sale</a>
</div>

<form class="filters" method="get">
  <?= select_field('period', month_options($db, 'sales', 'sale_date'), $period, 'All months') ?>
  <?= select_field('payment_type', PAYMENT_TYPES, $payment, 'Any payment') ?>
  <div class="grow">
    <input class="input" name="search" value="<?= h($search) ?>" placeholder="Item or customer">
  </div>
  <button class="btn">Filter</button>
</form>

<div class="hint">
  <?= number_format($count) ?> entries &middot; sales <strong><?= money($totals['sales']) ?></strong>
  &middot; profit <strong class="pos"><?= money($totals['profit']) ?></strong>
  &middot; pending <strong class="warn"><?= money($totals['pending']) ?></strong>
</div>

<div class="table-card"><div class="table-scroll">
  <table class="data">
    <thead><tr>
      <th>Date</th><th>Item</th><th class="num">Qty</th><th class="num">Price</th>
      <th class="num">Total</th><th class="num">Cost</th><th class="num">Profit</th>
      <th>Customer</th><th>Payment</th><th class="num">Pending</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr>
        <td class="dim nowrap"><?= h($r['sale_date']) ?></td>
        <td class="wide"><?= h($r['item']) ?></td>
        <td class="num"><?= h((float) $r['qty']) ?></td>
        <td class="num"><?= money($r['selling_price']) ?></td>
        <td class="num strong"><?= money($r['total_amount']) ?></td>
        <td class="num"><?= money($r['cost_price']) ?></td>
        <td class="num pos"><?= money($r['profit']) ?></td>
        <td class="nowrap"><?= h($r['customer']) ?></td>
        <td><?= pill($r['payment_type']) ?></td>
        <td class="num <?= $r['pending_amount'] > 0 ? 'warn' : 'dim' ?>">
          <?= $r['pending_amount'] > 0 ? money($r['pending_amount']) : '&mdash;' ?>
        </td>
        <td class="nowrap">
          <a class="icon-btn" href="sale_form.php?id=<?= (int) $r['id'] ?>" title="Edit" aria-label="Edit">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M14.85 2.85a1.5 1.5 0 012.12 2.12L6 15.94 2.5 17l1.06-3.5L14.85 2.85z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
          </a>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete this entry?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="icon-btn danger" title="Delete" aria-label="Delete">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="none"><path d="M4 6h12M8 6V4h4v2M6 6l1 10h6l1-10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>

<?php if ($pages > 1): ?>
<nav class="pager">
  <?php for ($p = 1; $p <= $pages; $p++): $qs = $_GET; $qs['page'] = $p; ?>
    <a class="<?= $p === $page ? 'active' : '' ?>" href="?<?= h(http_build_query($qs)) ?>"><?= $p ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
