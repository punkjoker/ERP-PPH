<?php
include 'db_con.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Insert disposal record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("INSERT INTO disposables (
        item_name, asset_id, category, disposal_method, disposal_date, disposal_location,
        quantity, condition_before, authorized_by, handled_by, regulatory_ref,
        certificate_ref, reason_for_disposal, remarks
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssssssssssssss",
        $_POST['item_name'], $_POST['asset_id'], $_POST['category'],
        $_POST['disposal_method'], $_POST['disposal_date'], $_POST['disposal_location'],
        $_POST['quantity'], $_POST['condition_before'], $_POST['authorized_by'],
        $_POST['handled_by'], $_POST['regulatory_ref'], $_POST['certificate_ref'],
        $_POST['reason_for_disposal'], $_POST['remarks']
    );
    $stmt->execute();
}

// Fetch all disposables
$disposables = $conn->query("SELECT * FROM disposables ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Disposables Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // Modal handling
    function openModal(disposable) {
      document.getElementById('modal-item-name').textContent = disposable.item_name;
      document.getElementById('modal-asset-id').textContent = disposable.asset_id || '—';
      document.getElementById('modal-category').textContent = disposable.category || '—';
      document.getElementById('modal-method').textContent = disposable.disposal_method || '—';
      document.getElementById('modal-date').textContent = disposable.disposal_date || '—';
      document.getElementById('modal-location').textContent = disposable.disposal_location || '—';
      document.getElementById('modal-quantity').textContent = disposable.quantity || '—';
      document.getElementById('modal-condition').textContent = disposable.condition_before || '—';
      document.getElementById('modal-authorized').textContent = disposable.authorized_by || '—';
      document.getElementById('modal-handled').textContent = disposable.handled_by || '—';
      document.getElementById('modal-regref').textContent = disposable.regulatory_ref || '—';
      document.getElementById('modal-certref').textContent = disposable.certificate_ref || '—';
      document.getElementById('modal-reason').textContent = disposable.reason_for_disposal || '—';
      document.getElementById('modal-remarks').textContent = disposable.remarks || '—';
      document.getElementById('detailsModal').classList.remove('hidden');
    }

    function closeModal() {
      document.getElementById('detailsModal').classList.add('hidden');
    }
  </script>
</head>
<body class="bg-gray-100 font-sans">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-8">
    <h2 class="text-3xl font-bold text-blue-800 mb-6 border-b pb-2">♻️ Disposables Management</h2>

    <!-- Disposal Form -->
    <form method="POST" class="bg-white p-6 rounded-xl shadow mb-10">
      <h3 class="text-xl font-semibold text-gray-700 mb-4">Item Details</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="text" name="item_name" placeholder="Item Name / Description" class="p-2 border rounded-lg">
        <input type="text" name="asset_id" placeholder="Asset / Batch ID" class="p-2 border rounded-lg">
        <input type="text" name="category" placeholder="Category (e.g. Chemical, Electronic)" class="p-2 border rounded-lg">
      </div>

      <h3 class="text-xl font-semibold text-gray-700 mt-6 mb-4">Disposal Information</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="text" name="disposal_method" placeholder="Disposal Method" class="p-2 border rounded-lg">
        <input type="date" name="disposal_date" class="p-2 border rounded-lg">
        <input type="text" name="disposal_location" placeholder="Disposal Location" class="p-2 border rounded-lg">
        <input type="text" name="quantity" placeholder="Quantity / Volume / Weight" class="p-2 border rounded-lg">
        <input type="text" name="condition_before" placeholder="Condition Before Disposal" class="p-2 border rounded-lg">
      </div>

      <h3 class="text-xl font-semibold text-gray-700 mt-6 mb-4">Compliance & Authorization</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="text" name="authorized_by" placeholder="Disposal Authorized By" class="p-2 border rounded-lg">
        <input type="text" name="handled_by" placeholder="Handled By" class="p-2 border rounded-lg">
        <input type="text" name="regulatory_ref" placeholder="Regulatory Reference (e.g. NEMA code)" class="p-2 border rounded-lg">
        <input type="text" name="certificate_ref" placeholder="Certificate of Disposal Ref" class="p-2 border rounded-lg">
      </div>

      <h3 class="text-xl font-semibold text-gray-700 mt-6 mb-4">Additional Notes</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="text" name="reason_for_disposal" placeholder="Reason for Disposal" class="p-2 border rounded-lg">
        <textarea name="remarks" placeholder="Remarks / Observations" class="p-2 border rounded-lg"></textarea>
      </div>

      <button type="submit" class="mt-6 bg-blue-700 hover:bg-blue-800 text-white px-6 py-2 rounded-lg shadow">
        Save Disposal Record
      </button>
    </form>

    <!-- Disposal List -->
    <div class="bg-white shadow rounded-xl p-6">
      <h3 class="text-xl font-semibold text-gray-700 mb-4">Disposal Records</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border">
          <thead class="bg-gray-100">
            <tr>
              <th class="border px-3 py-2">#</th>
              <th class="border px-3 py-2">Item Name</th>
              <th class="border px-3 py-2">Method</th>
              <th class="border px-3 py-2">Date</th>
              <th class="border px-3 py-2">Location</th>
              <th class="border px-3 py-2">Authorized By</th>
              <th class="border px-3 py-2">Reason</th>
              <th class="border px-3 py-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($disposables->num_rows > 0): 
              $i = 1; while($row = $disposables->fetch_assoc()): ?>
              <tr class="<?= $i % 2 == 0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">
                <td class="border px-3 py-1"><?= $i++ ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($row['item_name']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($row['disposal_method']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($row['disposal_date']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($row['disposal_location']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($row['authorized_by']) ?></td>
                <td class="border px-3 py-1"><?= htmlspecialchars($row['reason_for_disposal']) ?></td>
                <td class="border px-3 py-1 text-center">
                  <button onclick='openModal(<?= json_encode($row) ?>)' 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">
                    View
                  </button>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="8" class="text-center text-gray-500 py-4">No disposal records yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Popup Modal -->
  <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg w-11/12 md:w-2/3 p-6 overflow-y-auto max-h-[90vh]">
      <h2 class="text-2xl font-bold text-blue-700 mb-4">Disposal Details</h2>
      <div class="space-y-3 text-gray-700 text-sm">
        <p><strong>Item Name:</strong> <span id="modal-item-name"></span></p>
        <p><strong>Asset ID:</strong> <span id="modal-asset-id"></span></p>
        <p><strong>Category:</strong> <span id="modal-category"></span></p>
        <p><strong>Disposal Method:</strong> <span id="modal-method"></span></p>
        <p><strong>Disposal Date:</strong> <span id="modal-date"></span></p>
        <p><strong>Disposal Location:</strong> <span id="modal-location"></span></p>
        <p><strong>Quantity:</strong> <span id="modal-quantity"></span></p>
        <p><strong>Condition Before Disposal:</strong> <span id="modal-condition"></span></p>
        <p><strong>Authorized By:</strong> <span id="modal-authorized"></span></p>
        <p><strong>Handled By:</strong> <span id="modal-handled"></span></p>
        <p><strong>Regulatory Reference:</strong> <span id="modal-regref"></span></p>
        <p><strong>Certificate Reference:</strong> <span id="modal-certref"></span></p>
        <p><strong>Reason for Disposal:</strong> <span id="modal-reason"></span></p>
        <p><strong>Remarks:</strong> <span id="modal-remarks"></span></p>
      </div>

      <div class="text-right mt-6">
        <button onclick="closeModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
          Close
        </button>
      </div>
    </div>
  </div>

</body>
</html>
