<?php
include 'db_con.php';

// ✅ Validate production run ID
$run_id = intval($_POST['production_run_id'] ?? 0);
if (!$run_id) {
    die("Missing production run ID.");
}

// ✅ Fetch product name linked to this production run
$productQuery = $conn->prepare("
    SELECT p.name AS product_name
    FROM production_runs pr
    JOIN bill_of_materials b ON pr.request_id = b.id
    JOIN products p ON b.product_id = p.id
    WHERE pr.id = ?
");
$productQuery->bind_param("i", $run_id);
$productQuery->execute();
$productResult = $productQuery->get_result();

if ($productResult->num_rows === 0) {
    die("Product not found for this production run.");
}

$productRow = $productResult->fetch_assoc();
$product_name = $productRow['product_name'];
$productQuery->close();

// ✅ Loop through each packaging material row
foreach ($_POST['material_id'] as $i => $mat_id) {
    if (empty($mat_id)) continue;

    $pack_size = floatval($_POST['pack_size'][$i] ?? 0);
    $qty_used = floatval($_POST['quantity_used'][$i] ?? 0);
    $unpackaged_qty = floatval($_POST['unpackaged_qty'][$i] ?? 0);
    $unit_cost = floatval($_POST['cost_per_unit'][$i] ?? 0);
    $units = trim($_POST['unit'][$i] ?? ''); // ✅ column name is `units`
    $remarks = trim($_POST['remarks'][$i] ?? '');
    $total_cost = $qty_used * $unit_cost;

    // ✅ Insert into packaging table
    $sql = "INSERT INTO packaging (
                production_run_id, 
                item_name, 
                material_id, 
                pack_size, 
                units, 
                quantity_used, 
                unpackaged_qty, 
                cost_per_unit, 
                total_cost, 
                remarks, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";

   $stmt = $conn->prepare($sql);
$stmt->bind_param(
    "isidsdddds",
    $run_id,
    $product_name,
    $mat_id,
    $pack_size,
    $units,
    $qty_used,
    $unpackaged_qty,
    $unit_cost,
    $total_cost,
    $remarks
);
$stmt->execute();
$stmt->close();

}

// ✅ Redirect to list page with success message
header("Location: packaging_list.php?msg=Packaging+Updated+Successfully");
exit;
?>
