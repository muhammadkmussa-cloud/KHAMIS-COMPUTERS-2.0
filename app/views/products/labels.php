<?php
$serialLabels = $kind === 'serials';
$price = (float) $product['sell_price'];
$sheetGap = '2mm';
$base = url('products/' . $product['id'] . '/labels');
$qs = fn (array $p) => $base . (http_build_query($p) !== '' ? '?' . http_build_query($p) : '');
$shelfUrl   = $qs($thermal ? ['thermal' => 1, 'autoprint' => 1] : ['autoprint' => 1]);
$serialUrl  = $qs(array_merge(['kind' => 'serials'], $thermal ? ['thermal' => 1, 'autoprint' => 1] : ['autoprint' => 1]));
$thermalUrl = $qs(array_merge($serialLabels ? ['kind' => 'serials'] : [], ['thermal' => 1]));
$sheetUrl   = $qs($serialLabels ? ['kind' => 'serials'] : []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?></title>
<style>
  /* ==== Label geometry — adjust these two blocks to match your label stock ==== */
  @page { size: <?= $thermal ? '60mm 40mm' : 'A4 portrait' ?>; margin: <?= $thermal ? '0' : '6mm' ?>; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #fff; }
  body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #000; }

  .toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
             padding: 12px 14px; background: #f5f5f7; border-bottom: 1px solid #d2d2d7; }
  .toolbar h1 { font-size: 16px; margin: 0; font-weight: 600; }
  .toolbar .grow { flex: 1; }
  .toolbar a, .toolbar button { font-size: 13px; padding: 7px 14px; border-radius: 8px;
             border: 1px solid #d2d2d7; background: #fff; cursor: pointer; text-decoration: none; color: #1d1d1f; }
  .toolbar button.primary { background: #0071e3; border-color: #0071e3; color: #fff; }
  .toolbar input[type=number] { width: 70px; font-size: 13px; padding: 7px; border-radius: 8px; border: 1px solid #d2d2d7; }
  .hint { padding: 8px 14px; font-size: 12px; color: #6e6e73; }

  .sheet { display: grid; gap: <?= $sheetGap ?>; padding: 2mm;
           <?= $thermal
               ? 'grid-template-columns: 1fr;'
               : ($serialLabels ? 'grid-template-columns: repeat(4, 46mm);' : 'grid-template-columns: repeat(3, 63mm);') ?> }

  .label { background: #fff; border: 1px solid #000; border-radius: 4px; padding: 3px 5px;
           display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;
           break-inside: avoid; page-break-inside: avoid; }
  .label.shelf { width: 63mm; height: 33mm; }
  .label.serial { width: 46mm; height: 22mm; }
  .label.thermal { width: 60mm; height: 40mm; border-radius: 0; border: 1px solid #000; margin: 0 auto; }

  .ltop { display: flex; justify-content: space-between; align-items: baseline; gap: 4px; }
  .lname { font-weight: 700; font-size: 11px; line-height: 1.1; overflow: hidden;
           text-overflow: ellipsis; white-space: nowrap; }
  .lsku  { font-size: 8px; color: #333; letter-spacing: .4px; }
  .lprice { font-size: 15px; font-weight: 800; }
  .serial .lname { font-size: 9px; }
  .serial .lprice { font-size: 11px; }

  .lbc { display: flex; flex-direction: column; }
  .lbc svg { width: 100%; height: auto; max-height: 15mm; display: block; }
  .serial .lbc svg { max-height: 10mm; }
  .ltext { font-family: "Courier New", ui-monospace, Menlo, Consolas, monospace; font-size: 8px;
           letter-spacing: .5px; text-align: center; margin-top: 1px; white-space: nowrap; overflow: hidden; }

  .serialnum { font-weight: 800; font-size: 10px; letter-spacing: .3px; }

  @media print { .toolbar, .hint { display: none !important; } .sheet { padding: 0; } }
</style>
</head>
<body>
<div class="toolbar">
    <h1><?= e($serialLabels ? 'Serial / IMEI labels' : 'Shelf labels') ?> — <?= e($product['name']) ?></h1>
    <span class="grow"></span>
    <?php if (!$serialLabels): ?>
        <form method="get" action="<?= e(url('products/' . $product['id'] . '/labels')) ?>" style="display:flex;gap:6px;align-items:center">
            <label>Qty <input type="number" name="qty" min="1" max="500" value="<?= (int) $qty ?>"></label>
            <button type="submit">Update</button>
        </form>
    <?php endif; ?>
    <a href="<?= e($serialLabels ? $shelfUrl : $serialUrl) ?>"><?= $serialLabels ? 'Shelf labels' : 'Serial labels' ?></a>
    <a href="<?= e($thermal ? $sheetUrl : $thermalUrl) ?>"><?= $thermal ? 'A4 sheet' : 'Thermal 60×40' ?></a>
    <button class="primary" type="button" onclick="window.print()">Print</button>
    <a href="<?= e(url('products/' . $product['id'])) ?>">Back</a>
</div>
<div class="hint">
    <?= $thermal
        ? 'Thermal label 60×40mm — one label per page. In the print dialog pick your label printer, paper 60×40mm, margins “None”.'
        : ($serialLabels
            ? 'A4 sheet of 46×22mm serial stickers (4 per row). If your sticker sheet differs, adjust the “repeat(4, 46mm)” and .serial sizes at the top of this page.'
            : 'A4 sheet of 63×33mm shelf labels (3 per row). If your label sheet differs, adjust the “repeat(3, 63mm)” and .shelf sizes at the top of this page.') ?>
</div>

<div class="sheet">
<?php if ($serialLabels): ?>
    <?php foreach ($units as $u): ?>
        <div class="label serial<?= $thermal ? ' thermal' : '' ?>">
            <div class="ltop">
                <span class="lname"><?= e($product['name']) ?></span>
                <span class="lsku"><?= e($product['sku']) ?></span>
            </div>
            <div class="serialnum"><?= e($u['serial_number']) ?></div>
            <div class="lbc">
                <?= Barcode::svg((string) $u['serial_number'], 44, 2) ?>
                <div class="ltext"><?= e($u['serial_number']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$units): ?>
        <div class="hint">No in-stock serial units to label for this product.</div>
    <?php endif; ?>
<?php else: ?>
    <?php for ($i = 0; $i < $qty; $i++): ?>
        <div class="label shelf<?= $thermal ? ' thermal' : '' ?>">
            <div class="ltop">
                <span class="lname"><?= e($product['name']) ?></span>
                <span class="lsku"><?= e($product['sku']) ?></span>
            </div>
            <div class="lprice"><?= money($price) ?></div>
            <div class="lbc">
                <?= Barcode::svg((string) $barcodeValue, 46, 2) ?>
                <div class="ltext"><?= e($barcodeValue) ?></div>
            </div>
        </div>
    <?php endfor; ?>
<?php endif; ?>
</div>

<script>
  // Only auto-open the print dialog when arriving from a "Print" click.
  if (new URLSearchParams(location.search).get('autoprint') === '1') {
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 200); });
  }
</script>
</body>
</html>
