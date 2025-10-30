<?php
include 'db_con.php';

$term = $_GET['term'] ?? '';

$stmt = $conn->prepare("SELECT * FROM chemical_names WHERE chemical_name LIKE CONCAT('%', ?, '%') LIMIT 10");
$stmt->bind_param("s", $term);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while($row = $result->fetch_assoc()) {
    $suggestions[] = [
        'id' => $row['id'],
        'label' => $row['chemical_name'],   // what shows in dropdown
        'value' => $row['chemical_name'],   // what fills input
        'main_category' => $row['main_category'],
        'group_name' => $row['group_name'],
        'group_code' => $row['group_code'],
        'chemical_code' => $row['chemical_code'],
        'category' => $row['category']
    ];
}

header('Content-Type: application/json');
echo json_encode($suggestions);
?>
