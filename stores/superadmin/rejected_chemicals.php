<?php
session_start();
require 'db_con.php';

// Fetch rejected chemicals
$sql = "SELECT * FROM rejected_chemicals_in ORDER BY date_added DESC";
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
