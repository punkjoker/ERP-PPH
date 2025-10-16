<?php
include 'db_con.php';

$bom_id = $_GET['id'] ?? 0;

// ✅ Fetch product, QC, production details
$sql = "SELECT pr.*, p.name AS product_name, bom.requested_by, bom.description, bom.bom_date
        FROM production_runs pr
        JOIN bill_of_materials bom ON pr.request_id = bom.id
        JOIN products p ON bom.product_id = p.id
        WHERE pr.request_id = $bom_id LIMIT 1";
$res = $conn->query($sql);
if (!$res || $res->num_rows === 0) die("No record found for this BOM ID.");
$production = $res->fetch_assoc();


// ✅ Fetch all materials (for dropdown)
$materials = $conn->query("SELECT id, material_name, cost, quantity FROM materials ORDER BY material_name ASC")->fetch_all(MYSQLI_ASSOC);

// ✅ Fetch production procedures
$procedures = [];
$proc_result = $conn->query("SELECT * FROM production_procedures WHERE production_run_id = {$production['id']} ORDER BY created_at ASC");
if ($proc_result && $proc_result->num_rows > 0) {
  while ($row = $proc_result->fetch_assoc()) {
    $procedures[] = $row;
  }
}

// ✅ Fetch QC tests from the qc_tests table
$qc_tests = [];
$qc_result = $conn->query("
  SELECT t.*, i.qc_status
  FROM qc_tests t
  JOIN qc_inspections i ON t.qc_inspection_id = i.id
  WHERE i.production_run_id = {$production['id']}
  ORDER BY t.created_at ASC
");
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
  <title>Update Packaging - <?= htmlspecialchars($production['product_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
  // 🧮 Calculate packages and unpackaged quantity
  function calculatePackages(row) {
    const yieldQty = parseFloat(document.getElementById('obtainedYield').value) || 0;
    const packSize = parseFloat(row.querySelector('.pack-size').value) || 0;
    const unitCost = parseFloat(row.querySelector('.unit-cost').value) || 0;

    if (packSize > 0) {
      const fullPacks = Math.floor(yieldQty / packSize);
      const unpackaged = yieldQty % packSize;
      const totalCost = (fullPacks * unitCost).toFixed(2);

      // Update fields
      row.querySelector('.quantity-used').value = fullPacks;
      row.querySelector('.unpackaged-qty').value = unpackaged.toFixed(2);
      row.querySelector('.total-cost').value = totalCost;
    }
  }

  // ➕ Add new material row
  function addMaterialRow() {
    const template = document.querySelector('.material-row');
    const clone = template.cloneNode(true);
    clone.querySelectorAll('input').forEach(i => i.value = '');
    clone.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
    document.getElementById('materials-container').appendChild(clone);
  }

  // 🧾 Auto-fill unit cost & available stock
  function fillMaterialData(select) {
  const option = select.options[select.selectedIndex];
  const row = select.closest('.material-row');
  row.querySelector('.unit-cost').value = option.dataset.cost;
  row.querySelector('.available-stock').value = option.dataset.qty;
  row.querySelector('.pack-size').value = option.dataset.packsize || '';
  
  calculatePackages(row);
}

</script>
</head>

<body class="bg-gray-100 font-sans">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
  <!-- ✅ Product Info -->
  <div class="bg-white p-6 rounded-lg shadow-lg mb-6 border-b-4 border-blue-600">
    <h2 class="text-2xl font-bold text-gray-800 mb-2">PACKAGING DETAILS</h2>
    <p><span class="font-semibold text-gray-600">Product:</span> <?= htmlspecialchars($production['product_name']); ?></p>
    <p><span class="font-semibold text-gray-600">Requested By:</span> <?= htmlspecialchars($production['requested_by']); ?></p>
    <p><span class="font-semibold text-gray-600">Obtained Yield:</span> 
      <input type="number" id="obtainedYield" value="<?= htmlspecialchars($production['obtained_yield'] ?? 0); ?>" 
             class="border p-1 rounded w-24 inline-block text-center font-semibold text-blue-700">
      <span class="text-gray-600"><?= htmlspecialchars($production['yield_unit'] ?? 'Kg/L'); ?></span>
    </p>
    <p><span class="font-semibold text-gray-600">Description:</span> <?= htmlspecialchars($production['description']); ?></p>
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

  <!-- ✅ Packaging Form -->
  <form method="POST" action="save_packaging.php">
    <input type="hidden" name="production_run_id" value="<?= $production['id']; ?>">

    <div id="materials-container">
      <div class="material-row grid grid-cols-10 gap-2 bg-white p-3 mb-2 rounded shadow-sm border items-center">
        <div class="col-span-3">
          <label class="text-sm font-semibold text-gray-700">Material</label>
          <select name="material_id[]" class="border p-2 rounded w-full" onchange="fillMaterialData(this)">
            <option value="">-- Select Material --</option>
            <?php foreach ($materials as $m): ?>
              <option value="<?= $m['id']; ?>" 
    data-cost="<?= $m['cost']; ?>"
    data-qty="<?= $m['quantity']; ?>">
  <?= htmlspecialchars($m['material_name']); ?>
</option>


            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="text-sm font-semibold text-gray-700">Pack Size (Kg/L)</label>
          <input type="number" step="0.01" name="pack_size[]" class="pack-size border p-2 rounded w-full" 
                 oninput="calculatePackages(this.closest('.material-row'))">
        </div>
<!-- Unit -->
   <div>
  <label class="text-sm font-semibold text-gray-700">Unit</label>
  <input type="text" name="unit[]" placeholder="e.g. Kg, L, pcs" 
         class="border p-2 rounded w-full" />
</div>


        <div>
          <label class="text-sm font-semibold text-gray-700">Available</label>
          <input type="number" class="available-stock border p-2 rounded w-full bg-gray-100" readonly>
        </div>

        <div>
          <label class="text-sm font-semibold text-gray-700">Qty Used</label>
          <input type="number" name="quantity_used[]" class="quantity-used border p-2 rounded w-full bg-gray-50" readonly>
        </div>

        <div>
          <label class="text-sm font-semibold text-gray-700">Unit Cost</label>
          <input type="number" step="0.01" name="cost_per_unit[]" class="unit-cost border p-2 rounded w-full" 
                 oninput="calculatePackages(this.closest('.material-row'))">
        </div>

        <div>
          <label class="text-sm font-semibold text-gray-700">Total Cost</label>
          <input type="number" step="0.01" name="total_cost[]" class="total-cost border p-2 rounded w-full bg-gray-100" readonly>
        </div>

        <div>
  <label class="text-sm font-semibold text-gray-700">Unpackaged Qty</label>
  <input type="number" name="unpackaged_qty[]" class="unpackaged-qty border p-2 rounded w-full bg-gray-50" readonly>
</div>

      </div>
    </div>

    <button type="button" onclick="addMaterialRow()" 
            class="mt-3 bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
      + Add Material
    </button>

    <div class="mt-6 flex justify-end">
      <button type="submit" 
              class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700 transition">
        💾 Save Packaging
      </button>
    </div>
  </form>
</div>

</body>
</html>
