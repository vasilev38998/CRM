<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';

function render(string $title, callable $content, array $user = null): void {
  $app = (require __DIR__ . '/config.php')['app_name'];
  $success = flash_get('success');
  $error = flash_get('error');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= e($title) ?> — <?= e($app) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100">
  <div class="max-w-6xl mx-auto px-4 py-5">
    <div class="flex items-center justify-between gap-3 mb-6">
      <a href="/index.php" class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400"></div>
        <div class="font-bold tracking-tight"><?= e($app) ?></div>
      </a>
      <div class="flex items-center gap-2">
        <?php if ($user): ?>
          <span class="text-slate-300 text-sm"><?= e($user['name']) ?></span>
          <a class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/10" href="/index.php?r=dashboard">Кабинет</a>
          <a class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/10" href="/logout.php">Выйти</a>
        <?php else: ?>
          <a class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 border border-white/10" href="/login.php">Войти</a>
          <a class="px-3 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold" href="/register.php">Регистрация</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($user): ?>
      <div class="flex flex-wrap gap-2 mb-5">
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=dashboard">Дашборд</a>
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=products">Ингредиенты</a>
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=skus">SKU (меню)</a>
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=recipe">Техкарты</a>
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=sales">Продажи</a>
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=expenses">Расходы</a>
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=report_sku_margin">Отчёт: маржа SKU</a>
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=report_pl">Отчёт: P&L</a>
        <a class="px-3 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10" href="/index.php?r=import_sales">Импорт продаж CSV</a>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="mb-4 p-3 rounded-xl bg-emerald-500/15 border border-emerald-400/30 text-emerald-200"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="mb-4 p-3 rounded-xl bg-rose-500/15 border border-rose-400/30 text-rose-200"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="p-5 rounded-2xl bg-white/5 border border-white/10 shadow-[0_18px_60px_rgba(0,0,0,.45)]">
      <div class="text-xl font-semibold tracking-tight mb-4"><?= e($title) ?></div>
      <?php $content(); ?>
    </div>

    <div class="text-slate-400 text-xs mt-6">© <?= date('Y') ?> CoffeeOps</div>
  </div>
</body>
</html>
<?php } ?>
