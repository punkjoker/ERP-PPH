<?php
session_start();
require 'db_con.php';

if (!isset($_GET['id'])) {
    die("No chemical selected.");
}
$id = intval($_GET['id']);

// Fetch chemical details
$stmt = $conn->prepare("SELECT * FROM chemicals_in WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$chemical = $stmt->get_result()->fetch_assoc();

// Fetch inspection details
$stmt2 = $conn->prepare("SELECT * FROM inspected_chemicals_in WHERE chemical_id = ?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$inspection = $stmt2->get_result()->fetch_assoc();

// Decode tests if available
$tests = [];
if ($inspection && !empty($inspection['tests'])) {
    $tests = json_decode($inspection['tests'], true);
    if (!is_array($tests)) $tests = [];
}

// PDF generation
// PDF generation
if (isset($_GET['download_pdf'])) {
    require('fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'Chemical Analysis Report',0,1,'C');
    $pdf->Ln(5);

    // --- Chemical Details Table ---
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'Chemical Details',0,1);
    $pdf->SetFont('Arial','',11);

    foreach ($chemical as $key => $val) {
        $pdf->Cell(60,8,ucwords(str_replace("_"," ",$key)),1,0,'L');
        $pdf->Cell(120,8,$val,1,1,'L');
    }
    $pdf->Ln(5);

    // --- Inspection Details Table ---
    if ($inspection) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'Inspection Details',0,1);
        $pdf->SetFont('Arial','',11);

        $pdf->Cell(60,8,'RM Lot No',1,0);
        $pdf->Cell(120,8,$inspection['rm_lot_no'],1,1);

        $pdf->Cell(60,8,'Approved Quantity',1,0);
        $pdf->Cell(120,8,$inspection['approved_quantity'],1,1);

        $pdf->Cell(60,8,'Approved By',1,0);
        $pdf->Cell(120,8,$inspection['approved_by'],1,1);

        $pdf->Cell(60,8,'Approved Date',1,0);
        $pdf->Cell(120,8,$inspection['approved_date'],1,1);

        $pdf->Ln(5);
    }

    // --- QC Tests Table ---
    if ($tests) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'Quality Control Tests',0,1);

        // Table headers
        $pdf->SetFont('Arial','B',11);
        $pdf->Cell(60,8,'Test Type',1,0,'C');
        $pdf->Cell(65,8,'Specification',1,0,'C');
        $pdf->Cell(55,8,'Result',1,1,'C');

        // Table rows
        $pdf->SetFont('Arial','',11);
        foreach ($tests as $t) {
            $pdf->Cell(60,8,$t['type'],1,0);
            $pdf->Cell(65,8,$t['specification'],1,0);
            $pdf->Cell(55,8,$t['result'],1,1);
        }
        $pdf->Ln(5);
    }

    // Signature line
    $pdf->Ln(20);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,10,'Signature: ___________________________',0,1);

    $pdf->Output('D', 'analysis_report.pdf');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Chemical Analysis View</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Chemical Analysis View</h1>

        <div id="printSection" class="space-y-6">

            <!-- Chemical Details -->
            <div class="bg-white shadow-lg rounded p-4">
                <h2 class="text-xl font-semibold mb-3 text-blue-700">Chemical Details</h2>
                <table class="w-full border border-gray-300 rounded">
                    <?php foreach ($chemical as $key => $val): ?>
                        <tr>
                            <td class="border px-3 py-2 font-semibold bg-gray-100"><?= ucwords(str_replace("_"," ",$key)) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($val) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Inspection Details -->
            <?php if ($inspection): ?>
                <div class="bg-white shadow-lg rounded p-4">
                    <h2 class="text-xl font-semibold mb-3 text-green-700">Inspection Details</h2>
                    <table class="w-full border border-gray-300 rounded">
                        <tr><td class="border px-3 py-2 font-semibold bg-gray-100">RM Lot No</td><td class="border px-3 py-2"><?= htmlspecialchars($inspection['rm_lot_no']) ?></td></tr>
                        <tr><td class="border px-3 py-2 font-semibold bg-gray-100">Approved Quantity</td><td class="border px-3 py-2"><?= htmlspecialchars($inspection['approved_quantity']) ?></td></tr>
                        <tr><td class="border px-3 py-2 font-semibold bg-gray-100">Approved By</td><td class="border px-3 py-2"><?= htmlspecialchars($inspection['approved_by']) ?></td></tr>
                        <tr><td class="border px-3 py-2 font-semibold bg-gray-100">Approved Date</td><td class="border px-3 py-2"><?= htmlspecialchars($inspection['approved_date']) ?></td></tr>
                    </table>
                </div>
            <?php endif; ?>

            <!-- QC Tests -->
            <?php if ($tests): ?>
                <div class="bg-white shadow-lg rounded p-4">
                    <h2 class="text-xl font-semibold mb-3 text-purple-700">Quality Control Tests</h2>
                    <table class="w-full border border-gray-300 rounded">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="border px-3 py-2 text-left">Test Type</th>
                                <th class="border px-3 py-2 text-left">Specification</th>
                                <th class="border px-3 py-2 text-left">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tests as $t): ?>
                                <tr>
                                    <td class="border px-3 py-2"><?= htmlspecialchars($t['type']) ?></td>
                                    <td class="border px-3 py-2"><?= htmlspecialchars($t['specification']) ?></td>
                                    <td class="border px-3 py-2"><?= htmlspecialchars($t['result']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-red-500">No QC tests found for this chemical.</p>
            <?php endif; ?>

            <!-- Signature Line -->
            <div class="mt-10">
                <p class="text-lg font-semibold">Signature: ___________________________</p>
            </div>

        </div>

        <!-- Buttons -->
        <div class="mt-6 flex space-x-3">
            <button onclick="printSection()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Print</button>
            <a href="view_analysis.php?id=<?= $id ?>&download_pdf=1" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Save as PDF</a>
            <a href="inspect_raw_materials.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">Back</a>
        </div>
    </div>

<script>
function printSection() {
    var printContent = document.getElementById('printSection').innerHTML;
    var WinPrint = window.open('', '', 'width=900,height=650');
    WinPrint.document.write('<html><head><title>Print</title>');
    WinPrint.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss/dist/tailwind.min.css">');
    WinPrint.document.write('</head><body class="p-6">');
    WinPrint.document.write(printContent);
    WinPrint.document.write('</body></html>');
    WinPrint.document.close();
    WinPrint.focus();
    WinPrint.print();
    WinPrint.close();
}
</script>
</body>
</html>
