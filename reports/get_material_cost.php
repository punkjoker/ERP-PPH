<?php
require 'db_con.php';
$id = intval($_GET['id'] ?? 0);

$data = ['cost' => 0, 'quantity' => 0];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT cost, quantity FROM materials WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $data = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>
