<?php
session_start();
require 'db_con.php';

// Get material ID
$material_id = intval($_GET['id'] ?? 0);

// Date filter
$from_date = $_GET['from'] ?? '';
$to_date = $_GET['to'] ?? '';

// Fetch material details
$material_stmt = $conn->prepare("SELECT * FROM materials WHERE id=?");
$material_stmt->bind_param("i", $material_id);
$material_stmt->execute();
$material = $material_stmt->get_result()->fetch_assoc();

// Fetch material history with optional filter
if ($from_date && $to_date) {
    $history_stmt = $conn->prepare("SELECT * FROM material_out_history WHERE material_id=? AND DATE(removed_at) BETWEEN ? AND ? ORDER BY removed_at DESC");
    $history_stmt->bind_param("iss", $material_id, $from_date, $to_date);
} else {
    $history_stmt = $conn->prepare("SELECT * FROM material_out_history WHERE material_id=? ORDER BY removed_at DESC");
    $history_stmt->bind_param("i", $material_id);
}
$history_stmt->execute();
$history = $history_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Material History - <?= htmlspecialchars($material['material_name'] ?? 'Unknown') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body class="bg-blue-50 min-h-screen px-4">

<?php include 'navbar.php'; ?>

<div class="max-w-6xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">

  <!-- Filters and Actions -->
  <form method="GET" class="flex gap-4 mb-4">
    <input type="hidden" name="id" value="<?= $material_id ?>">
    <div>
      <label class="text-sm">From:</label>
      <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>" class="border rounded px-2 py-1">
    </div>
    <div>
      <label class="text-sm">To:</label>
      <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>" class="border rounded px-2 py-1">
    </div>
    <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700">Filter</button>
  </form>

  <div class="mb-4 flex justify-between">
    <a href="remove_material.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Back</a>
    <div class="flex gap-2">
      <button onclick="printHistory()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Print</button>
      <button onclick="downloadPDF()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Save as PDF</button>
    </div>
  </div>

  <!-- Everything to print -->
  <div id="print-section" class="overflow-x-auto">

    <!-- Stock Card Header -->
    <div class="border-b pb-4 mb-6 text-sm text-gray-700">
      <p class="font-bold text-lg text-center mb-2">STOCK CARDS - QF 18</p>
      <div class="grid grid-cols-3 gap-2">
        <p><strong>Effective Date:</strong> 01/11/2024</p>
        <p><strong>Issue Date:</strong> 25/10/2024</p>
        <p><strong>Review Date:</strong> 10/2027</p>
        <p><strong>Issue No:</strong> 007</p>
        <p><strong>Revision No:</strong> 006</p>
        <p><strong>Manual No:</strong> LYNNTECH-QP-22</p>
      </div>
    </div>

    <!-- Title -->
    <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">
      Material History: <?= htmlspecialchars($material['material_name'] ?? 'Unknown') ?>
    </h2>

    <!-- History Table -->
    <table class="w-full border text-sm">
      <thead class="bg-blue-100">
        <tr>
          <th class="border px-2 py-1">Qty Removed</th>
          <th class="border px-2 py-1">Remaining</th>
          <th class="border px-2 py-1">Issued To</th>
          <th class="border px-2 py-1">Description</th>
          <th class="border px-2 py-1">Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($history->num_rows > 0): ?>
          <?php while ($row = $history->fetch_assoc()): ?>
            <tr>
              <td class="border px-2 py-1 text-red-600 font-bold"><?= $row['quantity_removed'] ?></td>
              <td class="border px-2 py-1 text-green-700"><?= $row['remaining_quantity'] ?></td>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['issued_to']) ?></td>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['description']) ?></td>
              <td class="border px-2 py-1"><?= $row['removed_at'] ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" class="text-center text-gray-500 py-2">No history found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function printHistory() {
  const printContent = document.getElementById('print-section').innerHTML;
  const originalContent = document.body.innerHTML;
  document.body.innerHTML = printContent;
  window.print();
  document.body.innerHTML = originalContent;
  location.reload();
}

function downloadPDF() {
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF('p', 'pt', 'a4');
  const section = document.getElementById("print-section");

  html2canvas(section).then(canvas => {
    const imgData = canvas.toDataURL("image/png");
    const imgProps = pdf.getImageProperties(imgData);
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
    pdf.addImage(imgData, 'PNG', 10, 10, pdfWidth - 20, pdfHeight);
    pdf.save("material_history.pdf");
  });
}
</script>

</body>
</html>
