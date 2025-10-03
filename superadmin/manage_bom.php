<?php
session_start();
require 'db_con.php';

// Fetch products
$products = $conn->query("SELECT * FROM products ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch chemicals in FIFO order
$chemicals = $conn->query("SELECT id, chemical_name, remaining_quantity, unit_price FROM chemicals_in ORDER BY created_at ASC")->fetch_all(MYSQLI_ASSOC);

// Handle BOM submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $bom_date = $_POST['bom_date'] ?? date("Y-m-d");
    $requested_by = $_POST['requested_by'] ?? '';
    $description = $_POST['description'] ?? '';
    $chemicals_selected = $_POST['chemicals'] ?? [];

    if ($product_id && !empty($chemicals_selected)) {
        // Insert into main BOM
        $stmt = $conn->prepare("INSERT INTO bill_of_materials 
            (product_id, bom_date, requested_by, description, status) 
            VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("isss", $product_id, $bom_date, $requested_by, $description);
        $stmt->execute();
        $bom_id = $stmt->insert_id;
        $stmt->close();

        // Insert BOM items
        foreach ($chemicals_selected as $chem) {
            if (empty($chem['chemical_id']) || empty($chem['quantity_requested'])) continue;

            $chemical_id = intval($chem['chemical_id']);
            $qty_requested = floatval($chem['quantity_requested']);
            $qty_unit = $chem['unit'] ?? 'kg';
            $unit_price = floatval($chem['unit_price']);
            $total_cost = floatval($chem['total_cost']);

            $stmt = $conn->prepare("INSERT INTO bill_of_material_items 
                (bom_id, chemical_id, quantity_requested, unit, unit_price, total_cost) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iidssd", $bom_id, $chemical_id, $qty_requested, $qty_unit, $unit_price, $total_cost);
            $stmt->execute();
            $stmt->close();
        }

        $success = "BOM submitted successfully and is now pending approval.";
    } else {
        $error = "Please select a product and add at least one chemical.";
    }
}


// Filtering
$where = "";
$params = [];
if (isset($_GET['from']) && isset($_GET['to']) && $_GET['from'] && $_GET['to']) {
    $from = $_GET['from'];
    $to = $_GET['to'];
    $where = "WHERE b.created_at BETWEEN ? AND ?";
    $params = [$from . " 00:00:00", $to . " 23:59:59"];
}

$sql = "SELECT 
            b.id AS bom_id,
            b.bom_date,
            b.created_at,
            b.status,
            b.requested_by,
            b.description,
            p.name AS product_name
        FROM bill_of_materials b
        JOIN products p ON b.product_id = p.id
        $where
        ORDER BY b.created_at DESC";



$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param("ss", ...$params);
}
$stmt->execute();
$boms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage BOM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        function updateTotal(row) {
            let qty = parseFloat(row.querySelector('.qty').value) || 0;
            let price = parseFloat(row.querySelector('.unit-price').value) || 0;
            row.querySelector('.total-cost').value = (qty * price).toFixed(2);
        }

     let chemicalIndex = 1;

function addChemicalRow() {
    const container = document.getElementById('chemicals-container');
    const row = document.querySelector('.chemical-row').cloneNode(true);

    // Clear values
    row.querySelectorAll('input').forEach(i => i.value = '');
    row.querySelectorAll('select').forEach(s => s.selectedIndex = 0);

    // Update names
    row.querySelectorAll('select, input').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, "[" + chemicalIndex + "]");
    });

    container.appendChild(row);
    chemicalIndex++;
}

        function fillChemicalData(select) {
            let option = select.options[select.selectedIndex];
            let row = select.closest('.chemical-row');
            row.querySelector('.remaining').value = option.getAttribute('data-remaining');
            row.querySelector('.unit-price').value = option.getAttribute('data-price');
            updateTotal(row);
        }
    </script>
</head>
<body class="bg-gray-100">

    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Manage Bill of Materials (BOM)</h1>

        <!-- Messages -->
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
        <?php elseif (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- BOM Form -->
        <div class="bg-white shadow-lg rounded p-6 mb-6">
            <form method="POST" id="bomForm" class="space-y-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-1">Select Product</label>
                    <select name="product_id" required class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-1">BOM Date</label>
                    <input type="date" name="bom_date" required class="border rounded px-3 py-2 w-full">
                </div>
<div>
    <label class="block text-gray-700 font-semibold mb-1">Requested By</label>
    <input type="text" name="requested_by" required 
        class="border rounded px-3 py-2 w-full" placeholder="Enter your name">
</div>

<div>
    <label class="block text-gray-700 font-semibold mb-1">Description</label>
    <textarea name="description" rows="3" required 
        class="border rounded px-3 py-2 w-full" placeholder="e.g. Request for KWAL product 2kgs"></textarea>
</div>

                <!-- Chemicals Section -->
                <div id="chemicals-container" class="space-y-4">
                    <div class="chemical-row grid grid-cols-6 gap-3 items-end">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold">Chemical</label>
                            <select name="chemicals[0][chemical_id]" onchange="fillChemicalData(this)" class="border rounded px-2 py-1 w-full">
                                <option value="">-- Select Chemical --</option>
                                <?php foreach ($chemicals as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-remaining="<?= $c['remaining_quantity'] ?>" data-price="<?= $c['unit_price'] ?>">
                                        <?= htmlspecialchars($c['chemical_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold">Remaining Qty</label>
                            <input type="text" name="chemicals[0][remaining]" readonly class="remaining border rounded px-2 py-1 bg-gray-100 w-full">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold">Unit Price</label>
                            <input type="text" name="chemicals[0][unit_price]" class="unit-price border rounded px-2 py-1 bg-gray-100 w-full" readonly>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold">Qty Requested</label>
                            <input type="number" name="chemicals[0][quantity_requested]" class="qty border rounded px-2 py-1 w-full" oninput="updateTotal(this.closest('.chemical-row'))">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold">Unit</label>
                            <select name="chemicals[0][unit]" class="border rounded px-2 py-1 w-full">
                                <option value="kg">Kg</option>
                                <option value="litre">Litre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold">Total Cost</label>
                            <input type="text" name="chemicals[0][total_cost]" class="total-cost border rounded px-2 py-1 bg-gray-100 w-full" readonly>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="addChemicalRow()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">+ Add Another Chemical</button>

                <div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Submit BOM</button>
                </div>
            </form>
        </div>

        <!-- Filter Form -->
        <div class="bg-white shadow-md rounded p-4 mb-6">
            <form method="GET" class="flex space-x-4 items-end">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold">From</label>
                    <input type="date" name="from" class="border rounded px-2 py-1" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold">To</label>
                    <input type="date" name="to" class="border rounded px-2 py-1" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
                </div>
                <div>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Filter</button>
                </div>
            </form>
        </div>

        <!-- BOM List -->
        <div class="bg-white shadow-lg rounded p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Bill of Materials List</h2>
            <table class="w-full border border-gray-300 rounded">
                <thead class="bg-gray-200">
    <tr>
        <th class="border px-3 py-2 text-left">Date</th>
        <th class="border px-3 py-2 text-left">Product</th>
        <th class="border px-3 py-2 text-left">Requested By</th>
        <th class="border px-3 py-2 text-left">Description</th>
        <th class="border px-3 py-2 text-left">Status</th>
        <th class="border px-3 py-2 text-left">Action</th>
    </tr>
</thead>

                <tbody>
                   <?php if (!empty($boms)): ?>
    <?php foreach ($boms as $b): ?>
        <tr>
            <td class="border px-3 py-2"><?= htmlspecialchars($b['bom_date']) ?></td>
            <td class="border px-3 py-2"><?= htmlspecialchars($b['product_name']) ?></td>
            <td class="border px-3 py-2"><?= htmlspecialchars($b['requested_by']) ?></td>
            <td class="border px-3 py-2"><?= htmlspecialchars($b['description']) ?></td>
            <td class="border px-3 py-2 font-semibold <?= $b['status']=='Pending'?'text-yellow-600':($b['status']=='Approved'?'text-green-600':'text-red-600') ?>">
                <?= htmlspecialchars($b['status']) ?>
            </td>
            <td class="border px-3 py-2">
                <a href="view_bom.php?id=<?= $b['bom_id'] ?>" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">View</a>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center py-3 text-gray-500">No BOMs found</td></tr>
<?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
