<?php
include 'db_con.php';

// Handle filters
$where = "1=1";
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where .= " AND (p.po_no LIKE '%$search%' OR s.supplier_name LIKE '%$search%')";
}
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $start = $conn->real_escape_string($_GET['start_date']);
    $end   = $conn->real_escape_string($_GET['end_date']);
    $where .= " AND DATE(p.created_at) BETWEEN '$start' AND '$end'";
}

// Pagination
$perPage = intval($_GET['per_page'] ?? 10);
$page = intval($_GET['page'] ?? 1);
$offset = ($page - 1) * $perPage;

$totalQry = $conn->query("SELECT COUNT(*) as total 
    FROM po_list p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    WHERE $where");
$totalRows = $totalQry->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Main query
$qry = $conn->query("
    SELECT p.*, s.supplier_name, d.expected_delivery, d.delivered_date, d.status as delivery_status
    FROM po_list p
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN deliveries d ON p.id = d.po_id
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT $offset, $perPage
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Delivery Of Purchases</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 pt-20">
<div class="p-6 sm:ml-64">
  <?php include 'navbar.php'; ?>

  <div class="bg-white shadow rounded-lg p-6">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-semibold text-gray-700">📦 Delivery Of Purchases</h3>
      
    </div>

    <!-- Filters -->
    <form method="get" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      <input type="text" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>"
             placeholder="Search by PO # or Supplier..."
             class="px-3 py-2 border rounded shadow-sm w-full focus:ring focus:ring-blue-300">

      <input type="date" name="start_date" value="<?= isset($_GET['start_date']) ? $_GET['start_date'] : '' ?>"
             class="px-3 py-2 border rounded shadow-sm w-full focus:ring focus:ring-blue-300">

      <input type="date" name="end_date" value="<?= isset($_GET['end_date']) ? $_GET['end_date'] : '' ?>"
             class="px-3 py-2 border rounded shadow-sm w-full focus:ring focus:ring-blue-300">

      <select name="per_page" class="px-3 py-2 border rounded shadow-sm w-full focus:ring focus:ring-blue-300">
        <?php foreach([10,20,50] as $size): ?>
            <option value="<?= $size ?>" <?= $perPage == $size ? 'selected' : '' ?>>View <?= $size ?> rows</option>
        <?php endforeach; ?>
      </select>

      <div class="md:col-span-4 flex gap-2 mt-2">
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">Filter</button>
        <a href="delivered_purchases.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded shadow">Reset</a>
      </div>
    </form>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
        <thead class="bg-gray-200 text-gray-700">
          <tr>
            <th class="px-3 py-2 text-left">#</th>
            <th class="px-3 py-2 text-left">PO #</th>
            <th class="px-3 py-2 text-left">Supplier</th>
            <th class="px-3 py-2 text-left">Date</th>
            <th class="px-3 py-2 text-left">Expected Delivery</th>
            <th class="px-3 py-2 text-left">Delivered Date</th>
            <th class="px-3 py-2 text-center">Delivery Status</th>
            <th class="px-3 py-2 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($qry->num_rows > 0): ?>
            <?php $rowNum = $offset + 1; ?>
            <?php while($row = $qry->fetch_assoc()): ?>
              <tr class="<?= $rowNum % 2 == 0 ? 'bg-white' : 'bg-gray-50' ?> hover:bg-gray-100">
                <td class="px-3 py-2 text-gray-700"><?= $rowNum++ ?></td>
                <td class="px-3 py-2 font-medium"><?= $row['po_no'] ?></td>
                <td class="px-3 py-2"><?= $row['supplier_name'] ?></td>
                <td class="px-3 py-2"><?= date("Y-m-d", strtotime($row['created_at'])) ?></td>
                <td class="px-3 py-2"><?= $row['expected_delivery'] ?? '-' ?></td>
                <td class="px-3 py-2"><?= $row['delivered_date'] ?? '-' ?></td>
                <td class="px-3 py-2 text-center">
                  <?php
                    $status = $row['delivery_status'] ?? 0;
                    switch($status){
                      case 1: echo "<span class='px-2 py-1 bg-green-100 text-green-700 rounded text-xs'>Delivered</span>"; break;
                      default: echo "<span class='px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs'>Pending</span>"; break;
                    }
                  ?>
                </td>
                <td class="px-3 py-2 text-center space-x-1">
                  <a href="view_delivery.php?id=<?= $row['id'] ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">View</a>
                  <a href="update_delivery.php?id=<?= $row['id'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs">Update</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="px-4 py-4 text-center text-gray-500">No purchases found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="flex justify-between items-center mt-4">
      <div class="text-gray-600">Page <?= $page ?> of <?= $totalPages ?></div>
      <div class="space-x-1">
        <?php for($i=1; $i<=$totalPages; $i++): ?>
          <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>" 
             class="px-3 py-1 rounded <?= $i==$page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
             <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    </div>

  </div>
</div>
</body>
</html>
