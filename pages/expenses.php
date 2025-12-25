<?php
declare(strict_types=1);
$user = require_auth();
$tid = tenant_id();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $date = (string)($_POST['expense_date'] ?? now_date());
    $cat = trim((string)($_POST['category'] ?? ''));
    $amt = (float)($_POST['amount'] ?? 0);
    $note = trim((string)($_POST['note'] ?? ''));

    $st = $pdo->prepare("INSERT INTO expenses(tenant_id,expense_date,category,amount,note) VALUES(?,?,?,?,?)");
    $st->execute([$tid,$date,$cat,$amt,$note ?: null]);
    flash_set('success','Расход добавлен');
    redirect('/index.php?r=expenses');
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare("DELETE FROM expenses WHERE tenant_id=? AND id=?");
    $st->execute([$tid,$id]);
    flash_set('success','Удалено');
    redirect('/index.php?r=expenses');
  }
}

$st = $pdo->prepare("SELECT * FROM expenses WHERE tenant_id=? ORDER BY expense_date DESC, id DESC LIMIT 200");
$st->execute([$tid]);
$rows = $st->fetchAll();

render('Расходы', function() use ($rows) {
?>
  <form method="post" class="grid grid-cols-1 md:grid-cols-5 gap-2 mb-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create"/>
    <input name="expense_date" type="date" value="<?= e(now_date()) ?>" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <input name="category" required placeholder="Категория (аренда/маркетинг...)" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <input name="amount" type="number" step="0.01" required placeholder="Сумма" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <input name="note" placeholder="Комментарий" class="px-3 py-2 rounded-xl bg-black/20 border border-white/10"/>
    <button class="px-4 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold">Добавить</button>
  </form>

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-300">
        <tr class="border-b border-white/10">
          <th class="text-left py-2">Дата</th>
          <th class="text-left py-2">Категория</th>
          <th class="text-right py-2">Сумма</th>
          <th class="text-left py-2">Комментарий</th>
          <th class="text-right py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="border-b border-white/5">
            <td class="py-2 text-slate-400"><?= e($r['expense_date']) ?></td>
            <td class="py-2"><?= e($r['category']) ?></td>
            <td class="py-2 text-right"><?= e(money((float)$r['amount'])) ?></td>
            <td class="py-2 text-slate-400"><?= e((string)$r['note']) ?></td>
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
          <tr><td class="py-3 text-slate-400" colspan="5">Пока нет расходов.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php
}, $user);
