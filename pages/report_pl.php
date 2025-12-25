<?php
declare(strict_types=1);
$user = require_auth();
$tid = tenant_id();
$pdo = db();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? now_date();

$st = $pdo->prepare("SELECT COALESCE(SUM(gross_revenue),0) rev FROM daily_sales WHERE tenant_id=? AND sale_date BETWEEN ? AND ?");
$st->execute([$tid,$from,$to]);
$rev = (float)($st->fetch()['rev'] ?? 0);

$st = $pdo->prepare("
  SELECT COALESCE(SUM(ds.qty * c.cogs_per_unit),0) cogs
  FROM daily_sales ds
  LEFT JOIN (
    SELECT r.sku_id,
      SUM(rl.qty * COALESCE(pp.price_per_unit,0) * (1 + rl.waste_pct/100) * (1 + p.yield_loss_pct/100)) AS cogs_per_unit
    FROM recipes r
    JOIN recipe_lines rl ON rl.recipe_id=r.id AND rl.tenant_id=r.tenant_id
    JOIN products p ON p.id=rl.product_id AND p.tenant_id=rl.tenant_id
    LEFT JOIN product_prices pp ON pp.tenant_id=p.tenant_id AND pp.product_id=p.id AND pp.is_active=1
    WHERE r.tenant_id=? AND r.version=1
    GROUP BY r.sku_id
  ) c ON c.sku_id = ds.sku_id
  WHERE ds.tenant_id=? AND ds.sale_date BETWEEN ? AND ?
");
$st->execute([$tid,$tid,$from,$to]);
$cogs = (float)($st->fetch()['cogs'] ?? 0);

$st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) opex FROM expenses WHERE tenant_id=? AND expense_date BETWEEN ? AND ?");
$st->execute([$tid,$from,$to]);
$opex = (float)($st->fetch()['opex'] ?? 0);

$grossProfit = $rev - $cogs;
$net = $grossProfit - $opex;

render('Отчёт: P&L (упрощённый)', function() use ($from, $to, $rev, $cogs, $opex, $grossProfit, $net) {
  $gm = $rev > 0 ? ($grossProfit / $rev * 100) : 0;
?>
  <form method="get" class="flex flex-wrap gap-2 items-end mb-4">
    <input type="hidden" name="r" value="report_pl"/>
    <div>
      <div class="text-xs text-slate-400 mb-1">С</div>
      <input name="from" type="date" value="<?= e($from) ?>" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    </div>
    <div>
      <div class="text-xs text-slate-400 mb-1">По</div>
      <input name="to" type="date" value="<?= e($to) ?>" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    </div>
    <button class="px-4 py-2 rounded-xl bg-white/10 border border-white/10">Показать</button>
  </form>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
      <div class="text-slate-400 text-sm">Выручка</div>
      <div class="text-2xl font-semibold mt-1"><?= e(money($rev)) ?></div>
    </div>
    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
      <div class="text-slate-400 text-sm">COGS (себестоимость)</div>
      <div class="text-2xl font-semibold mt-1"><?= e(money($cogs)) ?></div>
    </div>
    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
      <div class="text-slate-400 text-sm">Валовая прибыль</div>
      <div class="text-2xl font-semibold mt-1"><?= e(money($grossProfit)) ?></div>
      <div class="text-slate-400 text-sm mt-1">Валовая маржа: <?= e(number_format($gm,2,'.',' ')) ?>%</div>
    </div>
    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
      <div class="text-slate-400 text-sm">OPEX (расходы)</div>
      <div class="text-2xl font-semibold mt-1"><?= e(money($opex)) ?></div>
    </div>
    <div class="p-4 rounded-2xl bg-white/5 border border-white/10 md:col-span-2">
      <div class="text-slate-400 text-sm">Прибыль (упрощённо)</div>
      <div class="text-3xl font-semibold mt-1 <?= $net < 0 ? 'text-rose-300' : 'text-emerald-200' ?>"><?= e(money($net)) ?></div>
      <div class="text-slate-400 text-xs mt-2">Примечание: налоги/амортизация/проценты пока не учитываются (MVP).</div>
    </div>
  </div>
<?php
}, $user);
