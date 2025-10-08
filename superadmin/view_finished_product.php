<?php
include 'db_con.php';

$bom_id = $_GET['id'] ?? 0;

// ✅ Fetch production + product + QC details
$sql = "SELECT pr.*, p.name AS product_name, bom.requested_by, bom.description, bom.bom_date, qc.created_at AS qc_date
        FROM production_runs pr
        JOIN bill_of_materials bom ON pr.request_id = bom.id
        JOIN products p ON bom.product_id = p.id
        LEFT JOIN qc_inspections qc ON qc.production_run_id = pr.id
        WHERE pr.request_id = $bom_id LIMIT 1";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) die("No record found for this product.");
$production = $result->fetch_assoc();

// ✅ Fetch production procedures
$procedures = [];
$proc_result = $conn->query("SELECT * FROM production_procedures WHERE production_run_id = {$production['id']} ORDER BY created_at ASC");
if ($proc_result && $proc_result->num_rows > 0) {
  while ($row = $proc_result->fetch_assoc()) {
    $procedures[] = $row;
  }
}

// ✅ Fetch QC inspections for this production
$qc_tests = [];
$qc_result = $conn->query("SELECT * FROM qc_inspections WHERE production_run_id = {$production['id']}");
if ($qc_result && $qc_result->num_rows > 0) {
  while ($row = $qc_result->fetch_assoc()) {
    $qc_tests[] = $row;
  }
}

// ✅ Fetch packaging reconciliation (linked to qc_inspections)
$packs = [];
$pack_result = $conn->query("
  SELECT pr.*, qc.test_name 
  FROM packaging_reconciliation pr
  JOIN qc_inspections qc ON pr.qc_inspection_id = qc.id
  WHERE qc.production_run_id = {$production['id']}
");
if ($pack_result && $pack_result->num_rows > 0) {
  while ($row = $pack_result->fetch_assoc()) {
    $packs[] = $row;
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Finished Product - <?= htmlspecialchars($production['product_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 font-sans">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-6">
    <!-- ✅ Header -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6 flex items-center border-b-4 border-green-600">
      <img src="images/lynn_logo.png" alt="Logo" class="h-16 mr-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">FINISHED PRODUCT DETAILS</h1>
        <p class="text-sm text-gray-600">PRODUCT NAME:
          <span class="font-semibold text-blue-700"><?= htmlspecialchars($production['product_name']) ?></span>
        </p>
        <p class="text-sm text-gray-600">REQUESTED BY:
          <span class="font-semibold"><?= htmlspecialchars($production['requested_by']) ?></span>
        </p>
        <p class="text-sm text-gray-600">STATUS:
          <span class="font-semibold <?= $production['status'] == 'Completed' ? 'text-green-600' : 'text-yellow-600' ?>">
            <?= htmlspecialchars($production['status']) ?>
          </span>
        </p>
      </div>
    </div>

    <!-- ✅ Batch Info -->
    <div class="bg-white shadow-lg rounded-lg p-6 border mb-8">
      <h2 class="text-xl font-semibold text-blue-700 mb-4 border-b pb-2">Batch Details</h2>
      <div class="grid grid-cols-2 gap-6 text-sm">
        <p><span class="font-medium text-gray-700">Batch Date:</span> <?= htmlspecialchars($production['bom_date']) ?></p>
        <p><span class="font-medium text-gray-700">Expected Yield:</span> <?= htmlspecialchars($production['expected_yield']) ?> Kg/L</p>
        <p><span class="font-medium text-gray-700">Obtained Yield:</span> <?= htmlspecialchars($production['obtained_yield']) ?> Kg/L</p>
        <p><span class="font-medium text-gray-700">Description:</span> <?= htmlspecialchars($production['description']) ?></p>
        <p><span class="font-medium text-gray-700">Completed At:</span>
          <?= !empty($production['completed_at']) ? date('d M Y, h:i A', strtotime($production['completed_at'])) : '—' ?>
        </p>
        <p><span class="font-medium text-gray-700">QC Date:</span>
          <?= !empty($production['qc_date']) ? date('d M Y, h:i A', strtotime($production['qc_date'])) : 'Not yet inspected' ?>
        </p>
      </div>
    </div>

    <!-- ✅ Procedures -->
    <div class="bg-white shadow-lg rounded-lg p-6 border mb-8">
      <h2 class="text-xl font-semibold text-green-700 mb-4 border-b pb-2">Production Procedures</h2>
      <?php if (count($procedures) > 0): ?>
        <table class="min-w-full border border-gray-300 text-sm">
          <thead class="bg-green-100 text-gray-700">
            <tr>
              <th class="px-3 py-2 text-left">#</th>
              <th class="px-3 py-2 text-left">Procedure Name</th>
              <th class="px-3 py-2 text-left">Done By</th>
              <th class="px-3 py-2 text-left">Checked By</th>
              <th class="px-3 py-2 text-left">Date Recorded</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php foreach ($procedures as $i => $proc): ?>
              <tr>
                <td class="px-3 py-2"><?= $i + 1 ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($proc['procedure_name']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($proc['done_by']) ?></td>
                <td class="px-3 py-2"><?= htmlspecialchars($proc['checked_by']) ?></td>
                <td class="px-3 py-2"><?= date('d M Y, h:i A', strtotime($proc['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500 italic">No procedures recorded.</p>
      <?php endif; ?>
    </div>

    <!-- ✅ QC Inspections -->
    <div class="bg-white shadow-lg rounded-lg p-6 border mb-8">
      <h2 class="text-lg font-bold mb-4 text-blue-700">Quality Control Inspections</h2>
      <?php if (count($qc_tests) > 0): ?>
        <table class="min-w-full border text-sm">
          <thead class="bg-blue-100">
            <tr>
              <th class="border px-3 py-1">#</th>
              <th class="border px-3 py-1">Test Name</th>
              <th class="border px-3 py-1">Specification</th>
              <th class="border px-3 py-1">Results</th>
              <th class="border px-3 py-1">QC Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($qc_tests as $i => $qc): ?>
              <tr>
                <td class="border px-3 py-1"><?= $i + 1 ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($qc['test_name']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($qc['specification']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($qc['procedure_done']) ?></td>
                <td class="border px-3 py-1 font-semibold <?= $qc['qc_status'] == 'Approved Product' ? 'text-green-600' : 'text-red-600' ?>">
                  <?= htmlspecialchars($qc['qc_status']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500 italic">No QC inspections recorded yet.</p>
      <?php endif; ?>
    </div>

    <!-- ✅ Packaging Reconciliation -->
    <div class="bg-white shadow-lg rounded-lg p-6 border mb-8">
      <h2 class="text-lg font-bold mb-4 text-green-700">Packaging Reconciliation</h2>
      <?php if (count($packs) > 0): ?>
        <table class="min-w-full border text-sm">
          <thead class="bg-green-100">
            <tr>
              <th class="border px-3 py-1">Item</th>
              <th class="border px-3 py-1">Issued</th>
              <th class="border px-3 py-1">Used</th>
              <th class="border px-3 py-1">Wasted</th>
              <th class="border px-3 py-1">Balance</th>
              <th class="border px-3 py-1">Qty Achieved</th>
              <th class="border px-3 py-1">% Yield</th>
              <th class="border px-3 py-1">Units</th>
              <th class="border px-3 py-1">Cost/Unit</th>
              <th class="border px-3 py-1">Total Cost</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($packs as $p): ?>
              <tr>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['item_name']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['issued']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['used']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['wasted']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['balance']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['quantity_achieved']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['yield_percent']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['units']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['cost_per_unit']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($p['total_cost']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500 italic">No packaging reconciliation recorded yet.</p>
      <?php endif; ?>
    </div>

    <!-- ✅ Back Button -->
    <div class="flex justify-end">
      <a href="inspect_finished_products.php" 
         class="bg-gray-500 text-white px-6 py-2 rounded-lg shadow hover:bg-gray-600 transition">
        ← Back
      </a>
    </div>
    <!-- ✅ Download PDF Button -->
<div class="flex justify-end mb-4">
  <a href="download_finished_product.php?id=<?= $bom_id ?>" 
     class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700 transition">
    📄 Download as PDF
  </a>
</div>

  </div>
</body>
</html>
