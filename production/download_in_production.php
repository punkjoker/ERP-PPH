<?php
include 'db_con.php';

// Get filters from query string
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Base query
$query = "SELECT bom.created_at, p.name AS product_name, bom.batch_number, 
                 bom.requested_by, bom.description, 
                 COALESCE(pr.status, 'In Production') AS production_status
          FROM bill_of_materials bom
          JOIN products p ON bom.product_id = p.id
          LEFT JOIN production_runs pr ON pr.request_id = bom.id
          WHERE bom.status = 'Approved'";

// Apply filters
if ($from_date && $to_date) {
    $query .= " AND DATE(bom.created_at) BETWEEN '$from_date' AND '$to_date'";
}

if (!empty($status_filter)) {
    $query .= " AND pr.status = '$status_filter'";
}

$query .= " ORDER BY bom.created_at DESC";

$result = $conn->query($query);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=In_Production_List.csv');

// Output file pointer
$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, ['Date', 'Product Name', 'Batch Number', 'Requested By', 'Description', 'Status']);

// Add data rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            date('d M Y, h:i A', strtotime($row['created_at'])),
            $row['product_name'],
            $row['batch_number'],
            $row['requested_by'],
            $row['description'],
            $row['production_status']
        ]);
    }
} else {
    fputcsv($output, ['No production runs found']);
}

fclose($output);
exit;
?>
