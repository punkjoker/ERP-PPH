<?php
require 'db_con.php';
$term = $_GET['term'] ?? '';

$stmt = $conn->prepare("SELECT id, chemical_name, chemical_code, unit_price, remaining_quantity FROM chemicals_in WHERE chemical_name LIKE ? ORDER BY chemical_name ASC LIMIT 10");
$likeTerm = "%$term%";
$stmt->bind_param("s", $likeTerm);
$stmt->execute();
$result = $stmt->get_result();

$chemicals = [];
while ($row = $result->fetch_assoc()) {
  $chemicals[] = $row;
}

echo json_encode($chemicals);
?>
