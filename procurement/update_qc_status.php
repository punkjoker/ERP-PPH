<?php
include 'db_con.php';

$bom_id = $_GET['id'] ?? 0;

// ✅ Fetch full production and product details
$sql = "SELECT pr.*, p.name AS product_name, bom.requested_by, bom.description, bom.bom_date
        FROM production_runs pr
        JOIN bill_of_materials bom ON pr.request_id = bom.id
        JOIN products p ON bom.product_id = p.id
        WHERE pr.request_id = $bom_id LIMIT 1";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) die("No record found for this product.");
$production = $result->fetch_assoc();

// ✅ Fetch procedures for the production run
$procedures = [];
$proc_result = $conn->query("SELECT * FROM production_procedures WHERE production_run_id = {$production['id']} ORDER BY created_at ASC");
if ($proc_result && $proc_result->num_rows > 0) {
  while ($row = $proc_result->fetch_assoc()) {
    $procedures[] = $row;
  }
}

// ✅ Fetch existing QC inspection status
$qc_status_query = $conn->query("SELECT qc_status FROM qc_inspections WHERE production_run_id = {$production['id']} LIMIT 1");
$current_qc_status = 'Not Approved';
if ($qc_status_query && $qc_status_query->num_rows > 0) {
  $qc_data = $qc_status_query->fetch_assoc();
  $current_qc_status = $qc_data['qc_status'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>QC Inspection - <?= htmlspecialchars($production['product_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>

<body class="bg-gray-100 font-sans">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-6">
    <!-- ✅ Header Section -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-6 flex items-center border-b-4 border-green-600">
      <img src="images/lynn_logo.png" alt="Logo" class="h-16 mr-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">QUALITY CONTROL INSPECTION (QF-29)</h1>
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

    <!-- ✅ Batch Details -->
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
      </div>
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

    <!-- ✅ Procedures List -->
    <div class="bg-white shadow-lg rounded-lg p-6 border mb-8">
      <h2 class="text-xl font-semibold text-green-700 mb-4 border-b pb-2">Production Procedures</h2>
      <?php if (count($procedures) > 0): ?>
        <table class="min-w-full border border-gray-300 rounded-lg overflow-hidden text-sm">
          <thead class="bg-green-100 text-gray-700">
            <tr>
              <th class="px-4 py-2 text-left">#</th>
              <th class="px-4 py-2 text-left">Procedure Name</th>
              <th class="px-4 py-2 text-left">Done By</th>
              <th class="px-4 py-2 text-left">Checked By</th>
              <th class="px-4 py-2 text-left">Date Recorded</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php foreach ($procedures as $index => $proc): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2"><?= $index + 1 ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($proc['procedure_name']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($proc['done_by']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($proc['checked_by']) ?></td>
                <td class="px-4 py-2"><?= date('d M Y, h:i A', strtotime($proc['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500 italic">No procedures recorded yet for this production run.</p>
      <?php endif; ?>
    </div>

    <!-- ✅ QC Inspection Form -->
<form action="save_qc_inspection.php" method="POST"
  x-data="{
    tests: [],
    approved: '<?= $current_qc_status ?>' === 'Approved Product'
  }">

  <input type="hidden" name="production_run_id" value="<?= $production['id'] ?>">

  <!-- QC Tests -->
  <div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-lg font-bold mb-4 text-blue-700">Quality Control Tests</h2>
    <template x-for="(t,i) in tests" :key="i">
      <div class="grid grid-cols-3 gap-4 mb-3">
        <input type="text" x-model="t.test" name="tests[]" placeholder="Test Name" class="border p-2 rounded">
        <input type="text" x-model="t.spec" name="specs[]" placeholder="Specification" class="border p-2 rounded">
        <input type="text" x-model="t.proc" name="procedures[]" placeholder="Results" class="border p-2 rounded">
      </div>
    </template>
    <button type="button" @click="tests.push({test:'',spec:'',proc:''})" class="bg-blue-500 text-white px-3 py-1 rounded text-sm">+ Add Test</button>
  </div>

  <!-- Quality Manager Review -->
  <div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-lg font-bold mb-4 text-gray-800">Quality Manager Review</h2>
    <table class="min-w-full text-sm border">
      <thead class="bg-gray-100">
        <tr>
          <th class="border px-2 py-1">No.</th>
          <th class="border px-2 py-1">Checklist</th>
          <th class="border px-2 py-1">Yes</th>
          <th class="border px-2 py-1">No</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $checklist = [
            "All production processes have been fully followed and complied",
            "Quality Control Processes have been fully followed.",
            "All QC reports are duly filled, recorded, and signed.",
            "Final product complies with standard specifications.",
            "Final product complies with packaging specifications.",
            "Retain sample collected and stored.",
            "All blank spaces have been fully filled.",
            "Certificate of Analysis complies with test results.",
            "Product released for sale."
          ];
          foreach ($checklist as $i => $item) {
            echo "
              <tr>
                <td class='border px-2 py-1 text-center'>".($i+1)."</td>
                <td class='border px-2 py-1'>$item</td>
                <td class='border px-2 py-1 text-center'><input type='radio' name='checklist_$i' value='Yes'></td>
                <td class='border px-2 py-1 text-center'><input type='radio' name='checklist_$i' value='No'></td>
              </tr>
            ";
          }
        ?>
      </tbody>
    </table>
  </div>

  <!-- QC Status (moved here) -->
  <div class="bg-white p-6 rounded-lg shadow mb-6">
    <label class="block font-semibold text-gray-700 mb-2">Final QC Status</label>
    <select name="qc_status"
            @change="approved = ($event.target.value === 'Approved Product')"
            class="border p-2 rounded w-full">
      <option value="Not Approved" <?= $current_qc_status == 'Not Approved' ? 'selected' : '' ?>>Not Approved</option>
      <option value="Approved Product" <?= $current_qc_status == 'Approved Product' ? 'selected' : '' ?>>Approved Product</option>
    </select>
  </div>

  <!-- Submit -->
  <div class="flex justify-end space-x-4">
    <a href="inspect_finished_products.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg shadow hover:bg-gray-600">
      ← Back
    </a>
    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg shadow hover:bg-green-700">
      Save QC Data
    </button>
  </div>
</form>
</div>
</body>
</html>
