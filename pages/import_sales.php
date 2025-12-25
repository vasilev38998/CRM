<?php
declare(strict_types=1);
$user = require_auth();
$tid = tenant_id();
$pdo = db();

$help = "CSV формат (разделитель запятая или точка с запятой):\n".
        "sale_date,sku_name,qty,gross_revenue,channel\n".
        "2025-12-01,Капучино M,12,5400,in_store\n".
        "2025-12-01,Круассан,5,1250,takeaway\n".
        "channel опционально (по умолчанию in_store)";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    flash_set('error', 'Ошибка загрузки файла');
    redirect('/index.php?r=import_sales');
  }

  $tmp = $_FILES['csv']['tmp_name'];
  $content = file_get_contents($tmp);
  if ($content === false) {
    flash_set('error', 'Не удалось прочитать файл');
    redirect('/index.php?r=import_sales');
  }

  // detect delimiter
  $firstLine = strtok($content, "\n");
  $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

  $fh = fopen($tmp, 'r');
  if (!$fh) {
    flash_set('error', 'Не удалось открыть файл');
    redirect('/index.php?r=import_sales');
  }

  $header = fgetcsv($fh, 0, $delim);
  if (!$header) {
    fclose($fh);
    flash_set('error', 'Пустой CSV');
    redirect('/index.php?r=import_sales');
  }

  $map = [];
  foreach ($header as $i => $h) $map[strtolower(trim($h))] = $i;

  $need = ['sale_date','sku_name','qty','gross_revenue'];
  foreach ($need as $n) {
    if (!array_key_exists($n, $map)) {
      fclose($fh);
      flash_set('error', 'Не найден столбец: '.$n);
      redirect('/index.php?r=import_sales');
    }
  }
  $idxDate = $map['sale_date'];
  $idxSku = $map['sku_name'];
  $idxQty = $map['qty'];
  $idxRev = $map['gross_revenue'];
  $idxCh = $map['channel'] ?? null;

  $pdo->beginTransaction();
  try {
    $selSku = $pdo->prepare("SELECT id FROM skus WHERE tenant_id=? AND name=? LIMIT 1");
    $insSale = $pdo->prepare("
      INSERT INTO daily_sales(tenant_id,sale_date,sku_id,qty,gross_revenue,channel)
      VALUES(?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE qty=VALUES(qty), gross_revenue=VALUES(gross_revenue)
    ");

    $created = 0;
    $updated = 0;
    $rows = 0;

    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
      if (count($row) < 4) continue;
      $rows++;

      $date = trim((string)($row[$idxDate] ?? ''));
      $skuName = trim((string)($row[$idxSku] ?? ''));
      $qty = (float)str_replace(',', '.', (string)($row[$idxQty] ?? '0'));
      $rev = (float)str_replace(',', '.', (string)($row[$idxRev] ?? '0'));
      $ch = $idxCh !== null ? trim((string)($row[$idxCh] ?? '')) : '';
      if ($ch === '') $ch = 'in_store';

      if ($date === '' || $skuName === '') continue;

      $selSku->execute([$tid, $skuName]);
      $s = $selSku->fetch();
      if (!$s) {
        // auto-create SKU with price 0 (can be edited later)
        $ins = $pdo->prepare("INSERT INTO skus(tenant_id,name,category,price,is_active) VALUES(?,?,NULL,0,1)");
        $ins->execute([$tid, $skuName]);
        $skuId = (int)$pdo->lastInsertId();
      } else {
        $skuId = (int)$s['id'];
      }

      // We don't know if insert or update easily without extra select; execute anyway.
      $insSale->execute([$tid, $date, $skuId, $qty, $rev, $ch]);
      $created++;
    }

    fclose($fh);
    $pdo->commit();

    flash_set('success', 'Импорт завершён. Обработано строк: '.$rows);
    redirect('/index.php?r=sales');
  } catch (Throwable $e) {
    fclose($fh);
    $pdo->rollBack();
    flash_set('error', 'Ошибка импорта CSV');
    redirect('/index.php?r=import_sales');
  }
}

render('Импорт продаж CSV', function() use ($help) {
?>
  <div class="text-slate-400 text-sm mb-3 whitespace-pre-line"><?= e($help) ?></div>

  <form method="post" enctype="multipart/form-data" class="space-y-3 max-w-xl">
    <?= csrf_field() ?>
    <input type="file" name="csv" accept=".csv,text/csv" required class="block w-full text-sm text-slate-200
      file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0
      file:text-sm file:font-semibold file:bg-white/10 file:text-slate-100 hover:file:bg-white/15"/>
    <button class="px-4 py-2 rounded-xl bg-gradient-to-br from-violet-500 to-emerald-400 text-slate-950 font-semibold">Импортировать</button>
  </form>

  <div class="mt-4 text-slate-400 text-sm">
    Подсказка: если SKU в CSV не найден — он будет создан автоматически (цена продажи = 0, можно поправить в “SKU (меню)”).
  </div>
<?php
}, $user);
