<?php
require 'db_con.php';

if (!isset($_GET['product_id'])) {
    echo json_encode(['error' => 'Missing product ID']);
    exit;
}

$product_id = intval($_GET['product_id']);

// Get BOM details
$bom_query = $conn->prepare("
    SELECT id, std_quantity, unit
    FROM bom
    WHERE product_id = ?
    LIMIT 1
");
$bom_query->bind_param("i", $product_id);
$bom_query->execute();
$bom = $bom_query->get_result()->fetch_assoc();
$bom_query->close();

if (!$bom) {
    echo json_encode(['items' => []]);
    exit;
}

$bom_id = $bom['id'];

// Pull materials + total remaining from chemicals_in
$sql = "
SELECT 
    n.chemical_name,
    n.chemical_code,
    bm.std_quantity AS quantity_required,
    bm.unit,
    COALESCE(SUM(ci.remaining_quantity), 0) AS remaining_quantity
FROM bom_materials bm
JOIN chemical_names n ON bm.chemical_id = n.id
LEFT JOIN chemicals_in ci ON ci.chemical_code = n.chemical_code
WHERE bm.bom_id = ?
GROUP BY n.chemical_name, n.chemical_code, bm.std_quantity, bm.unit
ORDER BY n.chemical_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'std_quantity' => $bom['std_quantity'],
    'unit' => $bom['unit'],
    'items' => $materials
]);
?>
