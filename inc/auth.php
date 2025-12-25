<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function auth_user(): ?array {
  if (empty($_SESSION['uid'])) return null;
  $stmt = db()->prepare("SELECT id, tenant_id, name, email, role FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['uid']]);
  $u = $stmt->fetch();
  return $u ?: null;
}

function require_auth(): array {
  $u = auth_user();
  if (!$u) redirect('/login.php');
  return $u;
}

function logout_user(): void {
  unset($_SESSION['uid']);
}
