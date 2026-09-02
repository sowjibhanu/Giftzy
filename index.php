<?php
require __DIR__ . '/lib/init.php';

$salesTotal = scalar($db, 'SELECT COALESCE(SUM(total_amount),0) FROM sales');
$profitTotal = scalar($db, 'SELECT COALESCE(SUM(profit),0) FROM sales');
$pendingTotal = scalar($db, 'SELECT COALESCE(SUM(pending_amount),0) FROM sales');

$bySource = [];
foreach (rows($db, 'SELECT fund_source, SUM(amount) t FROM expenses GROUP BY fund_source') as $r) {
    $bySource[$r['fund_source']] = (float) $r['t'];
}
$selfSpend = ($bySource['Sowji'] ?? 0) + ($bySource['Lavanya'] ?? 0);
$expenseTotal = array_sum($bySource);

$adjust = one($db, 'SELECT COALESCE(SUM(online_amount),0) o, COALESCE(SUM(cash_amount),0) c
                    FROM monthly_collections');
// Sale entries are money in: cash-paid ones into the cash box, the rest into the
// sale account, pending amounts excluded.
$received = one($db, "SELECT COALESCE(SUM(CASE WHEN payment_type = 'Cash' THEN 0 ELSE total_amount - pending_amount END),0) o,
                             COALESCE(SUM(CASE WHEN payment_type = 'Cash' THEN total_amount - pending_amount ELSE 0 END),0) c
                      FROM sales WHERE sale_date IS NOT NULL");

$opening = settings($db);
$accountBalance = $opening['opening_account'] + $received['o'] + $adjust['o']
                  - ($bySource['Account'] ?? 0);
$cashBalance = $opening['opening_cash'] + $received['c'] + $adjust['c']
               - ($bySource['Cash'] ?? 0);

$monthlySales = rows($db, "SELECT DATE_FORMAT(sale_date,'%Y-%m') p, SUM(total_amount) sales,
                                  SUM(profit) profit
                           FROM sales WHERE sale_date IS NOT NULL GROUP BY p ORDER BY p");
$monthlyExpenses = [];
foreach (rows($db, "SELECT DATE_FORMAT(expense_date,'%Y-%m') p, SUM(amount) t
                    FROM expenses WHERE expense_date IS NOT NULL GROUP BY p") as $r) {
    $monthlyExpenses[$r['p']] = (float) $r['t'];
}

$topItems = rows($db, 'SELECT item, SUM(qty) qty, SUM(total_amount) sales, SUM(profit) profit
                       FROM sales GROUP BY item ORDER BY sales DESC LIMIT 10');
$topCategories = rows($db, "SELECT COALESCE(NULLIF(category,''),'(uncategorised)') category,
                                   SUM(amount) t
                            FROM expenses GROUP BY category ORDER BY t DESC LIMIT 10");

$chartPeriods = array_column($monthlySales, 'p');
$chart = [
    'labels' => array_map('month_label', $chartPeriods),
    'periods' => $chartPeriods,
    'sales' => array_map(fn($r) => (float) $r['sales'], $monthlySales),
    'profit' => array_map(fn($r) => (float) $r['profit'], $monthlySales),
    'expenses' => array_map(fn($p) => $monthlyExpenses[$p] ?? 0, $chartPeriods),
];

$title = 'Dashboard';
require __DIR__ . '/partials/header.php';

$heroKpis = [
    ['Total sales', money($salesTotal), 'sales.php', 'primary'],
    ['Gross profit', money($profitTotal), 'sales.php', 'positive'],
    ['Total expenses', money($expenseTotal), 'expenses.php', 'negative'],
    ['Pending from customers', money($pendingTotal), 'sales.php?payment_type=Pending', 'warning'],
];
$balanceKpis = [
    ['Spent from sale account', money($bySource['Account'] ?? 0), 'expenses.php?fund_source=Account'],
    ['Spent from cash box', money($bySource['Cash'] ?? 0), 'expenses.php?fund_source=Cash'],
    ['Account balance', money($accountBalance), 'balances.php'],
    ['Cash box balance', money($cashBalance), 'balances.php'],
];
$collectionKpis = [
    ['Recorded investment by partners', money($selfSpend), 'investments.php'],
    ['Collections online', money($received['o'] + $adjust['o']), 'monthly.php'],
    ['Collections cash', money($received['c'] + $adjust['c']), 'monthly.php'],
];
?>
<h1 class="title">Dashboard</h1>

<div class="stat-row hero">
  <?php foreach ($heroKpis as [$label, $value, $link, $tone]): ?>
    <a class="stat hero <?= h($tone) ?>" href="<?= h($link) ?>">
      <div class="label"><?= h($label) ?></div>
      <div class="value"><?= h($value) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="eyebrow">Balances</div>
<div class="stat-row">
  <?php foreach ($balanceKpis as [$label, $value, $link]): ?>
    <a class="stat" href="<?= h($link) ?>">
      <div class="label"><?= h($label) ?></div>
      <div class="value"><?= h($value) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="eyebrow">Collections &amp; investment</div>
<div class="stat-row">
  <?php foreach ($collectionKpis as [$label, $value, $link]): ?>
    <a class="stat" href="<?= h($link) ?>">
      <div class="label"><?= h($label) ?></div>
      <div class="value"><?= h($value) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="card chart-card">
  <div class="chart-head">
    <h2 class="section">Month by month</h2>
    <span class="hint" style="margin:0">Click a bar or a month to open that month's entries</span>
  </div>
  <div class="chart-box"><canvas id="chart" height="110"></canvas></div>
</div>

<div class="split">
  <div class="card">
    <h3 class="section">Top selling items</h3>
    <table class="flat">
      <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Sales</th><th class="num">Profit</th></tr></thead>
      <tbody>
      <?php foreach ($topItems as $r): ?>
        <tr><td><?= h($r['item']) ?></td>
          <td class="num"><?= h((float) $r['qty']) ?></td>
          <td class="num"><?= money($r['sales']) ?></td>
          <td class="num pos"><?= money($r['profit']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h3 class="section">Biggest expense categories</h3>
    <table class="flat">
      <thead><tr><th>Category</th><th class="num">Amount</th></tr></thead>
      <tbody>
      <?php foreach ($topCategories as $r): ?>
        <tr><td><?= h($r['category']) ?></td><td class="num neg"><?= money($r['t']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="assets/chart.umd.min.js"></script>
<script>
(function () {
  var periods = <?= json_encode($chart['periods']) ?>;
  var currency = <?= json_encode($CONFIG['currency']) ?>;
  var fmt = function (v) {
    return currency + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 });
  };
  var canvas = document.getElementById('chart');

  var chart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels: <?= json_encode($chart['labels']) ?>,
      datasets: [
        { label: 'Sales', data: <?= json_encode($chart['sales']) ?>, backgroundColor: '#0086EA', borderRadius: 4 },
        { label: 'Profit', data: <?= json_encode($chart['profit']) ?>, backgroundColor: '#00744D', borderRadius: 4 },
        { label: 'Expenses', data: <?= json_encode($chart['expenses']) ?>, backgroundColor: '#D91214', borderRadius: 4 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 900, easing: 'easeOutQuart' },
      interaction: { mode: 'index', intersect: false },
      onHover: function (event, elements) {
        event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
      },
      onClick: function (event, elements) {
        var hit = elements[0] || chart.getElementsAtEventForMode(event, 'index', { intersect: false }, true)[0];
        if (!hit) { return; }
        var period = periods[hit.index];
        var page = hit.datasetIndex === 2 ? 'expenses.php' : 'sales.php';
        window.location = page + '?period=' + encodeURIComponent(period);
      },
      plugins: {
        legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } },
        tooltip: {
          callbacks: {
            label: function (ctx) { return ctx.dataset.label + ': ' + fmt(ctx.parsed.y); }
          }
        }
      },
      scales: {
        x: { grid: { display: false } },
        y: {
          beginAtZero: true,
          border: { display: false },
          grid: { color: '#F0F0FA' },
          ticks: { callback: function (v) { return fmt(v); } }
        }
      }
    }
  });
})();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
