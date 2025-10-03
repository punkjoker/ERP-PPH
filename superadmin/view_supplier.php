<?php
include 'db_con.php';

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT s.*, p.product_name 
    FROM suppliers s 
    LEFT JOIN procurement_products p ON s.product_id = p.id
    WHERE s.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()): ?>
    <p><strong>Product:</strong> <?= $row['product_name'] ?: $row['manual_product'] ?></p>
    <p><strong>Type/Model/Brand:</strong> <?= $row['type_model_brand'] ?></p>
    <p><strong>Quantity:</strong> <?= $row['quantity'] ?></p>
    <p><strong>User of Product:</strong> <?= $row['user_of_product'] ?></p>
    <hr class="my-2">
    <p><strong>Supplier Name:</strong> <?= $row['supplier_name'] ?></p>
    <p><strong>Contact Info:</strong> <?= $row['supplier_contact'] ?></p>
    <p><strong>Price:</strong> <?= $row['price'] ?: 'N/A' ?></p>
    <p><strong>Payment Terms:</strong> <?= $row['payment_terms'] ?></p>
    <p><strong>Status:</strong> <?= ucfirst($row['status']) ?></p>
<?php else: ?>
    <p class="text-red-500">Supplier not found.</p>
<?php endif; ?>
