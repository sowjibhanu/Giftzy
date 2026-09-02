<?php
require __DIR__ . '/lib/init.php';
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    save_setting($db, 'opening_account', post_num('opening_account'));
    save_setting($db, 'opening_cash', post_num('opening_cash'));
    redirect('balances.php', 'Opening balances saved.');
}

$opening = settings($db);

$collections = [];
foreach (rows($db, 'SELECT period, online_amount, cash_amount FROM monthly_collections') as $r) {
    $collections[$r['period']] = $r;
}
// A sale entry is money in: to the cash box when it was paid in cash, otherwise
// to the sale account. Pending amounts are not in yet.
$received = [];
foreach (rows($db, "SELECT DATE_FORMAT(sale_date,'%Y-%m') p,
                           SUM(CASE WHEN payment_type = 'Cash' THEN 0 ELSE total_amount - pending_amount END) acct,
                           SUM(CASE WHEN payment_type = 'Cash' THEN total_amount - pending_amount ELSE 0 END) cash
                    FROM sales WHERE sale_date IS NOT NULL GROUP BY p") as $r) {
    $received[$r['p']] = $r;
}
$spend = [];
foreach (rows($db, "SELECT DATE_FORMAT(expense_date,'%Y-%m') p,
                           SUM(CASE WHEN fund_source = 'Account' THEN amount ELSE 0 END) acct,
                           SUM(CASE WHEN fund_source = 'Cash' THEN amount ELSE 0 END) cash
                    FROM expenses WHERE expense_date IS NOT NULL GROUP BY p") as $r) {
    $spend[$r['p']] = $r;
}
// Undated expenses still leave the account, so they sit in the opening row.
$undated = one($db, "SELECT COALESCE(SUM(CASE WHEN fund_source = 'Account' THEN amount ELSE 0 END),0) acct,
                            COALESCE(SUM(CASE WHEN fund_source = 'Cash' THEN amount ELSE 0 END),0) cash
                     FROM expenses WHERE expense_date IS NULL");

$periods = month_series(array_merge(array_keys($collections), array_keys($spend),
                                   array_keys($received)));
sort($periods);
$thisMonth = date('Y-m');

$acct = $opening['opening_account'] - $undated['acct'];
$cash = $opening['opening_cash'] - $undated['cash'];
$openingAcct = $acct;
$openingCash = $cash;

$months = [];
foreach ($periods as $p) {
    $sale = (float) ($received[$p]['acct'] ?? 0);
    $saleCash = (float) ($received[$p]['cash'] ?? 0);
    $adj = (float) ($collections[$p]['online_amount'] ?? 0);
    $adjCash = (float) ($collections[$p]['cash_amount'] ?? 0);
    $out = (float) ($spend[$p]['acct'] ?? 0);
    $cashOut = (float) ($spend[$p]['cash'] ?? 0);
    $acct += $sale + $adj - $out;
    $cash += $saleCash + $adjCash - $cashOut;
    $months[] = compact('p', 'sale', 'saleCash', 'adj', 'adjCash', 'out', 'cashOut')
                + ['acct' => $acct, 'cash' => $cash];
}
$months = array_reverse($months);

$pending = scalar($db, 'SELECT COALESCE(SUM(pending_amount),0) FROM sales');

$title = 'Balances';
require __DIR__ . '/partials/header.php';
?>
<h1 class="title">Balances</h1>
<p class="lede">
  Money in is every sale entry &mdash; into the cash box when it was paid in cash, into the
  sale account otherwise, with pending amounts left out until collected. Money out is every
  expense paid from the account or the cash box; partner (Sowji / Lavanya) spending is their
  own money and does not touch either balance. Adjustments are anything else that moved money
  (top-ups, transfers between account and cash box) &mdash; edit them on the Monthly page.
  Sale entries without a date in the sheet are left out of the balances; give them a date
  to bring them in.
  A new month gets its own row on its own, carrying the previous closing balance forward.
</p>

<div class="stat-row">
  <div class="stat big">
      <div class="label">Sale account balance</div>
      <div class="value"><?= money($acct) ?></div>
      <div class="note">
        opening <?= money($opening['opening_account']) ?>
        + sales <?= money(array_sum(array_column($received, 'acct'))) ?>
        + adjustments <?= money(array_sum(array_column($collections, 'online_amount'))) ?>
        &minus; spent <?= money(scalar($db, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE fund_source='Account'")) ?>
      </div>
  </div>
  <div class="stat big">
      <div class="label">Cash box balance</div>
      <div class="value"><?= money($cash) ?></div>
      <div class="note">
        opening <?= money($opening['opening_cash']) ?>
        + cash sales <?= money(array_sum(array_column($received, 'cash'))) ?>
        + adjustments <?= money(array_sum(array_column($collections, 'cash_amount'))) ?>
        &minus; spent <?= money(scalar($db, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE fund_source='Cash'")) ?>
      </div>
  </div>
  <div class="stat big warning">
      <div class="label">Still to collect from customers</div>
      <div class="value"><?= money($pending) ?></div>
      <div class="note">pending amounts on sale entries</div>
  </div>
</div>

<form method="post" class="card form-card">
  <?= csrf_field() ?>
  <h2 class="section">Opening balances</h2>
  <p class="lede">
    What was in the account and the cash box before these records start. Adjust these
    until the balances above match your passbook and your cash box.
  </p>
  <div class="fields">
    <div class="field">
      <label for="opening_account">Account opening</label>
      <input class="input" name="opening_account" id="opening_account"
             value="<?= h($opening['opening_account']) ?>">
    </div>
    <div class="field">
      <label for="opening_cash">Cash opening</label>
      <input class="input" name="opening_cash" id="opening_cash"
             value="<?= h($opening['opening_cash']) ?>">
    </div>
  </div>
  <div class="actions"><button class="btn primary">Save</button></div>
</form>

<div class="table-card"><div class="table-scroll"><table class="data">
  <thead><tr>
    <th>Month</th>
    <th class="num">Sales in</th><th class="num">Adjustments</th>
    <th class="num">Account out</th><th class="num">Account balance</th>
    <th class="num">Cash sales</th><th class="num">Cash adjustments</th>
    <th class="num">Cash out</th><th class="num">Cash balance</th>
  </tr></thead>
  <tbody>
  <?php foreach ($months as $m): ?>
    <tr<?= $m['p'] === $thisMonth ? ' class="current"' : '' ?>>
      <td class="nowrap strong">
        <?= h(month_label($m['p'])) ?>
        <?php if ($m['p'] === $thisMonth): ?><span class="badge-now">this month</span><?php endif; ?>
      </td>
      <td class="num"><?= money($m['sale']) ?></td>
      <td class="num"><?= money($m['adj']) ?></td>
      <td class="num"><?= money($m['out']) ?></td>
      <td class="num strong"><?= money($m['acct']) ?></td>
      <td class="num"><?= money($m['saleCash']) ?></td>
      <td class="num"><?= money($m['adjCash']) ?></td>
      <td class="num"><?= money($m['cashOut']) ?></td>
      <td class="num strong"><?= money($m['cash']) ?></td>
    </tr>
  <?php endforeach; ?>
    <tr class="aside">
      <td class="nowrap strong">Opening / no date</td>
      <td class="num dim">&mdash;</td>
      <td class="num"><?= money($opening['opening_account']) ?></td>
      <td class="num"><?= money($undated['acct']) ?></td>
      <td class="num strong"><?= money($openingAcct) ?></td>
      <td class="num dim">&mdash;</td>
      <td class="num"><?= money($opening['opening_cash']) ?></td>
      <td class="num"><?= money($undated['cash']) ?></td>
      <td class="num strong"><?= money($openingCash) ?></td>
    </tr>
  </tbody>
</table></div></div>
<?php require __DIR__ . '/partials/footer.php'; ?>
