<?php
session_start();
require 'db_con.php';

// Get BOM ID
if (!isset($_GET['id'])) {
    die("Request ID missing");
}
$bom_id = intval($_GET['id']);

// Fetch BOM main info
$sql = "SELECT b.id, b.product_id, p.name as product_name, b.status, b.description, b.requested_by, b.bom_date, 
               b.issued_by, b.remarks, b.issue_date
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View BOM Report</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <!-- Print CSS: Only print #report -->
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #report, #report * {
                visibility: visible;
            }
            #report {
                margin: 0;
                padding: 0;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body class="bg-gray-100">

    <?php include 'navbar.php'; ?>

    <div class="p-6 ml-64">
        <div id="report" class="bg-white shadow-lg rounded-lg p-8 max-w-4xl mx-auto">
            
            <!-- Header -->
            <div class="border-b pb-4 mb-6">
                <h1 class="text-3xl font-bold text-center text-gray-800">Bill of Materials Report</h1>
                <p class="text-center text-gray-600">Production Department</p>
            </div>

            <!-- Product Info -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Product Details</h2>
                <p><span class="font-semibold">Product Name:</span> <?= htmlspecialchars($bom['product_name']) ?></p>
                <p><span class="font-semibold">Status:</span> <?= htmlspecialchars($bom['status']) ?></p>
            </div>

            <!-- Chemicals Table -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Chemicals & Costs</h2>
                <table class="w-full border border-gray-300">
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
                            $total_cost = 0;
                            foreach ($chemicals as $c): 
                            $total_cost += $c['total_cost'];
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
                            <td colspan="4" class="text-right border px-3 py-2">Total Production Cost</td>
                            <td class="border px-3 py-2"><?= number_format($total_cost, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
                
            </div>

            <!-- Requested By -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Request Information</h2>
                <p><span class="font-semibold">Requested By:</span> <?= htmlspecialchars($bom['requested_by']) ?></p>
                <p><span class="font-semibold">Description:</span> <?= htmlspecialchars($bom['description']) ?></p>
                <p><span class="font-semibold">Date:</span> <?= htmlspecialchars($bom['bom_date']) ?></p>
                <div class="mt-4">
                    <p class="italic">Signature: ____________________________</p>
                </div>
            </div>

            <!-- Issued By -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Issuing Information</h2>
                <p><span class="font-semibold">Issued By:</span> <?= htmlspecialchars($bom['issued_by']) ?></p>
                <p><span class="font-semibold">Remarks:</span> <?= htmlspecialchars($bom['remarks']) ?></p>
                <p><span class="font-semibold">Date of Issue:</span> <?= htmlspecialchars($bom['issue_date']) ?></p>
                <div class="mt-4">
                    <p class="italic">Signature: ____________________________</p>
                </div>
            </div>
        </div>

        <!-- Buttons -->
        <div class="flex justify-between mt-6 max-w-4xl mx-auto no-print">
            <a href="production_requests.php" 
               class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded">
               Back
            </a>
            <div>
                <button onclick="printReport()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded mr-2">
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

function savePDF() {
    const element = document.getElementById('report');
    html2pdf().from(element).save('BOM_Report.pdf');
}
</script>

</body>
</html>
