<?php
session_start();
require 'db_con.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $product_code = trim($_POST['product_code']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);

    if (!empty($name) && !empty($category) && !empty($product_code)) {
        $stmt = $conn->prepare("INSERT INTO products (name, product_code, category, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $product_code, $category, $description);
        $stmt->execute();
        $stmt->close();
        $success = "✅ Product added successfully!";
    } else {
        $error = "⚠️ Please fill in all required fields.";
    }
}

// Handle search
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ? OR product_code LIKE ? ORDER BY id DESC");
    $searchTerm = "%$search%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query("SELECT * FROM products ORDER BY id DESC");
    $products = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>

    <div class="p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Add Product</h1>

        <!-- Alerts -->
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Add Product Form -->
        <div class="bg-white shadow-lg rounded p-6 mb-6">
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-semibold">Product Name *</label>
                    <input type="text" name="name" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300">
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold">Product Code *</label>
                    <input type="text" name="product_code" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300" placeholder="e.g., PRD001">
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold">Category *</label>
                    <input type="text" name="category" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300">
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold">Description</label>
                    <textarea name="description" rows="3" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300"></textarea>
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Add Product</button>
            </form>
        </div>

        <!-- Search Bar -->
        <div class="bg-white shadow-md rounded p-4 mb-6 flex justify-between items-center">
            <form method="GET" class="flex w-full">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or code..." class="border border-gray-300 rounded-l px-3 py-2 w-full focus:ring focus:ring-blue-300">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r hover:bg-blue-700">Search</button>
            </form>
        </div>

        <!-- Products List -->
<div class="bg-white shadow-lg rounded p-6">
  <h2 class="text-xl font-semibold mb-3 text-blue-700">Products List</h2>
  <table class="w-full border border-gray-200 rounded overflow-hidden">
    <thead class="bg-gray-100 text-gray-700 text-sm uppercase">
      <tr>
        <th class="border px-2 py-1 text-left">ID</th>
        <th class="border px-2 py-1 text-left">Product Name</th>
        <th class="border px-2 py-1 text-left">Product Code</th>
        <th class="border px-2 py-1 text-left">Category</th>
        <th class="border px-2 py-1 text-left">Description</th>
        <th class="border px-2 py-1 text-left">Date Added</th>
        <th class="border px-2 py-1 text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($products)): ?>
        <?php foreach ($products as $p): ?>
          <tr class="hover:bg-gray-50 shadow-sm transition duration-150 ease-in-out">
            <td class="border px-2 py-1 text-sm"><?= $p['id'] ?></td>
            <td class="border px-2 py-1 text-sm"><?= htmlspecialchars($p['name']) ?></td>
            <td class="border px-2 py-1 text-sm"><?= htmlspecialchars($p['product_code']) ?></td>
            <td class="border px-2 py-1 text-sm"><?= htmlspecialchars($p['category']) ?></td>
            <td class="border px-2 py-1 text-sm"><?= htmlspecialchars($p['description']) ?></td>
            <td class="border px-2 py-1 text-sm"><?= $p['created_at'] ?? '' ?></td>
            <td class="border px-2 py-1 text-center">
              <a href="update_bom.php?id=<?= $p['id'] ?>" 
                 class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs mr-1">Update BOM</a>
              <a href="view_bom_materials.php?id=<?= $p['id'] ?>" 
                 class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs">View BOM</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="border px-3 py-2 text-center text-gray-500">No products found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

    </div>
</body>
</html>
