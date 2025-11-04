<?php
require 'db_con.php';

$material_name = $_GET['material_name'] ?? '';
$response = ['chemical_code' => ''];

if ($material_name) {
    $stmt = $conn->prepare("SELECT chemical_code FROM chemical_names WHERE chemical_name = ? LIMIT 1");
    $stmt->bind_param("s", $material_name);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $response['chemical_code'] = $result['chemical_code'];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
