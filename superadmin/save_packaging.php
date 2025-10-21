<?php
include 'db_con.php';

// ✅ Validate production run ID
$run_id = intval($_POST['production_run_id'] ?? 0);
if (!$run_id) {
    die("Missing production run ID.");
}

// ✅ Fetch product details linked to this production run
$productQuery = $conn->prepare("
    SELECT p.id AS product_id, p.name AS product_name, b.batch_number, pr.obtained_yield
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
$product_id = $productRow['product_id'];
$product_name = $productRow['product_name'];
$batch_number = $productRow['batch_number'];
$obtained_yield = $productRow['obtained_yield'];
$productQuery->close();

// ✅ Initialize totals for updating products later
$total_quantity_used = 0;
$first_pack_size = 0;
$first_unit = "";

// ✅ Loop through each packaging material row
foreach ($_POST['material_id'] as $i => $mat_id) {
    if (empty($mat_id)) continue;

    $pack_size = floatval($_POST['pack_size'][$i] ?? 0);
    $qty_used = floatval($_POST['quantity_used'][$i] ?? 0);
    $unpackaged_qty = floatval($_POST['unpackaged_qty'][$i] ?? 0);
    $unit_cost = floatval($_POST['cost_per_unit'][$i] ?? 0);
    $units = trim($_POST['unit'][$i] ?? '');
    $remarks = trim($_POST['remarks'][$i] ?? '');
    $total_cost = $qty_used * $unit_cost;

    // Capture the first row pack size and unit for updating product
    if ($i == 0) {
        $first_pack_size = $pack_size;
        $first_unit = $units;
    }

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

    // ✅ Add total quantity used for later product update
    $total_quantity_used += $qty_used;
}

// ✅ Update product stock quantities
if ($product_id && $total_quantity_used > 0) {

    // Fetch existing product data
    $check = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $check->bind_param("i", $product_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        // Calculate new totals
        $new_remaining = ($existing['remaining_quantity'] ?? 0) + $total_quantity_used;
        $new_obtained = ($existing['obtained_quantity'] ?? 0) + $total_quantity_used;

        // ✅ (Optional) Update product if needed
        $update = $conn->prepare("
            UPDATE products 
            SET remaining_quantity = ?, obtained_quantity = ?
            WHERE id = ?
        ");
        $update->bind_param("ddi", $new_remaining, $new_obtained, $product_id);
        $update->execute();
        $update->close();
    }
}

// ✅ Insert finished product record with product_id
if ($product_name && $batch_number) {
    $insert = $conn->prepare("
        INSERT INTO finished_products 
        (product_id, product_name, batch_number, obtained_yield, unit, pack_size, remaining_size)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param(
        "issdsdd",
        $product_id,
        $product_name,
        $batch_number,
        $obtained_yield,
        $first_unit,
        $first_pack_size,
        $first_pack_size
    );
    $insert->execute();
    $insert->close();
}

// ✅ Redirect to list page with success message
header("Location: packaging_list.php?msg=Packaging+Updated+Successfully");
exit;
?>
