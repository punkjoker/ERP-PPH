<?php
include 'db_con.php';


// Fetch procurement products
$products = $conn->query("SELECT id, product_name FROM procurement_products");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // If product_id is empty string, set it to null
    $product_id = !empty($_POST['product_id']) ? intval($_POST['product_id']) : null;
    $manual_product = !empty($_POST['manual_product']) ? $_POST['manual_product'] : null;
    $type_model_brand = $_POST['type_model_brand'] ?? null;
    $quantity = !empty($_POST['quantity']) ? intval($_POST['quantity']) : null;
    $user_of_product = $_POST['user_of_product'] ?? null;

    $supplier_name = $_POST['supplier_name'] ?? null;
    $supplier_contact = $_POST['supplier_contact'] ?? null;
    $price = !empty($_POST['price']) ? floatval($_POST['price']) : null;
    $payment_terms = $_POST['payment_terms'] ?? null;
    $status = $_POST['status'] ?? 'initial';

    // Prepare insert query
    $stmt = $conn->prepare("INSERT INTO suppliers 
        (product_id, manual_product, type_model_brand, quantity, user_of_product, 
         supplier_name, supplier_contact, price, payment_terms, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Bind parameters
    // i = int, s = string, d = decimal
    $stmt->bind_param(
        "ississsdss",
        $product_id,
        $manual_product,
        $type_model_brand,
        $quantity,
        $user_of_product,
        $supplier_name,
        $supplier_contact,
        $price,
        $payment_terms,
        $status
    );

    if (!$stmt->execute()) {
        die("Insert failed: " . $stmt->error);
    }
}

// Filter suppliers by product
// Filter suppliers by product
$filter_product = $_GET['filter_product'] ?? '';

// Build filter list including manual products too
$filterOptions = $conn->query("
    SELECT id AS value, product_name AS label, 'db' AS source
    FROM procurement_products
    UNION
    SELECT DISTINCT CONCAT('manual_', id) AS value, manual_product AS label, 'manual' AS source
    FROM suppliers
    WHERE manual_product IS NOT NULL AND manual_product <> ''
    ORDER BY label
");

// Build WHERE condition
$where = "";
if ($filter_product) {
    if (strpos($filter_product, 'manual_') === 0) {
        // Manual product
        $manualId = str_replace('manual_', '', $filter_product);
        $where = "WHERE s.id = " . intval($manualId);
    } else {
        // DB product
        $where = "WHERE s.product_id = " . intval($filter_product);
    }
}

$suppliers = $conn->query("
    SELECT s.*, p.product_name 
    FROM suppliers s 
    LEFT JOIN procurement_products p ON s.product_id = p.id
    $where
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Supplier List</title>
</head>
<body class="bg-gray-50">
  <?php include 'navbar.php'; ?>
<div class="p-6 sm:ml-64">
    <!-- QMS Header -->
    <div class="bg-gray-100 border border-gray-300 rounded-lg p-4 mb-6">
        <p><strong>EFFECTIVE DATE:</strong> 15/07/2025 &nbsp;&nbsp; 
           <strong>ISSUE DATE:</strong> 14/07/2025 &nbsp;&nbsp; 
           <strong>REVIEW DATE:</strong> 15/07/2028</p>
        <p><strong>LYNNTECH CHEMICAL AND EQUIPMENT LIMITED.</strong></p>
        <p><strong>QUALITY MANAGEMENT SYSTEM (QMS)</strong></p>
        <p><strong>MANUAL NO:</strong> LYNNTECH-QM-02 &nbsp;&nbsp; 
           <strong>ISSUE NO:</strong> 001 &nbsp;&nbsp; 
           <strong>REVISION NO:</strong> 00 &nbsp;&nbsp; 
           <strong>Page:</strong> 1 of 2</p>
        <p><strong>DOCUMENT NO:</strong> LYNNTECH-QP-09</p>
        <p><strong>TITLE:</strong> TECHNICAL SPECIFICATION AND JUSTIFICATION FOR PROCUREMENT QF-12 B</p>
    </div>


    <!-- Form Section -->       
    <form method="POST" class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-lg font-bold mb-4">Technical Specification & Justification</h2>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm">Select Product</label>
                <select name="product_id" class="w-full border rounded p-2">
                    <option value="">-- Select Product --</option>
                    <?php while($row = $products->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= $row['product_name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm">Or Enter Product Name</label>
                <input type="text" name="manual_product" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block text-sm">Type/Model/Brand</label>
                <input type="text" name="type_model_brand" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block text-sm">Quantity</label>
                <input type="number" name="quantity" class="w-full border rounded p-2">
            </div>
            <div class="col-span-2">
                <label class="block text-sm">Who Will Use the Product</label>
                <input type="text" name="user_of_product" class="w-full border rounded p-2">
            </div>
        </div>

        <h2 class="text-lg font-bold mt-6 mb-4">Supplier Details</h2>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm">Supplier Name</label>
                <input type="text" name="supplier_name" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block text-sm">Contact Info</label>
                <input type="text" name="supplier_contact" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block text-sm">Price</label>
                <input type="number" step="0.01" name="price" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block text-sm">Payment Terms</label>
                <input type="text" name="payment_terms" class="w-full border rounded p-2">
            </div>
            <div class="col-span-2">
                <label class="block text-sm">Status</label>
                <select name="status" class="w-full border rounded p-2">
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                    <option value="initial">Out of Stock</option>
                </select>
            </div>
        </div>

        <button type="submit" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded">Save Supplier</button>
    </form>

    <!-- Filter -->
    <!-- Filter -->
<form method="GET" class="mb-4">
    <label class="block text-sm">Filter by Product</label>
    <select name="filter_product" class="border rounded p-2" onchange="this.form.submit()">
        <option value="">All Products</option>
        <?php while($row = $filterOptions->fetch_assoc()): ?>
            <option value="<?= $row['value'] ?>" <?= $filter_product == $row['value'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['label']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</form>

    <!-- Supplier List -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">
        <table class="min-w-full border border-gray-300">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border p-2">Product</th>
                    <th class="border p-2">Supplier</th>
                    <th class="border p-2">Contact</th>
                    <th class="border p-2">Price</th>
                    <th class="border p-2">Terms</th>
                    <th class="border p-2">Status</th>
                    <th class="border p-2">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $suppliers->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-100">
                        <td class="border p-2"><?= $row['product_name'] ?: $row['manual_product'] ?></td>
                        <td class="border p-2"><?= $row['supplier_name'] ?></td>
                        <td class="border p-2"><?= $row['supplier_contact'] ?></td>
                        <td class="border p-2"><?= $row['price'] ?></td>
                        <td class="border p-2"><?= $row['payment_terms'] ?></td>
                        <td class="border p-2">
    <?php
    $status = strtolower($row['status']);
    $statusClass = '';

    if ($status === 'available') {
        $statusClass = 'bg-green-100 text-green-700 border border-green-300';
    } elseif ($status === 'unavailable') {
        $statusClass = 'bg-orange-100 text-orange-700 border border-orange-300';
    } elseif ($status === 'out of stock') {
        $statusClass = 'bg-red-100 text-red-700 border border-red-300';
    } else {
        $statusClass = 'bg-gray-100 text-gray-700 border border-gray-300';
    }
    ?>
    <span class="px-2 py-1 rounded text-sm font-semibold <?= $statusClass ?>">
        <?= ucfirst($row['status']) ?>
    </span>
</td>

                        <td class="border p-2">
                            <button class="bg-green-500 text-white px-2 py-1 rounded" onclick="openViewModal(<?= $row['id'] ?>)">View</button>
                            <button class="bg-yellow-500 text-white px-2 py-1 rounded" onclick="openEditModal(<?= $row['id'] ?>)">Edit</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View / Edit Modals -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
  <div class="bg-white p-6 rounded shadow-lg w-1/2">
    <h2 class="text-lg font-bold mb-4">Supplier Details</h2>
    <div id="viewContent"></div>
    <button onclick="closeModal('viewModal')" class="mt-4 bg-red-500 text-white px-4 py-2 rounded">Close</button>
  </div>
</div>

<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
  <div class="bg-white p-6 rounded shadow-lg w-1/2">
    <h2 class="text-lg font-bold mb-4">Edit Supplier</h2>
    <div id="editContent"></div>
    <button onclick="closeModal('editModal')" class="mt-4 bg-gray-500 text-white px-4 py-2 rounded">Close</button>
  </div>
</div>

<script>
function openViewModal(id) {
    // AJAX fetch details
    fetch('view_supplier.php?id=' + id)
      .then(res => res.text())
      .then(html => {
        document.getElementById('viewContent').innerHTML = html;
        document.getElementById('viewModal').classList.remove('hidden');
      });
}
function openEditModal(id) {
    fetch('edit_supplier.php?id=' + id)
      .then(res => res.text())
      .then(html => {
        document.getElementById('editContent').innerHTML = html;
        document.getElementById('editModal').classList.remove('hidden');
      });
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
</script>
