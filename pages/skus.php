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
    $cat = trim((string)($_POST['category'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);

    $st = $pdo->prepare("INSERT INTO skus(tenant_id,name,category,price,is_active) VALUES(?,?,?,?,1)");
    $st->execute([$tid,$name,$cat ?: null,$price]);
    flash_set('success','SKU добавлен');
    redirect('/index.php?r=skus');
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare("DELETE FROM skus WHERE tenant_id=? AND id=?");
    $st->execute([$tid,$id]);
    flash_set('success','Удалено');
    redirect('/index.php?r=skus');
  }
}

$st = $pdo->prepare("SELECT * FROM skus WHERE tenant_id=? ORDER BY id DESC");
$st->execute([$tid]);
$rows = $st->fetchAll();

render('SKU (меню)', function() use ($rows) {
?>
  <form method="post" class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create"/>
    <input name="name" placeholder="Название (Капучино M, Круассан...)" required class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <input name="category" placeholder="Категория (coffee/bakery/food)" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <input name="price" type="number" step="0.01" placeholder="Цена продажи" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <button class="px-4 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold">Добавить</button>
  </form>

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-300">
        <tr class="border-b border-white/10">
          <th class="text-left py-2">Название</th>
          <th class="text-left py-2">Категория</th>
          <th class="text-right py-2">Цена</th>
          <th class="text-right py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="border-b border-white/5">
            <td class="py-2"><?= e($r['name']) ?></td>
            <td class="py-2 text-slate-400"><?= e((string)$r['category']) ?></td>
            <td class="py-2 text-right"><?= e(number_format((float)$r['price'], 2, '.', ' ')) ?></td>
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
