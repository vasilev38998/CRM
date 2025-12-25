<?php
declare(strict_types=1);
$cfg = require __DIR__ . '/inc/config.php';
session_name($cfg['session_name']);
session_start();

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/csrf.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';

if (auth_user()) redirect('/index.php?r=dashboard');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $tenantName = trim((string)($_POST['tenant_name'] ?? ''));
  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $pass = (string)($_POST['password'] ?? '');

  if (strlen($pass) < 8) {
    flash_set('error', 'Пароль должен быть минимум 8 символов');
    redirect('/register.php');
  }

  $pdo = db();
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $st->execute([$email]);
    if ($st->fetch()) {
      $pdo->rollBack();
      flash_set('error', 'Этот email уже зарегистрирован');
      redirect('/register.php');
    }

    $st = $pdo->prepare("INSERT INTO tenants(name, timezone, currency) VALUES(?,?,?)");
    $st->execute([$tenantName ?: 'Моя кофейня', 'Europe/London', 'RUB']);
    $tenantId = (int)$pdo->lastInsertId();

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO users(tenant_id, name, email, password_hash, role) VALUES(?,?,?,?,?)");
    $st->execute([$tenantId, $name ?: 'Owner', $email, $hash, 'owner']);
    $userId = (int)$pdo->lastInsertId();

    $uoms = [
      ['g','Грамм'], ['kg','Килограмм'], ['ml','Миллилитр'], ['l','Литр'], ['pcs','Штука'],
    ];
    $st = $pdo->prepare("INSERT INTO uoms(tenant_id, code, name) VALUES(?,?,?)");
    foreach ($uoms as $u) $st->execute([$tenantId, $u[0], $u[1]]);

    $pdo->commit();

    $_SESSION['uid'] = $userId;
    flash_set('success', 'Аккаунт создан!');
    redirect('/index.php?r=dashboard');
  } catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', 'Ошибка регистрации');
    redirect('/register.php');
  }
}

render('Регистрация', function() {
?>
  <form method="post" class="space-y-3 max-w-md">
    <?= csrf_field() ?>
    <div>
      <label class="text-sm text-slate-300">Название кофейни</label>
      <input name="tenant_name" required class="mt-1 w-full px-3 py-2 rounded-xl bg-black/20 border border-white/10 outline-none"/>
    </div>
    <div>
      <label class="text-sm text-slate-300">Ваше имя</label>
      <input name="name" required class="mt-1 w-full px-3 py-2 rounded-xl bg-black/20 border border-white/10 outline-none"/>
    </div>
    <div>
      <label class="text-sm text-slate-300">Email</label>
      <input name="email" type="email" required class="mt-1 w-full px-3 py-2 rounded-xl bg-black/20 border border-white/10 outline-none"/>
    </div>
    <div>
      <label class="text-sm text-slate-300">Пароль (мин. 8)</label>
      <input name="password" type="password" required class="mt-1 w-full px-3 py-2 rounded-xl bg-black/20 border border-white/10 outline-none"/>
    </div>
    <button class="px-4 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold">Создать аккаунт</button>
    <div class="text-sm text-slate-400">Уже есть аккаунт? <a class="underline" href="/login.php">Войти</a></div>
  </form>
<?php
});
