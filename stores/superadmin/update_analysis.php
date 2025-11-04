<?php
session_start();
require 'db_con.php';

$chemical_id = intval($_GET['id'] ?? 0);

// Fetch chemical details
$stmt = $conn->prepare("SELECT * FROM chemicals_in WHERE id=?");
$stmt->bind_param("i", $chemical_id);
$stmt->execute();
$chemical = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_analysis'])) {
    $rm_lot_no = $_POST['rm_lot_no'];
    $approved_qty = floatval($_POST['approved_quantity']);
    $approved_by = $_POST['approved_by'];
    $approved_date = $_POST['approved_date'];
    $status = $_POST['status'];

    // Collect test data
    $tests = [];
    if (isset($_POST['test_type'])) {
        foreach ($_POST['test_type'] as $i => $type) {
            $tests[] = [
                'type' => $type,
                'specification' => $_POST['specification'][$i] ?? '',
                'result' => $_POST['result'][$i] ?? ''
            ];
        }
    }
    $tests_json = json_encode($tests);

    // If status is 'Denied' move to rejected_chemicals_in
    if ($status === 'Denied') {
        // 1️⃣ Insert into rejected_chemicals_in (copy all data from chemicals_in)
        $copy_sql = "
            INSERT INTO rejected_chemicals_in 
            SELECT * FROM chemicals_in WHERE id = ?
        ";
        $copy_stmt = $conn->prepare($copy_sql);
        $copy_stmt->bind_param("i", $chemical_id);
        $copy_stmt->execute();

        // 2️⃣ Delete from chemicals_in
        $del_stmt = $conn->prepare("DELETE FROM chemicals_in WHERE id = ?");
        $del_stmt->bind_param("i", $chemical_id);
        $del_stmt->execute();

        // 3️⃣ Redirect back
        header("Location: inspect_raw_materials.php?msg=rejected");
        exit();
    }

    // For approved or pending: insert into inspected_chemicals_in
    $ins_stmt = $conn->prepare("INSERT INTO inspected_chemicals_in 
        (chemical_id, rm_lot_no, approved_quantity, approved_by, approved_date, tests) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $ins_stmt->bind_param("isdsss", $chemical_id, $rm_lot_no, $approved_qty, $approved_by, $approved_date, $tests_json);
    $ins_stmt->execute();

    // Update status in chemicals_in
    $upd_stmt = $conn->prepare("UPDATE chemicals_in SET status=? WHERE id=?");
    $upd_stmt->bind_param("si", $status, $chemical_id);
    $upd_stmt->execute();

    header("Location: inspect_raw_materials.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update Analysis</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen px-4">
<?php include 'navbar.php'; ?>

<div class="max-w-6xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">
  <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Quality Control Analysis</h2>

  <!-- Chemical Details -->
  <div class="mb-6 border-b pb-4">
    <p><strong>Chemical:</strong> <?= htmlspecialchars($chemical['chemical_name']) ?></p>
    <p><strong>LOT No:</strong> <?= htmlspecialchars($chemical['rm_lot_no']) ?></p>
    <p><strong>Quantity:</strong> <?= $chemical['std_quantity'] ?></p>
    <p><strong>Status:</strong> <?= $chemical['status'] ?></p>
  </div>

  <!-- QC Tests Form -->
  <form method="POST" class="space-y-6">
    <input type="hidden" name="id" value="<?= $chemical_id ?>">

    <h3 class="text-lg font-bold text-gray-700">QUALITY CONTROL TESTS ANALYSIS</h3>
    <div id="tests-container" class="space-y-3">
      <div class="grid grid-cols-3 gap-2">
        <input type="text" name="test_type[]" placeholder="Type of Test" class="border rounded px-3 py-2" required>
        <input type="text" name="specification[]" placeholder="Specification" class="border rounded px-3 py-2" required>
        <input type="text" name="result[]" placeholder="Result" class="border rounded px-3 py-2" required>
      </div>
    </div>
    <button type="button" onclick="addTestRow()" class="bg-green-600 text-white px-3 py-1 rounded">+ Add Test</button>

    <!-- Approval Section -->
    <h3 class="text-lg font-bold text-gray-700 mt-6">Approval Section</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
  <label class="block text-sm">RM LOT NO</label>
  <input 
    type="text" 
    name="rm_lot_no" 
    value="<?= htmlspecialchars($chemical['rm_lot_no']) ?>" 
    class="w-full border rounded px-3 py-2 bg-gray-100 text-gray-700" 
    readonly 
  >
</div>

      <div>
        <label class="block text-sm">Approved Quantity</label>
        <input type="number" step="0.01" name="approved_quantity" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm">Approved By</label>
        <input type="text" name="approved_by" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm">Approved Date</label>
        <input type="date" name="approved_date" class="w-full border rounded px-3 py-2" required>
      </div>
      <div class="md:col-span-2">
        <label class="block text-sm">Update Status</label>
        <select name="status" class="w-full border rounded px-3 py-2" required>
          <option value="Pending">Pending</option>
          <option value="Approved">Approved</option>
          <option value="Denied">Denied</option>
        </select>
      </div>
    </div>

    <!-- Buttons -->
    <div class="flex justify-between mt-6">
      <a href="inspect_raw_materials.php" class="bg-gray-500 text-white px-4 py-2 rounded">Back</a>
      <button type="submit" name="save_analysis" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Save Analysis</button>
    </div>
  </form>
</div>

<script>
function addTestRow() {
  const container = document.getElementById('tests-container');
  const row = document.createElement('div');
  row.className = "grid grid-cols-3 gap-2";
  row.innerHTML = `
    <input type="text" name="test_type[]" placeholder="Type of Test" class="border rounded px-3 py-2" required>
    <input type="text" name="specification[]" placeholder="Specification" class="border rounded px-3 py-2" required>
    <input type="text" name="result[]" placeholder="Result" class="border rounded px-3 py-2" required>
  `;
  container.appendChild(row);
}
</script>

</body>
</html>
