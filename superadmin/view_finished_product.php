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
// ✅ Fetch Bill of Materials (BOM) data for this product
$bom_stmt = $conn->prepare("
  SELECT b.id, b.product_id, p.name AS product_name, b.status, b.description,
         b.requested_by, b.bom_date, b.issued_by, b.remarks, b.issue_date
  FROM bill_of_materials b
  JOIN products p ON b.product_id = p.id
  WHERE b.id = ?
");
$bom_stmt->bind_param("i", $bom_id);
$bom_stmt->execute();
$bom = $bom_stmt->get_result()->fetch_assoc();
$bom_stmt->close();

// ✅ Fetch BOM raw materials (chemicals)
$chem_stmt = $conn->prepare("
  SELECT i.chemical_id, c.chemical_name, i.quantity_requested, i.unit, 
         i.unit_price, i.total_cost, i.rm_lot_no
  FROM bill_of_material_items i
  JOIN chemicals_in c ON i.chemical_id = c.id
  WHERE i.bom_id = ?
");
$chem_stmt->bind_param("i", $bom_id);
$chem_stmt->execute();
$chemicals = $chem_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chem_stmt->close();

// ✅ Fetch packaging materials (linked to BOM)
$pack_stmt = $conn->prepare("
  SELECT pr.item_name, pr.units, pr.cost_per_unit, pr.total_cost
  FROM packaging_reconciliation pr
  JOIN qc_inspections qi ON qi.id = pr.qc_inspection_id
  JOIN production_runs r ON r.id = qi.production_run_id
  WHERE r.request_id = ?
");
$pack_stmt->bind_param("i", $bom_id);
$pack_stmt->execute();
$bom_packaging = $pack_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pack_stmt->close();

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
<!-- ✅ Bill of Materials -->
<div class="bg-white shadow-lg rounded-lg p-6 border mb-8">
  <h2 class="text-2xl font-semibold text-blue-800 mb-4 border-b pb-2">Bill of Materials (BOM)</h2>

  <div class="grid grid-cols-2 gap-6 text-sm mb-6">
    <p><span class="font-medium text-gray-700">Requested By:</span> <?= htmlspecialchars($bom['requested_by']) ?></p>
    <p><span class="font-medium text-gray-700">Issued By:</span> <?= htmlspecialchars($bom['issued_by']) ?></p>
    <p><span class="font-medium text-gray-700">BOM Date:</span> <?= htmlspecialchars($bom['bom_date']) ?></p>
    <p><span class="font-medium text-gray-700">Issue Date:</span> <?= htmlspecialchars($bom['issue_date']) ?></p>
    <p><span class="font-medium text-gray-700">Remarks:</span> <?= htmlspecialchars($bom['remarks']) ?></p>
  </div>

  <!-- ✅ Chemicals Section -->
  <h3 class="text-lg font-semibold text-gray-800 mb-2">Raw Materials (Chemicals)</h3>
  <table class="w-full border text-sm mb-6">
    <thead class="bg-gray-100">
      <tr>
        <th class="border px-3 py-1 text-left">Chemical</th>
        <th class="border px-3 py-1 text-left">RM LOT NO</th>
        <th class="border px-3 py-1 text-left">Qty Requested</th>
        <th class="border px-3 py-1 text-left">Unit</th>
        <th class="border px-3 py-1 text-left">Unit Price</th>
        <th class="border px-3 py-1 text-left">Total Cost</th>
      </tr>
    </thead>
    <tbody>
      <?php $chemical_total = 0; foreach ($chemicals as $c): $chemical_total += $c['total_cost']; ?>
      <tr>
        <td class="border px-3 py-1"><?= htmlspecialchars($c['chemical_name']) ?></td>
        <td class="border px-3 py-1"><?= htmlspecialchars($c['rm_lot_no']) ?></td>
        <td class="border px-3 py-1"><?= htmlspecialchars($c['quantity_requested']) ?></td>
        <td class="border px-3 py-1"><?= htmlspecialchars($c['unit']) ?></td>
        <td class="border px-3 py-1"><?= number_format($c['unit_price'], 2) ?></td>
        <td class="border px-3 py-1"><?= number_format($c['total_cost'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="bg-gray-50 font-semibold">
        <td colspan="5" class="text-right border px-3 py-1">Total Chemicals Cost</td>
        <td class="border px-3 py-1"><?= number_format($chemical_total, 2) ?></td>
      </tr>
    </tbody>
  </table>

  <!-- ✅ Packaging Section -->
  <h3 class="text-lg font-semibold text-gray-800 mb-2">Packaging Materials</h3>
  <table class="w-full border text-sm">
    <thead class="bg-gray-100">
      <tr>
        <th class="border px-3 py-1 text-left">Item</th>
        <th class="border px-3 py-1 text-left">Units</th>
        <th class="border px-3 py-1 text-left">Cost/Unit</th>
        <th class="border px-3 py-1 text-left">Total Cost</th>
      </tr>
    </thead>
    <tbody>
      <?php $packaging_total = 0; foreach ($bom_packaging as $p): $packaging_total += $p['total_cost']; ?>
      <tr>
        <td class="border px-3 py-1"><?= htmlspecialchars($p['item_name']) ?></td>
        <td class="border px-3 py-1"><?= htmlspecialchars($p['units']) ?></td>
        <td class="border px-3 py-1"><?= number_format($p['cost_per_unit'], 2) ?></td>
        <td class="border px-3 py-1"><?= number_format($p['total_cost'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="bg-gray-50 font-semibold">
        <td colspan="3" class="text-right border px-3 py-1">Total Packaging Cost</td>
        <td class="border px-3 py-1"><?= number_format($packaging_total, 2) ?></td>
      </tr>
    </tbody>
  </table>

  <div class="text-right mt-4 font-semibold text-blue-700">
    Grand Total Cost: <?= number_format($chemical_total + $packaging_total, 2) ?>
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
    <!-- ✅ Quality Manager Review -->
    <?php
    $review = $conn->query("
      SELECT * FROM quality_manager_review 
      WHERE qc_inspection_id IN (SELECT id FROM qc_inspections WHERE production_run_id = {$production['id']})
      ORDER BY checklist_no ASC
    ");
    ?>
    <div class="bg-white shadow-lg rounded-lg p-6 border mb-8">
      <h2 class="text-lg font-bold mb-4 text-purple-700">Quality Manager Review</h2>
      <?php if ($review && $review->num_rows > 0): ?>
        <table class="min-w-full border text-sm">
          <thead class="bg-purple-100">
            <tr>
              <th class="border px-3 py-1">#</th>
              <th class="border px-3 py-1">Checklist Item</th>
              <th class="border px-3 py-1">Response</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $n = 1;
            while ($r = $review->fetch_assoc()): ?>
              <tr>
                <td class="border px-3 py-1"><?= $n++ ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($r['checklist_item']) ?></td>
                <td class="border px-3 py-1 font-semibold <?= $r['response'] == 'Yes' ? 'text-green-600' : 'text-red-600' ?>">
                  <?= htmlspecialchars($r['response']) ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500 italic">No Quality Manager review data recorded yet.</p>
      <?php endif; ?>
    </div>

    <!-- ✅ Back Button -->
     <!--
    <div class="flex justify-end">
      <a href="inspect_finished_products.php" 
         class="bg-gray-500 text-white px-6 py-2 rounded-lg shadow hover:bg-gray-600 transition">
        ← Back
      </a>
    </div>
    -->
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
