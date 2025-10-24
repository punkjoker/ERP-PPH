<?php
include 'db_con.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') { echo ''; exit; }
$qLike = "%$q%";

$stmt = $conn->prepare("SELECT id, company_name, address FROM delivery_details WHERE company_name LIKE ? OR address LIKE ? ORDER BY company_name LIMIT 15");
$stmt->bind_param("ss", $qLike, $qLike);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo '<div class="p-2 text-sm text-gray-500">No companies found</div>';
    exit;
}

while ($row = $res->fetch_assoc()) {
    $label = htmlspecialchars($row['company_name'] . ($row['address'] ? " — " . $row['address'] : ''));
    $id = intval($row['id']);
    echo "<div class='autocomplete-item company-item' data-id='{$id}'>{$label}</div>";
}
$stmt->close();
