<?php
include 'db_con.php';

$id = intval($_GET['id'] ?? 0);

// Handle form update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?: null;
    $manual_product = $_POST['manual_product'] ?: null;
    $type_model_brand = $_POST['type_model_brand'] ?: null;
    $quantity = $_POST['quantity'] !== '' ? intval($_POST['quantity']) : null;
    $user_of_product = $_POST['user_of_product'] ?: null;

    $supplier_name = $_POST['supplier_name'] ?: null;
    $supplier_contact = $_POST['supplier_contact'] ?: null;
    $price = ($_POST['price'] !== '' && $_POST['price'] !== null) ? $_POST['price'] : null;
    $payment_terms = $_POST['payment_terms'] ?: null;
    $status = $_POST['status'] ?: 'initial';

    $stmt = $conn->prepare("UPDATE suppliers 
        SET product_id=?, manual_product=?, type_model_brand=?, quantity=?, user_of_product=?, 
            supplier_name=?, supplier_contact=?, price=?, payment_terms=?, status=?
        WHERE id=?");

    $stmt->bind_param(
        "ississsdssi",
        $product_id,
        $manual_product,
        $type_model_brand,
        $quantity,
        $user_of_product,
        $supplier_name,
        $supplier_contact,
        $price,
        $payment_terms,
        $status,
        $id
    );

    if ($stmt->execute()) {
        echo "<p class='text-green-600'>Supplier updated successfully. <a href='supplier_list.php' class='underline text-blue-600'>Back to list</a></p>";
    } else {
        echo "<p class='text-red-600'>Update failed: " . $stmt->error . "</p>";
    }
}

// Load existing supplier
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()):
?>

<form method="POST" class="bg-white p-6 rounded shadow-md max-w-3xl mx-auto space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm">Manual Product</label>
            <input type="text" name="manual_product" value="<?= htmlspecialchars($row['manual_product']) ?>" class="w-full border rounded p-2">
        </div>
        <div>
            <label class="block text-sm">Type/Model/Brand</label>
            <input type="text" name="type_model_brand" value="<?= htmlspecialchars($row['type_model_brand']) ?>" class="w-full border rounded p-2">
        </div>
        <div>
            <label class="block text-sm">Quantity</label>
            <input type="number" name="quantity" value="<?= htmlspecialchars($row['quantity']) ?>" class="w-full border rounded p-2">
        </div>
        <div>
            <label class="block text-sm">User of Product</label>
            <input type="text" name="user_of_product" value="<?= htmlspecialchars($row['user_of_product']) ?>" class="w-full border rounded p-2">
        </div>
        <div>
            <label class="block text-sm">Supplier Name</label>
            <input type="text" name="supplier_name" value="<?= htmlspecialchars($row['supplier_name']) ?>" class="w-full border rounded p-2">
        </div>
        <div>
            <label class="block text-sm">Contact Info</label>
            <input type="text" name="supplier_contact" value="<?= htmlspecialchars($row['supplier_contact']) ?>" class="w-full border rounded p-2">
        </div>
        <div>
            <label class="block text-sm">Price</label>
            <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($row['price']) ?>" class="w-full border rounded p-2">
        </div>
        <div>
            <label class="block text-sm">Payment Terms</label>
            <input type="text" name="payment_terms" value="<?= htmlspecialchars($row['payment_terms']) ?>" class="w-full border rounded p-2">
        </div>
        <div class="col-span-2">
            <label class="block text-sm">Status</label>
            <select name="status" class="w-full border rounded p-2">
                <option value="available" <?= $row['status'] == 'available' ? 'selected' : '' ?>>Available</option>
                <option value="unavailable" <?= $row['status'] == 'unavailable' ? 'selected' : '' ?>>Unavailable</option>
                <option value="initial" <?= $row['status'] == 'initial' ? 'selected' : '' ?>>Initial</option>
                <option value="out of stock" <?= $row['status'] == 'out of stock' ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>
    </div>
    <div class="flex gap-4">
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Update</button>
        <a href="supplier_list.php" class="bg-gray-500 text-white px-4 py-2 rounded">Back</a>
    </div>
</form>

<?php else: ?>
    <p class="text-red-500">Supplier not found.</p>
<?php endif; ?>
