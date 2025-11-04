<?php
include 'db_con.php';

$term = $_GET['term'] ?? '';

$stmt = $conn->prepare("SELECT id, chemical_name, main_category, group_name, group_code, chemical_code, category 
                        FROM chemical_names 
                        WHERE main_category = 'Engineering Products' 
                        AND chemical_name LIKE ?");
$like = "%{$term}%";
$stmt->bind_param("s", $like);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'value' => $row['chemical_name'],
        'main_category' => $row['main_category'],
        'group_name' => $row['group_name'],
        'group_code' => $row['group_code'],
        'chemical_code' => $row['chemical_code'],
        'category' => $row['category']
    ];
}
echo json_encode($data);
?>
