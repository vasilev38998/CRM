<?php
declare(strict_types=1);
$user = require_auth();
$tid = tenant_id();
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? now_date();
$export = ($_GET['export'] ?? '') === 'csv';

$sql = "
SELECT
  s.id sku_id,
  s.name sku_name,
  COALESCE(SUM(ds.qty),0) sold_qty,
  COALESCE(SUM(ds.gross_revenue),0) revenue,
  COALESCE(SUM(ds.qty * c.cogs_per_unit),0) cogs_total,
  COALESCE(c.cogs_per_unit,0) cogs_per_unit
FROM skus s
LEFT JOIN daily_sales ds
  ON ds.tenant_id=s.tenant_id AND ds.sku_id=s.id AND ds.sale_date BETWEEN :from AND :to
LEFT JOIN (
  SELECT
    r.sku_id,
    SUM(
      rl.qty
      * COALESCE(pp.price_per_unit,0)
      * (1 + rl.waste_pct/100)
      * (1 + p.yield_loss_pct/100)
    ) AS cogs_per_unit
  FROM recipes r
  JOIN recipe_lines rl ON rl.recipe_id=r.id AND rl.tenant_id=r.tenant_id
  JOIN products p ON p.id=rl.product_id AND p.tenant_id=rl.tenant_id
  LEFT JOIN product_prices pp
    ON pp.tenant_id=p.tenant_id AND pp.product_id=p.id AND pp.is_active=1
  WHERE r.tenant_id=:tid AND r.version=1
  GROUP BY r.sku_id
) c ON c.sku_id = s.id
WHERE s.tenant_id=:tid
GROUP BY s.id, s.name, c.cogs_per_unit
ORDER BY (revenue - cogs_total) DESC
";

$st = $pdo->prepare($sql);
$st->execute([':tid'=>$tid, ':from'=>$from, ':to'=>$to]);
$rows = $st->fetchAll();

if ($export) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="sku_margin_'.$from.'_to_'.$to.'.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['SKU','SoldQty','Revenue','COGS_Total','GrossProfit','MarginPct','COGS_per_unit']);
  foreach ($rows as $r) {
    $rev = (float)$r['revenue'];
    $cogs = (float)$r['cogs_total'];
    $gp = $rev - $cogs;
    $m = $rev > 0 ? ($gp / $rev * 100) : 0;
    fputcsv($out, [$r['sku_name'], (float)$r['sold_qty'], $rev, $cogs, $gp, $m, (float)$r['cogs_per_unit']]);
  }
  fclose($out);
  exit;
}

render('Отчёт: маржа SKU', function() use ($rows, $from, $to) {
  $q = http_build_query(['r'=>'report_sku_margin','from'=>$from,'to'=>$to,'export'=>'csv']);
?>
  <form method="get" class="flex flex-wrap gap-2 items-end mb-4">
    <input type="hidden" name="r" value="report_sku_margin"/>
    <div>
      <div class="text-xs text-slate-400 mb-1">С</div>
      <input name="from" type="date" value="<?= e($from) ?>" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    </div>
    <div>
      <div class="text-xs text-slate-400 mb-1">По</div>
      <input name="to" type="date" value="<?= e($to) ?>" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    </div>
    <button class="px-4 py-2 rounded-xl bg-white/10 border border-white/10">Показать</button>
    <a class="px-4 py-2 rounded-xl bg-white/10 border border-white/10" href="/index.php?<?= e($q) ?>">Экспорт CSV</a>
  </form>

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-300">
        <tr class="border-b border-white/10">
          <th class="text-left py-2">SKU</th>
          <th class="text-right py-2">Продано</th>
          <th class="text-right py-2">Выручка</th>
          <th class="text-right py-2">COGS</th>
          <th class="text-right py-2">Валовая прибыль</th>
          <th class="text-right py-2">Маржа %</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $rev = (float)$r['revenue'];
          $cogs = (float)$r['cogs_total'];
          $gp = $rev - $cogs;
          $m = $rev > 0 ? ($gp / $rev * 100) : 0;
        ?>
          <tr class="border-b border-white/5">
            <td class="py-2"><?= e($r['sku_name']) ?></td>
            <td class="py-2 text-right text-slate-400"><?= e(number_format((float)$r['sold_qty'], 3, '.', ' ')) ?></td>
            <td class="py-2 text-right"><?= e(money($rev)) ?></td>
            <td class="py-2 text-right"><?= e(money($cogs)) ?></td>
            <td class="py-2 text-right"><?= e(money($gp)) ?></td>
            <td class="py-2 text-right <?= $m < 0 ? 'text-rose-300' : 'text-emerald-200' ?>"><?= e(number_format($m, 2, '.', ' ')) ?>%</td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td class="py-3 text-slate-400" colspan="6">Нет данных.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="mt-4 text-slate-400 text-sm">
    Если у ингредиента нет цены, он считается как 0. Добавь цену в “Ингредиенты”.
  </div>
<?php
}, $user);
