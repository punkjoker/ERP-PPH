<?php
require 'db_con.php';
$term = $_GET['term'] ?? '';

$data = [];

if (!empty($term)) {
    $stmt = $conn->prepare("SELECT DISTINCT m.material_name, i.po_no, i.quantity, i.unit_price 
                            FROM po_items i 
                            JOIN materials_list m ON i.material_id = m.id
                            WHERE m.material_name LIKE CONCAT('%', ?, '%')
                            ORDER BY m.material_name ASC");
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $name = $row['material_name'];
        if (!isset($data[$name])) $data[$name] = [];
        $data[$name][] = [
            'po_no' => $row['po_no'],
            'quantity' => $row['quantity'],
            'unit_price' => $row['unit_price']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>
