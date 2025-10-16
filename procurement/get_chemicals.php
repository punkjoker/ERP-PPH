<?php
require 'db_con.php';

header('Content-Type: application/json');

$result = $conn->query("SELECT id, chemical_name, chemical_code, category FROM chemical_names ORDER BY chemical_name ASC");

$chemicals = [];
while ($row = $result->fetch_assoc()) {
    $chemicals[] = $row;
}

echo json_encode($chemicals);
?>
