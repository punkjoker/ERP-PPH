<?php
include 'db_con.php';

$bom_id = $_GET['id'] ?? 0;
$production = null;

// ✅ Step 1: Ensure production_run row exists
$bom_query = "
    SELECT p.name AS product_name, bom.requested_by, bom.description
    FROM bill_of_materials bom
    JOIN products p ON bom.product_id = p.id
    WHERE bom.id = $bom_id
";
$bom_result = $conn->query($bom_query);
if ($bom_result && $bom_result->num_rows > 0) {
    $bom_data = $bom_result->fetch_assoc();
    $product_name = $conn->real_escape_string($bom_data['product_name']);
    $requested_by = $conn->real_escape_string($bom_data['requested_by']);
    $description = $conn->real_escape_string($bom_data['description']);

    $conn->query("
        INSERT IGNORE INTO production_runs (request_id, product_name, requested_by, description, status)
        VALUES ($bom_id, '$product_name', '$requested_by', '$description', 'In production')
    ");
}

// ✅ Step 2: Fetch production run info
$sql = "SELECT * FROM production_runs WHERE request_id = $bom_id LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $production = $result->fetch_assoc();
} else {
    die("No production run found for BOM ID: $bom_id");
}

// ✅ Step 3: Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_production'])) {
    $expected_yield = $_POST['expected_yield'] ?? '';
    $obtained_yield = $_POST['obtained_yield'] ?? '';
    $status = $_POST['status'] ?? 'In production';
    $completed_at = !empty($_POST['completed_at']) ? $_POST['completed_at'] : null; // ✅ NEW

    // ✅ Update production_runs
    $update = $conn->prepare("
        UPDATE production_runs
        SET expected_yield = ?, obtained_yield = ?, status = ?, completed_at = ?, updated_at = NOW()
        WHERE request_id = ?
    ");
    $update->bind_param("ssssi", $expected_yield, $obtained_yield, $status, $completed_at, $bom_id);
    $update->execute();

    // ✅ Insert new procedures
    if (!empty($_POST['procedures'])) {
        foreach ($_POST['procedures'] as $proc) {
            $name = trim($proc['name']);
            $done_by = trim($proc['done_by']);
            $checked_by = trim($proc['checked_by']);
            if ($name === '' && $done_by === '' && $checked_by === '') continue;

            $name = $conn->real_escape_string($name);
            $done_by = $conn->real_escape_string($done_by);
            $checked_by = $conn->real_escape_string($checked_by);

            $check = $conn->query("
                SELECT id FROM production_procedures
                WHERE production_run_id = {$production['id']}
                AND procedure_name = '$name'
                AND done_by = '$done_by'
                AND checked_by = '$checked_by'
                LIMIT 1
            ");
            if ($check->num_rows == 0) {
                $conn->query("
                    INSERT INTO production_procedures (production_run_id, procedure_name, done_by, checked_by)
                    VALUES ({$production['id']}, '$name', '$done_by', '$checked_by')
                ");
            }
        }
    }

    echo "<script>alert('Production & Procedures saved successfully!');window.location='record_production_run.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Production</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        let procIndex = 1;
        function addProcedureRow() {
            const container = document.getElementById("procedureContainer");
            const div = document.createElement("div");
            div.classList = "grid grid-cols-3 gap-4 bg-gray-50 p-3 rounded-lg border mb-2";
            div.innerHTML = `
                <input type="text" name="procedures[${procIndex}][name]" placeholder="Procedure Name"
                    class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                <input type="text" name="procedures[${procIndex}][done_by]" placeholder="Done By"
                    class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                <input type="text" name="procedures[${procIndex}][checked_by]" placeholder="Checked By"
                    class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
            `;
            container.appendChild(div);
            procIndex++;
        }
    </script>
</head>
<body class="bg-gray-100 flex font-sans">

    <?php include 'navbar.php'; ?>

    <div class="flex-1 p-8 ml-64">
        <!-- Header -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6 flex items-center border-b-4 border-blue-600">
            <img src="images/lynn_logo.png" alt="Logo" class="h-16 mr-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">BATCH MANUFACTURING (QF-27)</h1>
                <p class="text-sm text-gray-600">PRODUCT NAME: 
                    <span class="font-semibold"><?= htmlspecialchars($production['product_name']) ?></span></p>
            </div>
        </div>

        <!-- Production Form -->
        <form method="POST" class="space-y-8 bg-white shadow-lg p-8 rounded-lg border">
<?php
// ✅ Fetch BOM items
$sql = "SELECT 
            i.chemical_name, 
            i.chemical_code, 
            i.rm_lot_no, 
            i.po_number, 
            i.quantity_requested, 
            i.unit, 
            i.unit_price, 
            i.total_cost
        FROM bill_of_material_items i
        WHERE i.bom_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$chemicals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ✅ Calculate total (expected yield)
$total_quantity_requested = 0;
$total_cost = 0;
foreach ($chemicals as $c) {
    $total_quantity_requested += $c['quantity_requested'];
    $total_cost += $c['total_cost'];
}

// ✅ Autofill expected yield in production record
if (empty($production['expected_yield'])) {
    $production['expected_yield'] = $total_quantity_requested;
}
?>

<!-- ✅ Bill of Materials Section -->
<section class="mb-8">
    <h3 class="text-lg font-semibold text-blue-700 border-b pb-2 mb-4">Bill of Materials</h3>
    <div class="overflow-x-auto">
        <table class="w-full border border-gray-300 text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-3 py-2 text-left">Chemical</th>
                    <th class="border px-3 py-2 text-left">Chemical Code</th>
                    <th class="border px-3 py-2 text-left">RM LOT NO</th>
                    <th class="border px-3 py-2 text-left">PO NO</th>
                    <th class="border px-3 py-2 text-left">Qty Requested</th>
                    <th class="border px-3 py-2 text-left">Unit</th>
                    <th class="border px-3 py-2 text-left">Unit Price</th>
                    <th class="border px-3 py-2 text-left">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chemicals as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['chemical_name']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['chemical_code']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['rm_lot_no']) ?></td>
                    <td class="border px-3 py-2">PO#<?= htmlspecialchars($c['po_number']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['quantity_requested']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['unit']) ?></td>
                    <td class="border px-3 py-2"><?= number_format($c['unit_price'], 2) ?></td>
                    <td class="border px-3 py-2"><?= number_format($c['total_cost'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-gray-100 font-semibold">
                    <td colspan="7" class="text-right border px-3 py-2">Total Production Cost</td>
                    <td class="border px-3 py-2"><?= number_format($total_cost, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

            <!-- Manufacturing Details -->
            <section>
                <h3 class="text-lg font-semibold text-blue-700 border-b pb-2 mb-4">Manufacturing Details</h3>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-1">Expected Yield (Kg/L)</label>
                        <input type="text" name="expected_yield" value="<?= htmlspecialchars($production['expected_yield']) ?>" 
                               class="border p-3 rounded w-full focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Obtained Yield (Kg/L)</label>
                        <input type="text" name="obtained_yield" value="<?= htmlspecialchars($production['obtained_yield']) ?>" 
                               class="border p-3 rounded w-full focus:ring-2 focus:ring-blue-500">
                    </div>
                    <!-- ✅ NEW Completed Date -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Completed Date & Time</label>
                        <input type="datetime-local" name="completed_at" 
                               value="<?= htmlspecialchars($production['completed_at']) ?>" 
                               class="border p-3 rounded w-full focus:ring-2 focus:ring-green-500">
                    </div>
                </div>
            </section>

            <!-- Procedure Section -->
            <section>
                <h3 class="text-lg font-semibold text-blue-700 border-b pb-2 mb-4">Procedure</h3>
                <div id="procedureContainer" class="space-y-3">
                    <div class="grid grid-cols-3 gap-4 bg-gray-50 p-3 rounded-lg border">
                        <input type="text" name="procedures[0][name]" placeholder="Procedure Name" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                        <input type="text" name="procedures[0][done_by]" placeholder="Done By" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                        <input type="text" name="procedures[0][checked_by]" placeholder="Checked By" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <button type="button" onclick="addProcedureRow()" 
                        class="mt-4 bg-blue-600 text-white px-5 py-2 rounded shadow hover:bg-blue-700 transition">
                    + Add Procedure
                </button>
            </section>

            <!-- Status Section -->
            <section>
                <h3 class="text-lg font-semibold text-blue-700 border-b pb-2 mb-4">Update Status</h3>
                <select name="status" class="border p-3 rounded w-full focus:ring-2 focus:ring-green-500">
                    <option value="In production" <?= ($production['status'] == 'In production') ? 'selected' : '' ?>>In production</option>
                    <option value="Completed" <?= ($production['status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
                </select>
            </section>

            <div class="flex justify-end">
                <button type="submit" name="update_production" 
                        class="bg-green-600 text-white px-8 py-3 rounded-lg shadow hover:bg-green-700 transition">
                    ✅ Update Production
                </button>
            </div>
        </form>
    </div>
</body>
</html>
