<?php
include 'db_con.php';

// Filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Base query
$query = "SELECT bom.id, bom.created_at, bom.requested_by, bom.description, bom.batch_number,
                 p.name AS product_name,
                 pr.status AS production_status
          FROM bill_of_materials bom
          JOIN products p ON bom.product_id = p.id
          LEFT JOIN production_runs pr ON pr.request_id = bom.id
          WHERE bom.status = 'Approved'";


// Apply filters
if ($from_date && $to_date) {
    $query .= " AND DATE(bom.created_at) BETWEEN '$from_date' AND '$to_date'";
}


if (!empty($status_filter)) {
    $query .= " AND pr.status = '$status_filter'";
}

$query .= " ORDER BY bom.created_at DESC";


$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Production Runs</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

  <?php include 'navbar.php'; ?>

  <div class="p-6 ml-64">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-lg">
      <h2 class="text-2xl font-bold mb-4 text-gray-800">Production Runs</h2>
      

      <!-- Filter Form -->
      <form method="GET" class="flex flex-wrap items-end gap-4 mb-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">From Date</label>
          <input type="date" name="from_date" value="<?= $from_date ?>" 
                 class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">To Date</label>
          <input type="date" name="to_date" value="<?= $to_date ?>" 
                 class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Status</label>
          <select name="status" class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
            <option value="">All</option>
            <option value="In Production" <?= $status_filter === 'In Production' ? 'selected' : '' ?>>In Production</option>
            <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
          </select>
        </div>
        <div>
          <button type="submit" 
                  class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition mt-2">Filter</button>
        </div>
        <a href="download_in_production.php?from_date=<?= $from_date ?>&to_date=<?= $to_date ?>&status=<?= $status_filter ?>"
   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
   Download CSV
</a>

      </form>

      <!-- Table -->
<div class="overflow-x-auto rounded-lg shadow-md">
  <table class="min-w-full text-xs border-collapse">
    <thead>
      <tr class="bg-gray-100 text-gray-700 uppercase tracking-wide">
        <th class="py-1 px-2 border">Date</th>
        <th class="py-1 px-2 border">Product Name</th>
        <th class="py-1 px-2 border">Batch #</th>
        <th class="py-1 px-2 border">Requested By</th>
        <th class="py-1 px-2 border">Description</th>
        <th class="py-1 px-2 border">Status</th>
        <th class="py-1 px-2 border text-center">Actions</th>
      </tr>
    </thead>
    <tbody class="text-gray-700">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <?php
            $prod_status = $row['production_status'] ?? 'In Production';
            $status_text = $prod_status === 'Completed'
                ? "<span class='text-green-600 font-semibold'>Completed</span>"
                : "<span class='text-yellow-600 font-semibold'>In Production</span>";
          ?>
          <tr class="bg-white shadow-sm hover:shadow-lg transition duration-150 rounded-sm">
            <td class="py-1 px-2 border"><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
            <td class="py-1 px-2 border"><?= htmlspecialchars($row['product_name']) ?></td>
            <td class="py-1 px-2 border"><?= htmlspecialchars($row['batch_number']) ?></td>
            <td class="py-1 px-2 border"><?= htmlspecialchars($row['requested_by']) ?></td>
            <td class="py-1 px-2 border truncate max-w-[200px]"><?= htmlspecialchars($row['description']) ?></td>
            <td class="py-1 px-2 border"><?= $status_text ?></td>
            <td class="py-[2px] px-2 border text-center">
              <div class="flex justify-center gap-2">
                <a href="update_production.php?id=<?= $row['id'] ?>" 
                   class="bg-green-500 text-white px-2 py-[1px] rounded hover:bg-green-600 text-[11px]">Update</a>
                <a href="view_production.php?id=<?= $row['id'] ?>" 
                   class="bg-blue-500 text-white px-2 py-[1px] rounded hover:bg-blue-600 text-[11px]">View</a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="py-2 px-3 text-center text-gray-500">No production runs found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

    </div>
  </div>

</body>
</html>
