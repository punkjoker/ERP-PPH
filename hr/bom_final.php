<?php 
session_start();
require 'db_con.php';

// Get BOM ID
if (!isset($_GET['id'])) {
    die("Request ID missing");
}
$bom_id = intval($_GET['id']);

// ✅ Fetch BOM main info
$sql = "SELECT b.id, b.product_id, p.name AS product_name, b.status, b.description, 
               b.requested_by, b.bom_date, b.issued_by, b.remarks, b.issue_date
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

// ✅ Fetch chemicals used in BOM
$sql = "SELECT 
            i.id, 
            i.chemical_id, 
            c.chemical_name, 
            i.quantity_requested, 
            i.unit, 
            i.unit_price, 
            i.total_cost,
            i.rm_lot_no
        FROM bill_of_material_items i
        JOIN chemicals_in c ON i.chemical_id = c.id
        WHERE i.bom_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$chemicals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ✅ Fetch packaging reconciliation for that BOM
$sql = "
SELECT pr.item_name, pr.units, pr.cost_per_unit, pr.total_cost
FROM packaging_reconciliation pr
JOIN qc_inspections qi ON qi.id = pr.qc_inspection_id
JOIN production_runs r ON r.id = qi.production_run_id
WHERE r.request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$packaging = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BOM Final Report</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        @media print {
            body * { visibility: hidden; }
            #report, #report * { visibility: visible; }
            #report { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>

    <div class="p-6 ml-64">
        <div id="report" class="bg-white shadow-lg rounded-lg p-8 max-w-4xl mx-auto">

            <!-- Header -->
            <div class="border-b pb-4 mb-6 text-center">
                <h1 class="text-3xl font-bold text-gray-800">Final Bill of Materials Report</h1>
                <p class="text-gray-600">Production Department</p>
            </div>

            <!-- Product Info -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Product Details</h2>
                <p><span class="font-semibold">Product Name:</span> <?= htmlspecialchars($bom['product_name']) ?></p>
                <p><span class="font-semibold">Status:</span> <?= htmlspecialchars($bom['status']) ?></p>
            </div>

            <!-- Chemicals Table -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Raw Materials (Chemicals)</h2>
                <table class="w-full border border-gray-300 text-sm">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="border px-3 py-2 text-left">Chemical</th>
                            <th class="border px-3 py-2 text-left">RM LOT NO</th>
                            <th class="border px-3 py-2 text-left">Qty Requested</th>
                            <th class="border px-3 py-2 text-left">Unit</th>
                            <th class="border px-3 py-2 text-left">Unit Price</th>
                            <th class="border px-3 py-2 text-left">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $chemical_total = 0;
                        foreach ($chemicals as $c): 
                            $chemical_total += $c['total_cost'];
                        ?>
                        <tr>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['chemical_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['rm_lot_no']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['quantity_requested']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($c['unit']) ?></td>
                            <td class="border px-3 py-2"><?= number_format($c['unit_price'], 2) ?></td>
                            <td class="border px-3 py-2"><?= number_format($c['total_cost'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-100 font-semibold">
                            <td colspan="4" class="text-right border px-3 py-2">Total Chemicals Cost</td>
                            <td class="border px-3 py-2"><?= number_format($chemical_total, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Packaging Table -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Packaging Materials</h2>
                <table class="w-full border border-gray-300 text-sm">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="border px-3 py-2 text-left">Item Name</th>
                            <th class="border px-3 py-2 text-left">Units</th>
                            <th class="border px-3 py-2 text-left">Cost Per Unit</th>
                            <th class="border px-3 py-2 text-left">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $packaging_total = 0;
                        foreach ($packaging as $p): 
                            $packaging_total += $p['total_cost'];
                        ?>
                        <tr>
                            <td class="border px-3 py-2"><?= htmlspecialchars($p['item_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($p['units']) ?></td>
                            <td class="border px-3 py-2"><?= number_format($p['cost_per_unit'], 2) ?></td>
                            <td class="border px-3 py-2"><?= number_format($p['total_cost'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-100 font-semibold">
                            <td colspan="3" class="text-right border px-3 py-2">Total Packaging Cost</td>
                            <td class="border px-3 py-2"><?= number_format($packaging_total, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Grand Total -->
            <?php $grand_total = $chemical_total + $packaging_total; ?>
            <div class="text-right mb-6">
                <p class="text-xl font-bold text-gray-800">
                    Grand Total Production Cost: 
                    <span class="text-blue-700"><?= number_format($grand_total, 2) ?></span>
                </p>
            </div>

            <!-- Request Info -->
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 mb-2">Request Information</h2>
                    <p><span class="font-semibold">Requested By:</span> <?= htmlspecialchars($bom['requested_by']) ?></p>
                    <p><span class="font-semibold">Description:</span> <?= htmlspecialchars($bom['description']) ?></p>
                    <p><span class="font-semibold">Date:</span> <?= htmlspecialchars($bom['bom_date']) ?></p>
                    <p class="italic mt-4">Signature: ____________________________</p>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 mb-2">Issuing Information</h2>
                    <p><span class="font-semibold">Issued By:</span> <?= htmlspecialchars($bom['issued_by']) ?></p>
                    <p><span class="font-semibold">Remarks:</span> <?= htmlspecialchars($bom['remarks']) ?></p>
                    <p><span class="font-semibold">Date of Issue:</span> <?= htmlspecialchars($bom['issue_date']) ?></p>
                    <p class="italic mt-4">Signature: ____________________________</p>
                </div>
            </div>
        </div>

        <!-- Buttons -->
        <div class="flex justify-between mt-6 max-w-4xl mx-auto no-print">
            <a href="view_finished_products.php" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded">
               Back
            </a>
            <div>
                <button onclick="printReport()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded mr-2">
                    Print
                </button>
                <a href="download_bom_pdf.php?id=<?= $bom_id ?>" 
                   class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded">
                   Save as PDF
                </a>
            </div>
        </div>
    </div>

<script>
function printReport() {
    window.print();
}
</script>

</body>
</html>
