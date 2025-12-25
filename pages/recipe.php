<?php
declare(strict_types=1);
$user = require_auth();
$tid = tenant_id();
$pdo = db();

$skuId = (int)($_GET['sku_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'add_line') {
    $skuId = (int)($_POST['sku_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (float)($_POST['qty'] ?? 0);
    $waste = (float)($_POST['waste_pct'] ?? 0);

    $st = $pdo->prepare("SELECT id FROM recipes WHERE tenant_id=? AND sku_id=? AND version=1");
    $st->execute([$tid,$skuId]);
    $r = $st->fetch();
    if (!$r) {
      $st = $pdo->prepare("INSERT INTO recipes(tenant_id, sku_id, version) VALUES(?,?,1)");
      $st->execute([$tid,$skuId]);
      $recipeId = (int)$pdo->lastInsertId();
    } else {
      $recipeId = (int)$r['id'];
    }

    $st = $pdo->prepare("INSERT INTO recipe_lines(tenant_id, recipe_id, product_id, qty, waste_pct) VALUES(?,?,?,?,?)");
    $st->execute([$tid,$recipeId,$productId,$qty,$waste]);

    flash_set('success','Строка техкарты добавлена');
    redirect('/index.php?r=recipe&sku_id='.$skuId);
  }

  if ($action === 'delete_line') {
    $lineId = (int)($_POST['line_id'] ?? 0);
    $skuId = (int)($_POST['sku_id'] ?? 0);
    $st = $pdo->prepare("DELETE FROM recipe_lines WHERE tenant_id=? AND id=?");
    $st->execute([$tid,$lineId]);
    flash_set('success','Удалено');
    redirect('/index.php?r=recipe&sku_id='.$skuId);
  }
}

$skus = $pdo->prepare("SELECT id, name FROM skus WHERE tenant_id=? ORDER BY name");
$skus->execute([$tid]);
$skuRows = $skus->fetchAll();

$products = $pdo->prepare("SELECT id, name, uom_code FROM products WHERE tenant_id=? ORDER BY name");
$products->execute([$tid]);
$productRows = $products->fetchAll();

$recipeLines = [];
if ($skuId) {
  $st = $pdo->prepare("
    SELECT rl.id, p.name product_name, p.uom_code, rl.qty, rl.waste_pct
    FROM recipes r
    JOIN recipe_lines rl ON rl.recipe_id=r.id AND rl.tenant_id=r.tenant_id
    JOIN products p ON p.id=rl.product_id AND p.tenant_id=rl.tenant_id
    WHERE r.tenant_id=? AND r.sku_id=? AND r.version=1
    ORDER BY rl.id DESC
  ");
  $st->execute([$tid,$skuId]);
  $recipeLines = $st->fetchAll();
}

render('Техкарты', function() use ($skuId, $skuRows, $productRows, $recipeLines) {
?>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <div class="md:col-span-1">
      <div class="text-slate-300 text-sm mb-2">Выбери SKU</div>
      <form method="get" class="flex gap-2">
        <input type="hidden" name="r" value="recipe"/>
        <select name="sku_id" class="w-full px-3 py-2 rounded-xl bg-black/20 border border-white/10">
          <option value="0">— выбрать —</option>
          <?php foreach ($skuRows as $s): ?>
            <option value="<?= e((string)$s['id']) ?>" <?= $skuId===(int)$s['id']?'selected':'' ?>>
              <?= e($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="px-3 py-2 rounded-xl bg-white/10 border border-white/10">Открыть</button>
      </form>
    </div>

    <div class="md:col-span-2">
      <?php if (!$skuId): ?>
        <div class="text-slate-400">Выбери SKU слева, чтобы редактировать техкарту.</div>
      <?php else: ?>
        <div class="text-slate-300 text-sm mb-2">Добавить строку техкарты</div>
        <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_line"/>
          <input type="hidden" name="sku_id" value="<?= e((string)$skuId) ?>"/>
          <select name="product_id" required class="px-3 py-2 rounded-xl bg-black/20 border border-white/10">
            <option value="">Ингредиент…</option>
            <?php foreach ($productRows as $p): ?>
              <option value="<?= e((string)$p['id']) ?>"><?= e($p['name']) ?> (<?= e($p['uom_code']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <input name="qty" type="number" step="0.0001" required placeholder="Кол-во (в uom)" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
          <input name="waste_pct" type="number" step="0.001" placeholder="Потери % (0)" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
          <button class="px-3 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold">Добавить</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($skuId): ?>
    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-300">
          <tr class="border-b border-white/10">
            <th class="text-left py-2">Ингредиент</th>
            <th class="text-left py-2">UOM</th>
            <th class="text-right py-2">Qty</th>
            <th class="text-right py-2">Потери %</th>
            <th class="text-right py-2"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recipeLines as $l): ?>
            <tr class="border-b border-white/5">
              <td class="py-2"><?= e($l['product_name']) ?></td>
              <td class="py-2 text-slate-400"><?= e($l['uom_code']) ?></td>
              <td class="py-2 text-right"><?= e(number_format((float)$l['qty'], 4, '.', ' ')) ?></td>
              <td class="py-2 text-right text-slate-400"><?= e((string)$l['waste_pct']) ?></td>
              <td class="py-2 text-right">
                <form method="post" onsubmit="return confirm('Удалить строку?');" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_line"/>
                  <input type="hidden" name="sku_id" value="<?= e((string)$skuId) ?>"/>
                  <input type="hidden" name="line_id" value="<?= e((string)$l['id']) ?>"/>
                  <button class="px-3 py-1 rounded-lg bg-rose-500/15 border border-rose-400/30 text-rose-200">Удалить</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recipeLines): ?>
            <tr><td class="py-3 text-slate-400" colspan="5">Строк нет. Добавь ингредиенты выше.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php
}, $user);
