<?php
include 'db_con.php';

$q = trim($_GET['q'] ?? '');
$sourceFilter = trim($_GET['source'] ?? ''); // optional filter
if ($q === '') { echo ''; exit; }

$qLike = "%$q%";
$results = []; // ✅ always initialize to avoid warnings

// 1️⃣ finished_products
if ($sourceFilter === '' || $sourceFilter === 'finished_products') {
    $stmt = $conn->prepare("
        SELECT id, product_name AS label, batch_number AS batch_no, remaining_size AS remaining, unit
        FROM finished_products
        WHERE product_name LIKE ? AND remaining_size > 0
        ORDER BY created_at ASC
        LIMIT 12
    ");
    $stmt->bind_param("s", $qLike);
    $stmt->execute();
    $results['finished_products'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 2️⃣ chemicals_in
if ($sourceFilter === '' || $sourceFilter === 'chemicals_in') {
    $stmt = $conn->prepare("
        SELECT id, chemical_name AS label, rm_lot_no AS batch_no, remaining_quantity AS remaining, 'kg' AS unit
        FROM chemicals_in
        WHERE chemical_name LIKE ? AND remaining_quantity > 0
        ORDER BY date_added ASC
        LIMIT 12
    ");
    $stmt->bind_param("s", $qLike);
    $stmt->execute();
    $results['chemicals_in'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 3️⃣ stock_in — include batch_no
if ($sourceFilter === '' || $sourceFilter === 'stock_in') {
    $stmt = $conn->prepare("
        SELECT id, stock_name AS label, batch_no, quantity AS remaining, unit
        FROM stock_in
        WHERE stock_name LIKE ? AND quantity > 0
        ORDER BY created_at ASC
        LIMIT 12
    ");
    $stmt->bind_param("s", $qLike);
    $stmt->execute();
    $results['stock_in'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ✅ Output all results
if (!empty($results)) {
    foreach ($results as $sourceTable => $rows) {
        foreach ($rows as $r) {
            $label = htmlspecialchars($r['label']);
            $unit = htmlspecialchars($r['unit']);
            $remaining = htmlspecialchars($r['remaining']);
            $batchNo = htmlspecialchars($r['batch_no'] ?? '');

            // 🧠 Custom label: use "Batch" for finished_products & stock_in, "Lot" for chemicals_in
            if (!empty($batchNo)) {
                if ($sourceTable === 'chemicals_in') {
                    $batchText = " (Lot: {$batchNo})";
                } else {
                    $batchText = " (Batch: {$batchNo})";
                }
            } else {
                $batchText = '';
            }

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
} else {
    echo "<div class='px-3 py-2 text-gray-500 text-sm'>No matching items found.</div>";
}


$conn->close();
?>
