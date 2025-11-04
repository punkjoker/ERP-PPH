<?php
require 'db_con.php';

$q = $_GET['q'] ?? '';
if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT DISTINCT po_no FROM po_list WHERE po_no LIKE CONCAT('%', ?, '%') AND status = 1 LIMIT 10");
$stmt->bind_param("s", $q);
$stmt->execute();
$result = $stmt->get_result();

$po_numbers = [];
while ($row = $result->fetch_assoc()) {
    $po_numbers[] = $row['po_no'];
}

echo json_encode($po_numbers);
?>
