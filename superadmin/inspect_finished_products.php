<?php
include 'db_con.php';

// Date filter
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// ✅ Fetch only completed production runs
$query = "SELECT bom.id, bom.bom_date, bom.requested_by, bom.description, 
                 p.name AS product_name,
                 pr.status AS production_status,
                 COALESCE(pr.qc_status, 'Not Inspected') AS qc_status
          FROM bill_of_materials bom
          JOIN products p ON bom.product_id = p.id
          LEFT JOIN production_runs pr ON pr.request_id = bom.id
          WHERE pr.status = 'Completed'";

if ($from_date && $to_date) {
    $query .= " AND bom.bom_date BETWEEN '$from_date' AND '$to_date'";
}

$query .= " ORDER BY bom.bom_date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inspect Finished Products</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

  <!-- Navbar -->
  <?php include 'navbar.php'; ?>

  <!-- Page Content -->
  <div class="p-6 ml-64">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-lg">
      <h2 class="text-2xl font-bold mb-4 text-gray-800">Inspect Finished Products</h2>

      <!-- Filter Form -->
      <form method="GET" class="flex space-x-4 mb-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">From Date</label>
          <input type="date" name="from_date" value="<?php echo $from_date; ?>" class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">To Date</label>
          <input type="date" name="to_date" value="<?php echo $to_date; ?>" class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
        </div>
        <div class="flex items-end">
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
        </div>
      </form>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 text-sm">
          <thead>
            <tr class="bg-gray-100 text-gray-700 uppercase text-xs">
              <th class="py-2 px-3 border">Date</th>
              <th class="py-2 px-3 border">Product Name</th>
              <th class="py-2 px-3 border">Requested By</th>
              <th class="py-2 px-3 border">Description</th>
              <th class="py-2 px-3 border">QC Status</th>
              <th class="py-2 px-3 border">Actions</th>
            </tr>
          </thead>
          <tbody class="text-gray-700">
            <?php while ($row = $result->fetch_assoc()): ?>
              <?php
                $qc_status = $row['qc_status'];
                if ($qc_status === 'Inspected') {
                    $qc_text = "<span class='text-green-600 font-semibold'>Inspected</span>";
                } else {
                    $qc_text = "<span class='text-red-600 font-semibold'>Not Inspected</span>";
                }
              ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="py-2 px-3 border"><?php echo htmlspecialchars($row['bom_date']); ?></td>
                <td class="py-2 px-3 border"><?php echo htmlspecialchars($row['product_name']); ?></td>
                <td class="py-2 px-3 border"><?php echo htmlspecialchars($row['requested_by']); ?></td>
                <td class="py-2 px-3 border"><?php echo htmlspecialchars($row['description']); ?></td>
                <td class="py-2 px-3 border"><?php echo $qc_text; ?></td>
                <td class="py-2 px-3 border space-x-2">
                  <a href="update_qc_status.php?id=<?php echo $row['id']; ?>" 
                     class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 text-xs">Update Status</a>
                  <a href="view_finished_product.php?id=<?php echo $row['id']; ?>" 
                     class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-xs">View</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</body>
</html>
