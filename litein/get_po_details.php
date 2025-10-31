<?php
require 'db_con.php';

$po_no = $_GET['po_no'] ?? '';

if (!$po_no) {
    echo json_encode(['success' => false]);
    exit;
}

// ✅ First, find the PO ID from `po_list`
$stmt = $conn->prepare("SELECT id FROM po_list WHERE po_no = ?");
$stmt->bind_param("s", $po_no);
$stmt->execute();
$res = $stmt->get_result();
$po = $res->fetch_assoc();

if (!$po) {
    echo json_encode(['success' => false, 'message' => 'PO not found']);
    exit;
}

$po_id = $po['id'];

// ✅ Now get the order item details for that PO
// (If multiple items exist, you may refine by chemical_code or manual_name later)
$query = $conn->prepare("
    SELECT quantity, unit, unit_price 
    FROM order_items 
    WHERE po_id = ? 
    LIMIT 1
");
$query->bind_param("i", $po_id);
$query->execute();
$result = $query->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'quantity' => (float)$row['quantity'],
        'unit' => $row['unit'],
        'unit_price' => (float)$row['unit_price']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No items found for this PO']);
}
?>
