<?php
require 'db_con.php';

$po_no = $_GET['po_number'] ?? '';

if ($po_no) {
    // ✅ Get PO ID from po_list
    $stmt = $conn->prepare("SELECT id FROM po_list WHERE po_no = ?");
    $stmt->bind_param("s", $po_no);
    $stmt->execute();
    $po = $stmt->get_result()->fetch_assoc();

    if ($po) {
        $po_id = $po['id'];

        // ✅ Fetch all order items for that PO
        $query = "
            SELECT 
                COALESCE(oi.manual_name, pp.product_name) AS item_name,
                oi.quantity, 
                oi.unit, 
                oi.unit_price
            FROM order_items oi
            LEFT JOIN procurement_products pp ON oi.product_id = pp.id
            WHERE oi.po_id = ?
            ORDER BY oi.id ASC
            LIMIT 1
        ";
        $stmt2 = $conn->prepare($query);
        $stmt2->bind_param("i", $po_id);
        $stmt2->execute();
        $item = $stmt2->get_result()->fetch_assoc();

        if ($item) {
            echo json_encode($item);
        } else {
            echo json_encode(['item_name' => '', 'quantity' => '', 'unit' => '', 'unit_price' => '']);
        }
    } else {
        echo json_encode(['item_name' => '', 'quantity' => '', 'unit' => '', 'unit_price' => '']);
    }
} else {
    echo json_encode(['item_name' => '', 'quantity' => '', 'unit' => '', 'unit_price' => '']);
}
?>
