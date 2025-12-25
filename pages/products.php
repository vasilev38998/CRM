<?php
declare(strict_types=1);
$user = require_auth();
$tid = tenant_id();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $name = trim((string)($_POST['name'] ?? ''));
    $type = (string)($_POST['type'] ?? 'ingredient');
    $uom = (string)($_POST['uom_code'] ?? 'g');
    $loss = (float)($_POST['yield_loss_pct'] ?? 0);

    $st = $pdo->prepare("INSERT INTO products(tenant_id,type,name,uom_code,yield_loss_pct) VALUES(?,?,?,?,?)");
    $st->execute([$tid,$type,$name,$uom,$loss]);

    $ppu = trim((string)($_POST['price_per_unit'] ?? ''));
    if ($ppu !== '') {
      $productId = (int)$pdo->lastInsertId();
      $st = $pdo->prepare("INSERT INTO product_prices(tenant_id,product_id,price_per_unit,currency,effective_from,is_active)
                           VALUES(?,?,?,?,?,1)");
      $st->execute([$tid,$productId,(float)$ppu,'RUB', now_date()]);
    }

    flash_set('success', 'Ингредиент добавлен');
    redirect('/index.php?r=products');
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare("DELETE FROM products WHERE tenant_id=? AND id=?");
    $st->execute([$tid,$id]);
    flash_set('success', 'Удалено');
    redirect('/index.php?r=products');
  }
}

$items = $pdo->prepare("
  SELECT p.*,
    (SELECT price_per_unit FROM product_prices pp
      WHERE pp.tenant_id=p.tenant_id AND pp.product_id=p.id AND pp.is_active=1
      ORDER BY pp.effective_from DESC, pp.id DESC LIMIT 1) AS price_per_unit
  FROM products p
  WHERE p.tenant_id=?
  ORDER BY p.id DESC
");
$items->execute([$tid]);
$rows = $items->fetchAll();

render('Ингредиенты', function() use ($rows) {
?>
  <div class="text-slate-400 text-sm mb-3">
    Цена задаётся <b>за 1 единицу</b> базовой UOM (например за 1g / 1ml / 1pcs). Это ускоряет MVP.
  </div>

  <form method="post" class="grid grid-cols-1 md:grid-cols-5 gap-2 mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create"/>
    <input name="name" placeholder="Название (молоко, кофе...)" required class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <select name="type" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10">
      <option value="ingredient">Ингредиент</option>
      <option value="packaging">Упаковка</option>
      <option value="other">Другое</option>
      <option value="semi_finished">Полуфабрикат</option>
    </select>
    <select name="uom_code" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10">
      <option value="g">g</option><option value="ml">ml</option><option value="pcs">pcs</option>
      <option value="kg">kg</option><option value="l">l</option>
    </select>
    <input name="yield_loss_pct" type="number" step="0.001" placeholder="Потери % (0)" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <input name="price_per_unit" type="number" step="0.0001" placeholder="Цена за 1 ед." class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <button class="md:col-span-5 px-4 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold">Добавить</button>
  </form>

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-300">
        <tr class="border-b border-white/10">
          <th class="text-left py-2">Название</th>
          <th class="text-left py-2">Тип</th>
          <th class="text-left py-2">UOM</th>
          <th class="text-right py-2">Потери %</th>
          <th class="text-right py-2">Цена/ед</th>
          <th class="text-right py-2"></th>
        </tr>
      </thead>
      <tbody class="text-slate-200">
        <?php foreach ($rows as $r): ?>
          <tr class="border-b border-white/5">
            <td class="py-2"><?= e($r['name']) ?></td>
            <td class="py-2 text-slate-400"><?= e($r['type']) ?></td>
            <td class="py-2 text-slate-400"><?= e($r['uom_code']) ?></td>
            <td class="py-2 text-right text-slate-400"><?= e((string)$r['yield_loss_pct']) ?></td>
            <td class="py-2 text-right"><?= e($r['price_per_unit'] !== null ? number_format((float)$r['price_per_unit'], 4, '.', ' ') : '-') ?></td>
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
      </tbody>
    </table>
  </div>
<?php
}, $user);
