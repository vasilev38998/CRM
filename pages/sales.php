<?php
declare(strict_types=1);
$user = require_auth();
$tid = tenant_id();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'upsert') {
    $date = (string)($_POST['sale_date'] ?? now_date());
    $skuId = (int)($_POST['sku_id'] ?? 0);
    $qty = (float)($_POST['qty'] ?? 0);
    $rev = (float)($_POST['gross_revenue'] ?? 0);
    $channel = (string)($_POST['channel'] ?? 'in_store');

    $st = $pdo->prepare("
      INSERT INTO daily_sales(tenant_id,sale_date,sku_id,qty,gross_revenue,channel)
      VALUES(?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE qty=VALUES(qty), gross_revenue=VALUES(gross_revenue)
    ");
    $st->execute([$tid,$date,$skuId,$qty,$rev,$channel]);
    flash_set('success','Продажи сохранены');
    redirect('/index.php?r=sales');
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare("DELETE FROM daily_sales WHERE tenant_id=? AND id=?");
    $st->execute([$tid,$id]);
    flash_set('success','Удалено');
    redirect('/index.php?r=sales');
  }
}

$skus = $pdo->prepare("SELECT id,name FROM skus WHERE tenant_id=? ORDER BY name");
$skus->execute([$tid]);
$skuRows = $skus->fetchAll();

$st = $pdo->prepare("
  SELECT ds.*, s.name sku_name
  FROM daily_sales ds
  JOIN skus s ON s.id=ds.sku_id AND s.tenant_id=ds.tenant_id
  WHERE ds.tenant_id=?
  ORDER BY ds.sale_date DESC, ds.id DESC
  LIMIT 200
");
$st->execute([$tid]);
$rows = $st->fetchAll();

render('Продажи (день)', function() use ($skuRows, $rows) {
?>
  <form method="post" class="grid grid-cols-1 md:grid-cols-6 gap-2 mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upsert"/>
    <input name="sale_date" type="date" value="<?= e(now_date()) ?>" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <select name="sku_id" required class="px-3 py-2 rounded-xl bg-black/20 border border-white/10 md:col-span-2">
      <option value="">SKU…</option>
      <?php foreach ($skuRows as $s): ?>
        <option value="<?= e((string)$s['id']) ?>"><?= e($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input name="qty" type="number" step="0.001" required placeholder="Кол-во" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <input name="gross_revenue" type="number" step="0.01" required placeholder="Выручка" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <select name="channel" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10">
      <option value="in_store">in_store</option>
      <option value="takeaway">takeaway</option>
      <option value="delivery">delivery</option>
    </select>
    <button class="md:col-span-6 px-4 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold">Сохранить</button>
  </form>

  <div class="flex flex-wrap gap-2 mb-3">
    <a class="px-3 py-2 rounded-xl bg-white/10 border border-white/10 text-sm" href="/index.php?r=import_sales">Импорт CSV</a>
    <a class="px-3 py-2 rounded-xl bg-white/10 border border-white/10 text-sm" href="/index.php?r=report_sku_margin">Маржа SKU</a>
  </div>

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-300">
        <tr class="border-b border-white/10">
          <th class="text-left py-2">Дата</th>
          <th class="text-left py-2">SKU</th>
          <th class="text-left py-2">Канал</th>
          <th class="text-right py-2">Qty</th>
          <th class="text-right py-2">Выручка</th>
          <th class="text-right py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="border-b border-white/5">
            <td class="py-2 text-slate-400"><?= e($r['sale_date']) ?></td>
            <td class="py-2"><?= e($r['sku_name']) ?></td>
            <td class="py-2 text-slate-400"><?= e($r['channel']) ?></td>
            <td class="py-2 text-right"><?= e((string)$r['qty']) ?></td>
            <td class="py-2 text-right"><?= e(money((float)$r['gross_revenue'])) ?></td>
            <td class="py-2 text-right">
              <form method="post" onsubmit="return confirm('Удалить?');" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>"/>
                <button class="px-3 py-1 rounded-lg bg-rose-500/15 border border-rose-400/30 text-rose-200">Удалить</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td class="py-3 text-slate-400" colspan="6">Пока нет продаж.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php
}, $user);
