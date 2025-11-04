<?php
include 'db_con.php';

$term = $_GET['term'] ?? '';
$data = [];

if (!empty($term)) {
    $stmt = $conn->prepare("SELECT id, name, product_code, category, description
                            FROM products
                            WHERE name LIKE CONCAT('%', ?, '%')
                            LIMIT 10");
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'value' => $row['name'],
            'product_code' => $row['product_code'],
            'category' => $row['category'],
            'description' => $row['description']
        ];
    }
}
echo json_encode($data);
?>
