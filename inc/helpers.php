<?php
declare(strict_types=1);

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): never {
  header("Location: {$path}");
  exit;
}

function now_date(): string { return date('Y-m-d'); }

function flash_set(string $key, string $msg): void {
  $_SESSION['flash'][$key] = $msg;
}
function flash_get(string $key): ?string {
  if (!isset($_SESSION['flash'][$key])) return null;
  $m = $_SESSION['flash'][$key];
  unset($_SESSION['flash'][$key]);
  return $m;
}

function money(float $v): string {
  return number_format($v, 2, '.', ' ');
}
