<?php
session_start();
require 'db_con.php';

// Get BOM ID
if (!isset($_GET['id'])) {
    die("Request ID missing");
}
$bom_id = intval($_GET['id']);

// Fetch BOM main info
$sql = "SELECT b.id, b.product_id, p.name as product_name, b.status, b.description, b.requested_by, b.bom_date
        FROM bill_of_materials b
        JOIN products p ON b.product_id = p.id
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$bom = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bom) {
    die("BOM request not found.");
}

// Fetch BOM items (chemicals)
$sql = "SELECT i.id, i.chemical_id, c.chemical_name, c.remaining_quantity, i.quantity_requested, 
               i.unit, i.unit_price, i.total_cost
        FROM bill_of_material_items i
        JOIN chemicals_in c ON i.chemical_id = c.id
        WHERE i.bom_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$chemicals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle update
// ✅ Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issued_by = $_POST['issued_by'];
    $remarks = $_POST['remarks'];
    $issue_date = $_POST['issue_date'];
    $status = $_POST['status'];

    // ✅ Update BOM with issuing details
    $stmt = $conn->prepare("UPDATE bill_of_materials 
                            SET issued_by = ?, remarks = ?, issue_date = ?, status = ? 
                            WHERE id = ?");
    $stmt->bind_param("ssssi", $issued_by, $remarks, $issue_date, $status, $bom_id);
    $stmt->execute();
    $stmt->close();

    // ✅ Deduct stock only if Approved
    if ($status === "Approved") {
        foreach ($chemicals as $chem) {
            $qty_requested = $chem['quantity_requested'];
            $chemical_id = $chem['chemical_id'];

            $stmt = $conn->prepare("UPDATE chemicals_in 
                                    SET remaining_quantity = remaining_quantity - ? 
                                    WHERE id = ? AND remaining_quantity >= ?");
            $stmt->bind_param("dii", $qty_requested, $chemical_id, $qty_requested);
            $stmt->execute();
            $stmt->close();
        }
    }

    $success = "Production request updated successfully.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Production Request</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <?php include 'navbar.php'; ?>

    <div class="p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Update Production Request</h1>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-lg rounded p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Product: <?= htmlspecialchars($bom['product_name']) ?></h2>

            <!-- Chemicals Table -->
            <table class="w-full border border-gray-300 mb-6">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="border px-3 py-2 text-left">Chemical</th>
                        <th class="border px-3 py-2 text-left">Qty Requested</th>
                        <th class="border px-3 py-2 text-left">Remaining Qty</th>
                        <th class="border px-3 py-2 text-left">Unit</th>
                        <th class="border px-3 py-2 text-left">Unit Price</th>
                        <th class="border px-3 py-2 text-left">Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                        $total_cost = 0;
                        foreach ($chemicals as $c): 
                        $total_cost += $c['total_cost'];
                    ?>
                        <tr>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['chemical_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['quantity_requested']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['remaining_quantity']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['unit']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['unit_price']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['total_cost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bg-gray-100 font-semibold">
                        <td colspan="5" class="text-right border px-3 py-2">Total Production Cost</td>
                        <td class="border px-3 py-2"><?= number_format($total_cost, 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Update Form -->
            <form method="POST" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-semibold">Issued By</label>
                    <input type="text" name="issued_by" required class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold">Date of Issuing</label>
                    <input type="date" name="issue_date" required class="w-full border rounded px-3 py-2">
                </div>
                <div class="col-span-2">
                    <label class="block text-gray-700 font-semibold">Remarks</label>
                    <textarea name="remarks" rows="3" class="w-full border rounded px-3 py-2"></textarea>
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold">Status</label>
                    <select name="status" required class="w-full border rounded px-3 py-2">
                        <option value="Pending" <?= $bom['status']=='Pending'?'selected':'' ?>>Pending</option>
                        <option value="Approved" <?= $bom['status']=='Approved'?'selected':'' ?>>Approved</option>
                        <option value="Rejected" <?= $bom['status']=='Rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-span-2 flex justify-between mt-4">
    <a href="production_requests.php" 
       class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded">
       Back
    </a>
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
        Update Request
    </button>
</div>

                </div>
            </form>
        </div>
    </div>
</body>
</html>
