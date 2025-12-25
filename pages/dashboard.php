<?php
declare(strict_types=1);
$user = require_auth();
$tid = tenant_id();
$pdo = db();

$today = now_date();

$st = $pdo->prepare("SELECT COALESCE(SUM(gross_revenue),0) rev, COALESCE(SUM(qty),0) qty
                     FROM daily_sales WHERE tenant_id=? AND sale_date=?");
$st->execute([$tid, $today]);
$todaySales = $st->fetch();

$st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) amt FROM expenses WHERE tenant_id=? AND expense_date=?");
$st->execute([$tid, $today]);
$todayExp = $st->fetch();

render('Дашборд', function() use ($todaySales, $todayExp) {
?>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
      <div class="text-slate-400 text-sm">Выручка сегодня</div>
      <div class="text-2xl font-semibold mt-1"><?= e(money((float)$todaySales['rev'])) ?></div>
    </div>
    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
      <div class="text-slate-400 text-sm">Продано порций сегодня</div>
      <div class="text-2xl font-semibold mt-1"><?= e((string)$todaySales['qty']) ?></div>
    </div>
    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
      <div class="text-slate-400 text-sm">Расходы сегодня</div>
      <div class="text-2xl font-semibold mt-1"><?= e(money((float)$todayExp['amt'])) ?></div>
    </div>
  </div>

  <div class="mt-5 text-slate-300">
    <div class="font-semibold">Быстрый старт</div>
    <ol class="list-decimal ml-5 mt-2 text-slate-400">
      <li>Добавь ингредиенты и цены (цена за 1g/1ml/1pcs)</li>
      <li>Создай SKU меню</li>
      <li>Заполни техкарты</li>
      <li>Внеси продажи — и смотри маржу в отчёте</li>
    </ol>
  </div>
<?php
}, $user);
