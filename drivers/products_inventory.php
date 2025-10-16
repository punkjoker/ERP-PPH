<?php
include 'db_con.php';

// Handle search
$search = trim($_GET['search'] ?? '');
$sql = "SELECT id, name, category, remaining_quantity, pack_size, unit, created_at 
        FROM products 
        WHERE 1 ";

if (!empty($search)) {
    $search = "%$search%";
    $stmt = $conn->prepare("
        SELECT id, name, category, remaining_quantity, pack_size, unit, created_at 
        FROM products 
        WHERE name LIKE ? 
           OR remaining_quantity LIKE ? 
           OR pack_size LIKE ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $products = $stmt->get_result();
    $stmt->close();
} else {
    $products = $conn->query("
        SELECT id, name, category, remaining_quantity, pack_size, unit, created_at 
        FROM products 
        ORDER BY created_at DESC
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Product Inventory</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <?php include 'navbar.php'; ?>

  <div class="p-6 ml-64">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">📦 Products Inventory</h1>

    <!-- Search Form -->
    <form method="GET" class="mb-6 flex gap-3">
      <input 
        type="text" 
        name="search" 
        placeholder="Search by Product Name, Remaining Qty, or Pack Size..." 
        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
        class="w-1/2 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
      <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">Search</button>
      <?php if (!empty($search)): ?>
        <a href="products_inventory.php" class="ml-2 text-red-600 hover:underline self-center">Clear</a>
      <?php endif; ?>
    </form>

    <!-- Inventory Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
      <table class="min-w-full table-auto border-collapse">
        <thead class="bg-blue-600 text-white">
          <tr>
            <th class="py-3 px-4 text-left">#</th>
            <th class="py-3 px-4 text-left">Product Name</th>
            <th class="py-3 px-4 text-left">Category</th>
            <th class="py-3 px-4 text-left">Remaining Quantity</th>
            <th class="py-3 px-4 text-left">Pack Size</th>
            <th class="py-3 px-4 text-left">Date Added</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($products && $products->num_rows > 0): ?>
            <?php $i = 1; while ($row = $products->fetch_assoc()): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="py-3 px-4"><?= $i++; ?></td>
                <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($row['name']); ?></td>
                <td class="py-3 px-4 text-gray-700"><?= htmlspecialchars($row['category']); ?></td>
                <td class="py-3 px-4 text-gray-700"><?= number_format($row['remaining_quantity'], 2); ?></td>
                <td class="py-3 px-4 text-gray-700">
                  <?= number_format($row['pack_size'], 2) . ' ' . htmlspecialchars($row['unit']); ?>
                </td>
                <td class="py-3 px-4 text-gray-500"><?= htmlspecialchars(date("Y-m-d", strtotime($row['created_at']))); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center text-gray-500 py-6">No products found in inventory.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
