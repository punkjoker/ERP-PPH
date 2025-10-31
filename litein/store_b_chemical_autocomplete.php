<?php
include 'db_con.php';

$term = $_GET['term'] ?? '';

$sql = "SELECT id, chemical_name, main_category, group_name, group_code, chemical_code, category
        FROM chemical_names
        WHERE main_category = 'Chemicals' AND chemical_name LIKE ?
        ORDER BY chemical_name ASC
        LIMIT 10";

$stmt = $conn->prepare($sql);
$likeTerm = "%$term%";
$stmt->bind_param('s', $likeTerm);
$stmt->execute();
$result = $stmt->get_result();

$suggestions = [];
while($row = $result->fetch_assoc()) {
    $suggestions[] = [
        'id' => $row['id'],
        'value' => $row['chemical_name'],
        'main_category' => $row['main_category'],
        'group_name' => $row['group_name'],
        'group_code' => $row['group_code'],
        'chemical_code' => $row['chemical_code'],
        'category' => $row['category']
    ];
}

echo json_encode($suggestions);
?>
