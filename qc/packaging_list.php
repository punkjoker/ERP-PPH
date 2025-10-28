<?php
include 'db_con.php';

// Date filter
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// ✅ Fetch approved QC items that are pending or done with packaging
$query = "
SELECT 
    bom.id, 
    bom.created_at, 
    bom.requested_by, 
    bom.description, 
    p.name AS product_name,
    pr.status AS production_status,
    qi.qc_status,
    COALESCE(pkg.overall_status, 'Pending') AS packaging_status
FROM bill_of_materials bom
JOIN products p ON bom.product_id = p.id
LEFT JOIN production_runs pr ON pr.request_id = bom.id
LEFT JOIN qc_inspections qi ON qi.production_run_id = pr.id
LEFT JOIN (
    SELECT 
        production_run_id,
        CASE
            WHEN SUM(status='Rejected') > 0 THEN 'Rejected'
            WHEN SUM(status='Pending') > 0 THEN 'In Progress'
            WHEN SUM(status='Approved') = COUNT(*) THEN 'Completed'
            ELSE 'Pending'
        END AS overall_status
    FROM packaging
    GROUP BY production_run_id
) pkg ON pkg.production_run_id = pr.id
WHERE pr.status = 'Completed' 
  AND qi.qc_status = 'Approved Product'
";


// ✅ Add date filter if applied
if ($from_date && $to_date) {
    $query .= " AND bom.created_at BETWEEN '$from_date' AND '$to_date'";
}

$query .= " ORDER BY bom.created_at DESC";

$result = $conn->query($query);
if (!$result) {
    die('Query Error: ' . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Packaging List</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <style>
    table tr:nth-child(even) { background-color: #f9fafb; } /* light shading */
    table tr:nth-child(odd) { background-color: #ffffff; }
  </style>
</head>
<body class="bg-gray-100">

  <!-- Navbar -->
  <?php include 'navbar.php'; ?>

  <!-- Page Content -->
  <div class="p-6 ml-64">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-md">
      <h2 class="text-2xl font-bold mb-4 text-gray-800">Packaging List (QC Approved)</h2>

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
        <table class="min-w-full border border-gray-200 text-xs">
          <thead>
            <tr class="bg-gray-200 text-gray-700 uppercase">
              <th class="py-2 px-3 border">Date</th>
              <th class="py-2 px-3 border">Product Name</th>
              <th class="py-2 px-3 border">Requested By</th>
              <th class="py-2 px-3 border">Description</th>
              <th class="py-2 px-3 border">Packaging Status</th>
              <th class="py-2 px-3 border">Actions</th>
            </tr>
          </thead>
          <tbody class="text-gray-700">
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-blue-50 transition">
                  <td class="py-1 px-3 border"><?php echo htmlspecialchars($row['created_at']); ?></td>
                  <td class="py-1 px-3 border"><?php echo htmlspecialchars($row['product_name']); ?></td>
                  <td class="py-1 px-3 border"><?php echo htmlspecialchars($row['requested_by']); ?></td>
                  <td class="py-1 px-3 border"><?php echo htmlspecialchars($row['description']); ?></td>
                  
                  <td class="py-1 px-3 border text-center">
                    <?php 
                      if ($row['packaging_status'] == 'Completed') {
                        echo "<span class='text-green-600 font-semibold'>Completed</span>";
                      } elseif ($row['packaging_status'] == 'In Progress') {
                        echo "<span class='text-yellow-600 font-semibold'>In Progress</span>";
                      } else {
                        echo "<span class='text-gray-500 italic'>Pending</span>";
                      }
                    ?>
                  </td>

                  <td class="py-1 px-3 border space-x-1 text-center">
  <?php if ($row['packaging_status'] === 'Completed'): ?>
    <button 
      class="bg-gray-400 text-white px-3 py-1 rounded text-xs cursor-not-allowed opacity-70" 
      disabled>
      Updated
    </button>
  <?php else: ?>
    <a href="update_packaging.php?id=<?php echo $row['id']; ?>" 
       class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-xs">
       Update Packaging
    </a>
  <?php endif; ?>
</td>

                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-3 text-gray-500">No products ready for packaging.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</body>
</html>
