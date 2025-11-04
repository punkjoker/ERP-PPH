<?php
include 'db_con.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($id > 0 && in_array($status, ['Pending', 'Delivered', 'Cancelled'])) {
        $stmt = $conn->prepare("UPDATE order_deliveries_store_b SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }

    echo "<script>alert('✅ Delivery status updated successfully!'); window.location.href='store_b_order_deliveries.php';</script>";
    exit;
}
?>
