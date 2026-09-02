<?php
require __DIR__ . '/lib/init.php';
csrf_check();

if (($_POST['action'] ?? '') === 'delete') {
    $st = $db->prepare('DELETE FROM investments WHERE id = ?');
    $st->execute([(int) $_POST['id']]);
    redirect('investments.php', 'Investment deleted.');
}

$source = q('fund_source');
$category = q('category');
[$where, $params] = build_where([
    ['fund_source = ?', $source],
    ['category = ?', $category],
]);

$categories = array_column(rows($db, "SELECT DISTINCT category FROM investments
                                      WHERE category IS NOT NULL AND category <> ''
                                      ORDER BY category"), 'category');

$list = rows($db, "SELECT * FROM investments$where ORDER BY inv_date IS NULL, inv_date DESC, id DESC", $params);
$sum = scalar($db, "SELECT COALESCE(SUM(amount),0) FROM investments$where", $params);

// The investment is the partner money that went out as expenses; the sheet's own
// summary tranches stay below as reference (they also carry Giftzy's own money).
$shopSpend = scalar($db, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE fund_source = 'Shop'");
$selfSpend = rows($db, "SELECT fund_source, SUM(amount) t FROM expenses
                        WHERE fund_source IN ('Sowji','Lavanya') GROUP BY fund_source");
$selfTotal = array_sum(array_column($selfSpend, 't'));

// Partner spending is settled between the two as it goes and shared half and half,
// so what is still open is half the gap between their not-yet-settled rows.
$open = [];
foreach (rows($db, "SELECT fund_source, SUM(amount) t FROM expenses
                    WHERE fund_source IN ('Sowji','Lavanya') AND settled = 0
                    GROUP BY fund_source") as $r) {
    $open[$r['fund_source']] = (float) $r['t'];
}
$openDiff = (($open['Lavanya'] ?? 0) - ($open['Sowji'] ?? 0)) / 2;

$title = 'Investments';
require __DIR__ . '/partials/header.php';
?>
<div class="page-head">
  <h1 class="title">Investments</h1>
  <a class="btn primary" href="investment_form.php">Add investment</a>
</div>

<div class="stat-row">
  <div class="stat medium">
    <div class="label">Recorded investment by partners</div>
    <div class="value"><?= money($selfTotal) ?></div>
    <div class="sub">Sowji and Lavanya's own money</div>
  </div>
  <?php foreach ($selfSpend as $r): ?>
    <a class="stat medium" href="expenses.php?fund_source=<?= h($r['fund_source']) ?>">
      <div class="label"><?= h($r['fund_source']) ?> spent from own money</div>
      <div class="value"><?= money($r['t']) ?></div>
    </a>
  <?php endforeach; ?>
  <div class="stat medium">
    <div class="label">To be cleared between partners</div>
    <div class="value"><?= money(abs($openDiff)) ?></div>
    <div class="sub">
      <?php if ($openDiff == 0): ?>
        everything spent so far is settled
      <?php else: ?>
        <?= $openDiff > 0 ? 'Sowji owes Lavanya' : 'Lavanya owes Sowji' ?>
        &mdash; half the gap on the entries not ticked as settled
      <?php endif; ?>
    </div>
  </div>
</div>

<p class="lede">
  Recorded investment by partners adds up every expense paid with Sowji's or Lavanya's own money,
  so it grows on its own whenever such an expense is added. The summary entries below are the sheet's
  investment tranches broken down the same way, so they add up to exactly the same amount and are not
  added on top; the <?= money($shopSpend) ?> that Giftzy itself paid inside the Investment 2026 table
  is left out of both.
  Partner spending is squared up between the two as it goes, so an expense ticked as settled is
  already cleared; what is left to clear is the difference between the unsettled rows.
  <a href="expenses.php?fund_source=Sowji">See Sowji's rows</a> &middot;
  <a href="expenses.php?fund_source=Lavanya">Lavanya's rows</a>.
</p>

<h2 class="section">Summary entries as per sheet</h2>
<div class="hint" style="margin-top:8px">
  <?= number_format(count($list)) ?> entries &middot; total <strong><?= money($sum) ?></strong>
  &middot; the same money as the investment above, tranche by tranche
</div>

<form class="filters" method="get">
  <?= select_field('fund_source', FUND_SOURCES, $source, 'Any source') ?>
  <?= select_field('category', $categories, $category, 'Any category') ?>
  <button class="btn">Filter</button>
</form>

<div class="table-card"><div class="table-scroll">
  <table class="data">
    <thead><tr><th>Date</th><th>Purpose</th><th class="num">Amount</th><th>Source</th><th>Category</th><th>Notes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
      <tr>
        <td class="dim nowrap"><?= h($r['inv_date']) ?></td>
        <td class="wide"><?= h($r['purpose']) ?></td>
        <td class="num strong"><?= money($r['amount']) ?></td>
        <td><?= pill($r['fund_source']) ?></td>
        <td><?= h($r['category']) ?></td>
        <td class="dim"><?= h($r['notes']) ?></td>
        <td class="nowrap">
          <a class="icon-btn" href="investment_form.php?id=<?= (int) $r['id'] ?>" title="Edit" aria-label="Edit">
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
<?php require __DIR__ . '/partials/footer.php'; ?>
