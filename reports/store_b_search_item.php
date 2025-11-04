<?php
include 'db_con.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') exit;

// Search in all three store B tables
$results = [];

// 🧪 1. Chemicals
$sql = "
    SELECT 
        id, 
        chemical_name AS item_name,
        chemical_code AS code,
        remaining_quantity,
        units,
        pack_size,
        'store_b_chemicals_in' AS source_table
    FROM store_b_chemicals_in
    WHERE chemical_name LIKE CONCAT('%', ?, '%')
    GROUP BY chemical_name
    LIMIT 10
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $q);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    // Combine all remaining quantities of same chemical_code
    $sumStmt = $conn->prepare("
        SELECT SUM(remaining_quantity) AS total_rem
        FROM store_b_chemicals_in
        WHERE chemical_code = (
            SELECT chemical_code FROM store_b_chemicals_in WHERE id = ?
        )
    ");
    $sumStmt->bind_param('i', $r['id']);
    $sumStmt->execute();
    $sumResult = $sumStmt->get_result()->fetch_assoc();
    $r['remaining_quantity'] = $sumResult['total_rem'] ?? $r['remaining_quantity'];
    $sumStmt->close();

    $results[] = $r;
}
$stmt->close();

// ⚙️ 2. Engineering Products
$sql = "
    SELECT 
        id, 
        product_name AS item_name,
        product_code AS code,
        remaining_quantity,
        units,
        pack_size,
        'store_b_engineering_products_in' AS source_table
    FROM store_b_engineering_products_in
    WHERE product_name LIKE CONCAT('%', ?, '%')
    GROUP BY product_name
    LIMIT 10
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $q);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $sumStmt = $conn->prepare("
        SELECT SUM(remaining_quantity) AS total_rem
        FROM store_b_engineering_products_in
        WHERE product_code = (
            SELECT product_code FROM store_b_engineering_products_in WHERE id = ?
        )
    ");
    $sumStmt->bind_param('i', $r['id']);
    $sumStmt->execute();
    $sumResult = $sumStmt->get_result()->fetch_assoc();
    $r['remaining_quantity'] = $sumResult['total_rem'] ?? $r['remaining_quantity'];
    $sumStmt->close();

    $results[] = $r;
}
$stmt->close();

// 🏭 3. Finished Products
$sql = "
    SELECT 
        id, 
        product_name AS item_name,
        product_code AS code,
        remaining_quantity,
        units,
        pack_size,
        'store_b_finished_products_in' AS source_table
    FROM store_b_finished_products_in
    WHERE product_name LIKE CONCAT('%', ?, '%')
    GROUP BY product_name
    LIMIT 10
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $q);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $sumStmt = $conn->prepare("
        SELECT SUM(remaining_quantity) AS total_rem
        FROM store_b_finished_products_in
        WHERE product_code = (
            SELECT product_code FROM store_b_finished_products_in WHERE id = ?
        )
    ");
    $sumStmt->bind_param('i', $r['id']);
    $sumStmt->execute();
    $sumResult = $sumStmt->get_result()->fetch_assoc();
    $r['remaining_quantity'] = $sumResult['total_rem'] ?? $r['remaining_quantity'];
    $sumStmt->close();

    $results[] = $r;
}
$stmt->close();

// ✅ Output suggestions
if (empty($results)) {
    echo "<div class='autocomplete-item text-gray-500'>No items found</div>";
} else {
    foreach ($results as $row) {
        $label = htmlspecialchars($row['item_name']);
        $remaining = htmlspecialchars($row['remaining_quantity']);
        $unit = htmlspecialchars($row['units']);
        $source = htmlspecialchars($row['source_table']);
        $pack = htmlspecialchars($row['pack_size']);

        echo "
        <div class='item-suggestion autocomplete-item'
             data-id='{$row['id']}'
             data-label='{$label}'
             data-remaining='{$remaining}'
             data-unit='{$unit}'
             data-pack_size='{$pack}'
             data-source='{$source}'>
             {$label} <span class='text-gray-500 text-sm'>(Remaining: {$remaining} {$unit})</span>
        </div>";
    }
}
?>
