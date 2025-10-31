<?php
include 'db_con.php';

$q = trim($_GET['q'] ?? '');
$sourceFilter = trim($_GET['source'] ?? '');

if ($q === '') {
    echo '';
    exit;
}

$qLike = "%$q%";
$results = [];

/* ──────────────────────────────
   1️⃣ FINISHED PRODUCTS
────────────────────────────────*/
/* ──────────────────────────────
   1️⃣ FINISHED PRODUCTS
────────────────────────────────*/
if ($sourceFilter === '' || $sourceFilter === 'finished_products') {
    $stmt = $conn->prepare("
        SELECT 
            fp.id,
            fp.product_name AS label,
            fp.batch_number AS batch_no,
            fp.remaining_size,
            fp.unit,
            fp.pack_size,               -- ✅ pack_size directly from finished_products
            m.material_name
        FROM finished_products fp
        LEFT JOIN materials m ON fp.material_id = m.id
        WHERE fp.product_name LIKE ? 
          AND fp.remaining_size > 0
        ORDER BY fp.created_at ASC
        LIMIT 12
    ");
    $stmt->bind_param('s', $qLike);
    $stmt->execute();
    $res = $stmt->get_result();

    $finishedRows = [];
    while ($r = $res->fetch_assoc()) {
        // Optional: calculate remaining packs for reference
        $remaining_packs = ($r['pack_size'] > 0)
            ? round($r['remaining_size'] / $r['pack_size'])
            : 0;

        $finishedRows[] = [
            'id' => $r['id'],
            'label' => $r['label'],
            'batch_no' => $r['batch_no'],
            'material_name' => $r['material_name'] ?? 'N/A',
            'pack_size' => $r['pack_size'] ?? 0,
            'unit' => $r['unit'],
            'remaining' => $r['remaining_size'],   // ✅ Use actual remaining_size here
            'remaining_packs' => $remaining_packs  // ✅ optional field if you want to show it later
        ];
    }

    $results['finished_products'] = $finishedRows;
    $stmt->close();
}

/* ──────────────────────────────
   2️⃣ CHEMICALS IN
────────────────────────────────*/
if ($sourceFilter === '' || $sourceFilter === 'chemicals_in') {
    $stmt = $conn->prepare("
        SELECT 
            id,
            chemical_name AS label,
            rm_lot_no AS batch_no,
            remaining_quantity AS remaining,
            'kg' AS unit
        FROM chemicals_in
        WHERE chemical_name LIKE ? 
          AND remaining_quantity > 0
        ORDER BY date_added ASC
        LIMIT 12
    ");
    $stmt->bind_param('s', $qLike);
    $stmt->execute();
    $results['chemicals_in'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ──────────────────────────────
   3️⃣ STOCK IN
────────────────────────────────*/
if ($sourceFilter === '' || $sourceFilter === 'stock_in') {
    $stmt = $conn->prepare("
        SELECT 
            id,
            stock_name AS label,
            batch_no,
            quantity AS remaining,
            unit
        FROM stock_in
        WHERE stock_name LIKE ? 
          AND quantity > 0
        ORDER BY created_at ASC
        LIMIT 12
    ");
    $stmt->bind_param('s', $qLike);
    $stmt->execute();
    $results['stock_in'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ──────────────────────────────
   ✅ DISPLAY RESULTS
────────────────────────────────*/
if (!empty($results)) {
    foreach ($results as $sourceTable => $rows) {
        foreach ($rows as $r) {
            $label = htmlspecialchars($r['label']);
            $unit = htmlspecialchars($r['unit']);
            $remaining = htmlspecialchars($r['remaining']);
            $batchNo = htmlspecialchars($r['batch_no'] ?? '');
            $materialName = htmlspecialchars($r['material_name'] ?? '');
            $packSize = htmlspecialchars($r['pack_size'] ?? '');

            // Use “Batch” for most, “Lot” for chemicals
            if (!empty($batchNo)) {
                $batchText = ($sourceTable === 'chemicals_in')
                    ? " (Lot: {$batchNo})"
                    : " (Batch: {$batchNo})";
            } else {
                $batchText = '';
            }

            // ✅ Custom display text for finished_products
            if ($sourceTable === 'finished_products') {
    echo "
    <div class='item-suggestion px-3 py-2 hover:bg-gray-100 cursor-pointer'
         data-id='{$r['id']}'
         data-label='{$label}{$batchText}'
         data-source='{$sourceTable}'
         data-remaining='{$remaining}'
         data-unit='{$unit}'
         data-pack_size='{$packSize}'
         data-material_name='{$materialName}'>
         <div class='font-semibold text-blue-700'>{$label}{$batchText}</div>
         <div class='text-sm text-gray-600'>
            Material: <span class='font-medium'>{$materialName}</span> | 
            Pack: <span class='font-medium'>{$packSize} {$unit}</span> | 
            Remaining: <span class='font-medium'>{$remaining}</span>
         </div>
    </div>";
            } else {
                // ✅ Default display for other tables
                echo "
                <div class='item-suggestion px-3 py-2 hover:bg-gray-100 cursor-pointer'
                     data-id='{$r['id']}'
                     data-label='{$label}{$batchText}'
                     data-source='{$sourceTable}'
                     data-remaining='{$remaining}'
                     data-unit='{$unit}'>
                     {$label}{$batchText} — {$remaining} {$unit}
                </div>";
            }
        }
    }
} else {
    echo "<div class='px-3 py-2 text-gray-500 text-sm'>No matching items found.</div>";
}

$conn->close();
?>
