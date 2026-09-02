<?php
require __DIR__ . '/lib/init.php';
csrf_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $db->prepare('INSERT INTO monthly_collections (period, online_amount, cash_amount, notes)
                        VALUES (?,?,?,?)
                        ON DUPLICATE KEY UPDATE online_amount = VALUES(online_amount),
                                                cash_amount = VALUES(cash_amount),
                                                notes = VALUES(notes)');
    if (($_POST['action'] ?? '') === 'add') {
        $period = preg_match('/^\d{4}-\d{2}$/', $_POST['new_period'] ?? '') ? $_POST['new_period'] : null;
        if ($period) {
            $st->execute([$period, post_num('new_online'), post_num('new_cash'), post_str('new_notes')]);
        }
        redirect('monthly.php', $period ? "Collections saved for $period." : 'Pick a month first.');
    }
    foreach ($_POST['online'] ?? [] as $period => $online) {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            continue;
        }
        $st->execute([
            $period,
            (float) str_replace(',', '', $online),
            (float) str_replace(',', '', $_POST['cash'][$period] ?? 0),
            trim((string) ($_POST['notes'][$period] ?? '')) ?: null,
        ]);
    }
    redirect('monthly.php', 'Monthly collections saved.');
}

$sales = [];
foreach (rows($db, "SELECT DATE_FORMAT(sale_date,'%Y-%m') p, SUM(total_amount) sales, SUM(profit) profit,
                           SUM(CASE WHEN payment_type = 'Cash' THEN 0 ELSE total_amount - pending_amount END) into_account,
                           SUM(CASE WHEN payment_type = 'Cash' THEN total_amount - pending_amount ELSE 0 END) into_cash
                    FROM sales WHERE sale_date IS NOT NULL GROUP BY p") as $r) {
    $sales[$r['p']] = $r;
}
$expenses = [];
foreach (rows($db, "SELECT DATE_FORMAT(expense_date,'%Y-%m') p,
                           SUM(amount) total,
                           SUM(CASE WHEN fund_source IN ('Sowji','Lavanya') THEN amount ELSE 0 END) self_paid,
                           SUM(CASE WHEN fund_source = 'Account' THEN amount ELSE 0 END) from_account,
                           SUM(CASE WHEN fund_source = 'Cash' THEN amount ELSE 0 END) from_cash
                    FROM expenses WHERE expense_date IS NOT NULL GROUP BY p") as $r) {
    $expenses[$r['p']] = $r;
}
$collections = [];
foreach (rows($db, 'SELECT * FROM monthly_collections') as $r) {
    $collections[$r['period']] = $r;
}

$periods = month_series(array_merge(array_keys($sales), array_keys($expenses),
                                   array_keys($collections)));
rsort($periods);
$thisMonth = date('Y-m');

// Workbook rows that carry no date still have to show up in the totals.
$undated = one($db, "SELECT COALESCE(SUM(amount),0) total,
                            COALESCE(SUM(CASE WHEN fund_source IN ('Sowji','Lavanya') THEN amount ELSE 0 END),0) self_paid,
                            COALESCE(SUM(CASE WHEN fund_source = 'Account' THEN amount ELSE 0 END),0) from_account,
                            COALESCE(SUM(CASE WHEN fund_source = 'Cash' THEN amount ELSE 0 END),0) from_cash
                     FROM expenses WHERE expense_date IS NULL");
$undatedSales = one($db, 'SELECT COALESCE(SUM(total_amount),0) sales, COALESCE(SUM(profit),0) profit
                          FROM sales WHERE sale_date IS NULL');

$title = 'Monthly';
require __DIR__ . '/partials/header.php';
?>
<h1 class="title">Monthly sales, collections and expenses</h1>
<p class="lede">
  Sales and profit are added up from the sales entries, and every sale is money in on its
  own: into the cash box when it was paid in cash, into the sale account otherwise. The two
  editable columns are adjustments &mdash; anything else that moved money that month
  (top-ups, transfers between account and cash box, differences against the passbook).
  Every month up to the current one has its own row automatically, so there is nothing to
  create when a new month starts.
</p>

<form method="post">
  <?= csrf_field() ?>
  <div class="table-card"><div class="table-scroll"><table class="data">
    <thead><tr>
      <th>Month</th><th class="num">Sales (entries)</th><th class="num">Profit</th>
      <th class="num">Into account</th><th class="num">Into cash</th>
      <th class="num">Account adjust</th><th class="num">Cash adjust</th>
      <th class="num">Money in</th><th class="num">Expenses</th>
      <th class="num">Own money</th><th class="num">From account</th><th class="num">From cash</th>
      <th>Notes</th>
    </tr></thead>
    <tbody>
    <?php
    $t = ['sales' => 0, 'profit' => 0, 'in_acct' => 0, 'in_cash' => 0, 'online' => 0,
          'cash' => 0, 'exp' => 0, 'self' => 0, 'acct' => 0, 'cashexp' => 0];
    foreach ($periods as $p):
        $s = $sales[$p] ?? ['sales' => 0, 'profit' => 0, 'into_account' => 0, 'into_cash' => 0];
        $e = $expenses[$p] ?? ['total' => 0, 'self_paid' => 0, 'from_account' => 0, 'from_cash' => 0];
        $c = $collections[$p] ?? ['online_amount' => 0, 'cash_amount' => 0, 'notes' => ''];
        $t['sales'] += $s['sales']; $t['profit'] += $s['profit'];
        $t['in_acct'] += $s['into_account']; $t['in_cash'] += $s['into_cash'];
        $t['online'] += $c['online_amount']; $t['cash'] += $c['cash_amount'];
        $t['exp'] += $e['total']; $t['self'] += $e['self_paid'];
        $t['acct'] += $e['from_account']; $t['cashexp'] += $e['from_cash'];
    ?>
      <tr<?= $p === $thisMonth ? ' class="current"' : '' ?>>
        <td class="nowrap strong">
          <?= h(month_label($p)) ?>
          <?php if ($p === $thisMonth): ?><span class="badge-now">this month</span><?php endif; ?>
        </td>
        <td class="num"><?= money($s['sales']) ?></td>
        <td class="num"><?= money($s['profit']) ?></td>
        <td class="num"><?= money($s['into_account']) ?></td>
        <td class="num"><?= money($s['into_cash']) ?></td>
        <td class="num"><input class="cell-input" name="online[<?= h($p) ?>]" value="<?= h((float) $c['online_amount']) ?>"></td>
        <td class="num"><input class="cell-input" name="cash[<?= h($p) ?>]" value="<?= h((float) $c['cash_amount']) ?>"></td>
        <td class="num strong"><?= money($s['into_account'] + $s['into_cash']
                                  + $c['online_amount'] + $c['cash_amount']) ?></td>
        <td class="num neg"><?= money($e['total']) ?></td>
        <td class="num"><?= money($e['self_paid']) ?></td>
        <td class="num"><?= money($e['from_account']) ?></td>
        <td class="num"><?= money($e['from_cash']) ?></td>
        <td><input class="cell-input text" name="notes[<?= h($p) ?>]" value="<?= h($c['notes']) ?>"></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($undated['total'] > 0 || $undatedSales['sales'] > 0):
        $t['sales'] += $undatedSales['sales']; $t['profit'] += $undatedSales['profit'];
        $t['exp'] += $undated['total']; $t['self'] += $undated['self_paid'];
        $t['acct'] += $undated['from_account']; $t['cashexp'] += $undated['from_cash'];
    ?>
      <tr class="aside">
        <td class="nowrap strong">No date in sheet</td>
        <td class="num"><?= money($undatedSales['sales']) ?></td>
        <td class="num"><?= money($undatedSales['profit']) ?></td>
        <td class="num dim">&mdash;</td>
        <td class="num dim">&mdash;</td>
        <td class="num dim">&mdash;</td>
        <td class="num dim">&mdash;</td>
        <td class="num dim">&mdash;</td>
        <td class="num neg"><?= money($undated['total']) ?></td>
        <td class="num"><?= money($undated['self_paid']) ?></td>
        <td class="num"><?= money($undated['from_account']) ?></td>
        <td class="num"><?= money($undated['from_cash']) ?></td>
        <td class="dim">Add a date to move these into a month</td>
      </tr>
    <?php endif; ?>
    </tbody>
    <tfoot><tr>
      <td>Total</td>
      <td class="num"><?= money($t['sales']) ?></td>
      <td class="num"><?= money($t['profit']) ?></td>
      <td class="num"><?= money($t['in_acct']) ?></td>
      <td class="num"><?= money($t['in_cash']) ?></td>
      <td class="num"><?= money($t['online']) ?></td>
      <td class="num"><?= money($t['cash']) ?></td>
      <td class="num"><?= money($t['in_acct'] + $t['in_cash'] + $t['online'] + $t['cash']) ?></td>
      <td class="num"><?= money($t['exp']) ?></td>
      <td class="num"><?= money($t['self']) ?></td>
      <td class="num"><?= money($t['acct']) ?></td>
      <td class="num"><?= money($t['cashexp']) ?></td>
      <td></td>
    </tr></tfoot>
  </table></div></div>
  <button class="btn primary">Save collections</button>
</form>

<form method="post" class="card form-card" style="margin-top:24px">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add">
  <div class="fields">
    <div class="field">
      <label for="new_period">Add a month</label>
      <input class="input" type="month" name="new_period" id="new_period" value="<?= h(date('Y-m')) ?>">
    </div>
    <div class="field narrow">
      <label for="new_online">Account adjust</label>
      <input class="input" name="new_online" id="new_online" value="0">
    </div>
    <div class="field narrow">
      <label for="new_cash">Cash adjust</label>
      <input class="input" name="new_cash" id="new_cash" value="0">
    </div>
    <div class="field">
      <label for="new_notes">Notes</label>
      <input class="input" name="new_notes" id="new_notes">
    </div>
  </div>
  <div class="actions"><button class="btn">Add</button></div>
</form>
<?php require __DIR__ . '/partials/footer.php'; ?>
