<?php
require 'db_con.php';

$po_number = $_GET['po_number'] ?? '';
$material_name = $_GET['material_name'] ?? '';

if ($po_number && $material_name) {
    $stmt = $conn->prepare("SELECT quantity, unit, cost FROM materials WHERE po_number = ? AND material_name = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("ss", $po_number, $material_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['quantity' => '', 'unit' => '', 'cost' => '']);
    }
} else {
    echo json_encode(['quantity' => '', 'unit' => '', 'cost' => '']);
}
?>
