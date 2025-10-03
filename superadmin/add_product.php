<?php
session_start();
require 'db_con.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);

    if (!empty($name) && !empty($category)) {
        $stmt = $conn->prepare("INSERT INTO products (name, category, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $category, $description);
        $stmt->execute();
        $stmt->close();
        $success = "Product added successfully!";
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Fetch products
$result = $conn->query("SELECT * FROM products ORDER BY id DESC");
$products = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Add Product</h1>

        <!-- Success/Error messages -->
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

        <!-- Products List -->
        <div class="bg-white shadow-lg rounded p-6">
            <h2 class="text-xl font-semibold mb-3 text-blue-700">Products List</h2>
            <table class="w-full border border-gray-300 rounded">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="border px-3 py-2 text-left">ID</th>
                        <th class="border px-3 py-2 text-left">Name</th>
                        <th class="border px-3 py-2 text-left">Category</th>
                        <th class="border px-3 py-2 text-left">Description</th>
                        <th class="border px-3 py-2 text-left">Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="border px-3 py-2"><?= $p['id'] ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($p['name']) ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($p['category']) ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($p['description']) ?></td>
                                <td class="border px-3 py-2"><?= $p['created_at'] ?? '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="border px-3 py-2 text-center text-gray-500">No products added yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
