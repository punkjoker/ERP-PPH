<?php
session_start();
require 'db_con.php';

// Get filter and search values
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search_name = $_GET['search_name'] ?? '';

// Build query dynamically
$sql = "SELECT * FROM rejected_chemicals_in WHERE 1=1";

if (!empty($from_date) && !empty($to_date)) {
    $sql .= " AND date_added BETWEEN '$from_date' AND '$to_date'";
}

if (!empty($search_name)) {
    $search_name = $conn->real_escape_string($search_name);
    $sql .= " AND chemical_name LIKE '%$search_name%'";
}

$sql .= " ORDER BY date_added DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Rejected Chemicals</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8">
  <h1 class="text-3xl font-bold text-red-700 mb-6">Rejected Chemicals</h1>

  <!-- 🔍 Filter Form -->
  <form method="GET" class="bg-white p-4 shadow-md rounded-lg mb-6 flex flex-wrap items-end gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700">From Date</label>
      <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border border-gray-300 rounded p-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">To Date</label>
      <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border border-gray-300 rounded p-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Chemical Name</label>
      <input type="text" name="search_name" value="<?= htmlspecialchars($search_name) ?>" placeholder="Search chemical..." class="border border-gray-300 rounded p-2">
    </div>

    <div class="flex items-end gap-2">
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>

      <!-- ✅ Download Button -->
      <a href="download_rejected.php?from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&search_name=<?= urlencode($search_name) ?>" 
         class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700" target="_blank">
         Download PDF
      </a>
    </div>
  </form>

  <!-- 📋 Data Table -->
  <div class="bg-white shadow-md rounded-lg overflow-hidden">
    <table class="w-full text-sm text-left border-collapse">
      <thead class="bg-red-100 text-red-700 uppercase">
        <tr>
          <th class="border px-3 py-2">#</th>
          <th class="border px-3 py-2">Chemical Code</th>
          <th class="border px-3 py-2">Chemical Name</th>
          <th class="border px-3 py-2">Batch No</th>
          <th class="border px-3 py-2">RM Lot No</th>
          <th class="border px-3 py-2">Original Qty</th>
          <th class="border px-3 py-2">Remaining Qty</th>
          <th class="border px-3 py-2">Unit Price</th>
          <th class="border px-3 py-2">Total Cost</th>
          <th class="border px-3 py-2">Status</th>
          <th class="border px-3 py-2">Date Added</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php
        if ($result->num_rows > 0) {
            $i = 1;
            while ($row = $result->fetch_assoc()) {
                echo "<tr class='hover:bg-red-50'>
                    <td class='border px-3 py-2 text-center'>{$i}</td>
                    <td class='border px-3 py-2'>" . htmlspecialchars($row['chemical_code']) . "</td>
                    <td class='border px-3 py-2 font-semibold'>" . htmlspecialchars($row['chemical_name']) . "</td>
                    <td class='border px-3 py-2'>" . htmlspecialchars($row['batch_no']) . "</td>
                    <td class='border px-3 py-2'>" . htmlspecialchars($row['rm_lot_no']) . "</td>
                    <td class='border px-3 py-2 text-right'>" . htmlspecialchars($row['original_quantity']) . "</td>
                    <td class='border px-3 py-2 text-right'>" . htmlspecialchars($row['remaining_quantity']) . "</td>
                    <td class='border px-3 py-2 text-right'>" . htmlspecialchars($row['unit_price']) . "</td>
                    <td class='border px-3 py-2 text-right'>" . htmlspecialchars($row['total_cost']) . "</td>
                    <td class='border px-3 py-2 text-center font-semibold text-red-600'>" . htmlspecialchars($row['status']) . "</td>
                    <td class='border px-3 py-2'>" . htmlspecialchars($row['date_added']) . "</td>
                </tr>";
                $i++;
            }
        } else {
            echo "<tr><td colspan='11' class='text-center text-gray-500 py-4'>No rejected chemicals found.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
