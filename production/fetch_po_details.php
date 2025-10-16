<?php
include 'db_con.php';
$po_no = $_GET['po_no'] ?? '';
$chem = $_GET['chemical_name'] ?? '';

$data = ['quantity' => 0, 'unit_price' => 0];
if ($po_no && $chem) {
    $qry = $conn->query("
        SELECT o.quantity, o.unit_price
        FROM order_items o
        LEFT JOIN procurement_products p ON o.product_id = p.id
        LEFT JOIN po_list po ON po.id = o.po_id
        WHERE po.po_no = '$po_no' AND (p.product_name = '$chem' OR o.manual_name = '$chem')
        LIMIT 1
    ");
    if ($qry && $qry->num_rows > 0) {
        $data = $qry->fetch_assoc();
    }
}
echo json_encode($data);
?>
