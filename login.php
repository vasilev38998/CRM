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
  $email = trim((string)($_POST['email'] ?? ''));
  $pass = (string)($_POST['password'] ?? '');

  $stmt = db()->prepare("SELECT id, password_hash FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if (!$u || !password_verify($pass, $u['password_hash'])) {
    flash_set('error', 'Неверный email или пароль');
    redirect('/login.php');
  }

  $_SESSION['uid'] = (int)$u['id'];
  flash_set('success', 'Добро пожаловать!');
  redirect('/index.php?r=dashboard');
}

render('Вход', function() {
?>
  <form method="post" class="space-y-3 max-w-md">
    <?= csrf_field() ?>
    <div>
      <label class="text-sm text-slate-300">Email</label>
      <input name="email" type="email" required class="mt-1 w-full px-3 py-2 rounded-xl bg-black/20 border border-white/10 outline-none"/>
    </div>
    <div>
      <label class="text-sm text-slate-300">Пароль</label>
      <input name="password" type="password" required class="mt-1 w-full px-3 py-2 rounded-xl bg-black/20 border border-white/10 outline-none"/>
    </div>
    <button class="px-4 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold">Войти</button>
    <div class="text-sm text-slate-400">Нет аккаунта? <a class="underline" href="/register.php">Регистрация</a></div>
  </form>
<?php
});
