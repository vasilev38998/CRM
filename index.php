<?php
declare(strict_types=1);

$cfg = require __DIR__ . '/inc/config.php';
session_name($cfg['session_name']);
session_start();

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/csrf.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/tenant.php';
require_once __DIR__ . '/inc/layout.php';

$user = auth_user();
if (!$user) redirect('/login.php');

$r = $_GET['r'] ?? 'dashboard';

$routes = [
  'dashboard' => __DIR__ . '/pages/dashboard.php',
  'products' => __DIR__ . '/pages/products.php',
  'skus' => __DIR__ . '/pages/skus.php',
  'recipe' => __DIR__ . '/pages/recipe.php',
  'sales' => __DIR__ . '/pages/sales.php',
  'expenses' => __DIR__ . '/pages/expenses.php',
  'report_sku_margin' => __DIR__ . '/pages/report_sku_margin.php',
  'report_pl' => __DIR__ . '/pages/report_pl.php',
  'import_sales' => __DIR__ . '/pages/import_sales.php',
];

if (!isset($routes[$r])) {
  http_response_code(404);
  echo "Not Found";
  exit;
}

require $routes[$r];
