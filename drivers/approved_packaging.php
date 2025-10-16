<?php
include 'db_con.php';

$run_id = intval($_GET['run_id'] ?? 0);
if ($run_id <= 0) die("Invalid production run.");

// ✅ Fetch production + BOM + product details
$sql = "
SELECT 
    pr.id AS production_run_id,
    pr.status AS production_status,
    pr.obtained_yield,
    
    bom.id AS bom_id,
    bom.bom_date,
    bom.requested_by,
    bom.description,
    p.name AS product_name
FROM production_runs pr
JOIN bill_of_materials bom ON pr.request_id = bom.id
JOIN products p ON bom.product_id = p.id
WHERE pr.id = $run_id
LIMIT 1";
$res = $conn->query($sql);
if (!$res || $res->num_rows === 0) die("No record found.");
$product = $res->fetch_assoc();

// ✅ Fetch materials for dropdown
$materials = $conn->query("SELECT id, material_name, cost, quantity FROM materials ORDER BY material_name ASC")->fetch_all(MYSQLI_ASSOC);

// ✅ Fetch existing packaging records
$pack_res = $conn->query("
    SELECT pkg.*, m.material_name 
    FROM packaging pkg
    LEFT JOIN materials m ON pkg.material_id = m.id
    WHERE pkg.production_run_id = $run_id
");
$packaging_records = $pack_res ? $pack_res->fetch_all(MYSQLI_ASSOC) : [];

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? 'Pending';
    $issued_by = trim($_POST['issued_by'] ?? 'HR');
    $issued_date = $_POST['issued_date'] ?? date('Y-m-d H:i:s');


    // ✅ 1. Update status, issued_by, and issued_date
    $stmt = $conn->prepare("
        UPDATE packaging 
        SET status = ?, 
            issued_by = ?, 
            issued_date = ?
        WHERE production_run_id = ?
    ");
    $stmt->bind_param("sssi", $status, $issued_by, $issued_date, $run_id);
    $stmt->execute();
    $stmt->close();

    // ✅ 2. Log to material_out_history only if Approved
    if ($status === 'Approved') {
        $packaging = $conn->query("
            SELECT p.material_id, m.material_name, p.quantity_used 
            FROM packaging p
            LEFT JOIN materials m ON p.material_id = m.id
            WHERE p.production_run_id = $run_id
        ");

        while ($row = $packaging->fetch_assoc()) {
            $mat_id = $row['material_id'];
            $qty_used = $row['quantity_used'];
            $mat_name = $row['material_name'];

            // ✅ Deduct from stock
            $conn->query("UPDATE materials SET quantity = quantity - $qty_used WHERE id = $mat_id");

            // ✅ Get remaining stock
            $mat = $conn->query("SELECT quantity FROM materials WHERE id = $mat_id")->fetch_assoc();
            $remaining = $mat['quantity'];

            // ✅ Log in material_out_history
            $issued_to = $product['product_name'];
            $desc = "Used for approved packaging of production run #$run_id";

            $stmt = $conn->prepare("
    INSERT INTO material_out_history (material_id, material_name, quantity_removed, remaining_quantity, issued_to, description)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isddss", $mat_id, $mat_name, $qty_used, $remaining, $issued_to, $desc);

            $stmt->execute();
            $stmt->close();
        }
    }

    echo "<script>alert('✅ Packaging status updated successfully!'); window.location='packaging_list.php';</script>";
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Approved Packaging - <?= htmlspecialchars($product['product_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
  function calculatePackages(row) {
      const yieldQty = parseFloat(document.getElementById('obtainedYield').value) || 0;
      const packSize = parseFloat(row.querySelector('.pack-size').value) || 0;
      const unitCost = parseFloat(row.querySelector('.unit-cost').value) || 0;
      if (packSize > 0) {
          const packagesNeeded = Math.ceil(yieldQty / packSize);
          row.querySelector('.quantity-used').value = packagesNeeded;
          row.querySelector('.total-cost').value = (packagesNeeded * unitCost).toFixed(2);
      }
  }

  function fillMaterialData(select) {
      const option = select.options[select.selectedIndex];
      const row = select.closest('.material-row');
      row.querySelector('.unit-cost').value = option.dataset.cost;
      row.querySelector('.available-stock').value = option.dataset.qty;
  }

  function addMaterialRow() {
      const template = document.querySelector('.material-row');
      const clone = template.cloneNode(true);
      clone.querySelectorAll('input').forEach(i => i.value = '');
      clone.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
      document.getElementById('materials-container').appendChild(clone);
  }
  </script>
</head>

<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
  <div class="bg-white shadow-md p-6 rounded-lg border-b-4 border-blue-500 mb-6">
    <h2 class="text-2xl font-bold text-gray-800 mb-3">Packaging for: <?= htmlspecialchars($product['product_name']); ?></h2>
    <p><strong>Requested By:</strong> <?= htmlspecialchars($product['requested_by']); ?></p>
    <p><strong>Description:</strong> <?= htmlspecialchars($product['description']); ?></p>
    <p><strong>Date:</strong> <?= htmlspecialchars($product['bom_date']); ?></p>
    <p><strong>Obtained Yield:</strong> 
      <input type="number" id="obtainedYield" value="<?= htmlspecialchars($product['obtained_yield'] ?? 0); ?>" 
             class="border p-1 rounded w-24 text-center font-semibold text-blue-700">
      <?= htmlspecialchars($product['yield_unit'] ?? 'Kg/L'); ?>
    </p>
  </div>

  <!-- ✅ Existing Packaging Records -->
  <?php if (count($packaging_records) > 0): ?>
    <div class="bg-white shadow-md rounded-lg p-4 mb-6 border">
      <h3 class="text-lg font-semibold text-green-700 mb-3">Existing Packaging Records</h3>
      <table class="min-w-full border text-sm">
        <thead class="bg-green-100">
          <tr>
            <th class="border px-2 py-1">Material</th>
            <th class="border px-2 py-1">Qty Used</th>
            <th class="border px-2 py-1">Cost/Unit</th>
            <th class="border px-2 py-1">Total Cost</th>
            <th class="border px-2 py-1">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($packaging_records as $r): ?>
            <tr>
              <td class="border px-2 py-1"><?= htmlspecialchars($r['material_name']); ?></td>
              <td class="border px-2 py-1"><?= htmlspecialchars($r['quantity_used']); ?></td>
              <td class="border px-2 py-1"><?= htmlspecialchars($r['cost_per_unit']); ?></td>
              <td class="border px-2 py-1"><?= number_format($r['total_cost'], 2); ?></td>
              <td class="border px-2 py-1 text-center font-semibold <?= $r['status'] === 'Completed' ? 'text-green-600' : 'text-yellow-600'; ?>">
                <?= htmlspecialchars($r['status']); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <form method="POST">
  <div class="grid grid-cols-2 gap-4 mt-4">
    <div>
      <label class="font-semibold text-gray-700">Issued By:</label>
      <input type="text" name="issued_by" 
             value="<?= htmlspecialchars($_SESSION['username'] ?? 'HR'); ?>" 
             class="border p-2 rounded w-full">
    </div>
    <div>
      <label class="font-semibold text-gray-700">Issue Date:</label>
      <input type="datetime-local" name="issued_date" 
             value="<?= date('Y-m-d\TH:i'); ?>" 
             class="border p-2 rounded w-full">
    </div>
  </div>

<div class="mt-4">
  <label class="font-semibold text-gray-700">Packaging Status:</label>
  <select name="status" class="border p-2 rounded ml-2">
    <option value="Pending">Pending</option>
    <option value="Approved">Approved</option>
    <option value="Rejected">Rejected</option>
  </select>
</div>

 

    <div class="mt-6 flex justify-end">
      <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700">
        ✅ Approve & Save Packaging
      </button>
    </div>
  </form>
</div>
</body>
</html>
