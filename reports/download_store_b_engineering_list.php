<?php
require('fpdf.php');
include 'db_con.php';

// Handle search filter
$search = $_GET['search'] ?? '';
$search_sql = "";
$title_suffix = "";

if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $search_sql = " AND (cn.chemical_name LIKE '%$search_safe%' OR cn.chemical_code LIKE '%$search_safe%') ";
    $title_suffix = " - Filtered by: $search_safe";
}

// Fetch data
$sql = "SELECT 
            cn.chemical_name AS product_name,
            cn.group_name,
            cn.category,
            cn.chemical_code AS product_code,
            IFNULL(SUM(sep.remaining_quantity), 0) AS total_remaining
        FROM chemical_names cn
        LEFT JOIN store_b_engineering_products_in sep
        ON cn.chemical_code = sep.product_code
        WHERE cn.main_category = 'Engineering products' $search_sql
        GROUP BY cn.chemical_code, cn.chemical_name, cn.group_name, cn.category
        ORDER BY cn.chemical_name ASC";

$result = $conn->query($sql);

// PDF setup
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// --- Header ---
$pdf->Image('images/lynn_logo.png', 10, 8, 25);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, 'Store B Engineering Products Inventory List' . $title_suffix, 0, 1, 'C');
$pdf->Ln(5);

// --- Table Header ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(200, 220, 255);
$headers = ['Product Name', 'Group', 'Category', 'Remaining Quantity'];
$widths  = [100, 60, 60, 40];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// --- Table Rows ---
$pdf->SetFont('Arial', '', 8);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell($widths[0], 8, substr($row['product_name'], 0, 40), 1);
        $pdf->Cell($widths[1], 8, $row['group_name'], 1);
        $pdf->Cell($widths[2], 8, $row['category'], 1);
        $pdf->Cell($widths[3], 8, $row['total_remaining'], 1, 0, 'R');
        $pdf->Ln();
    }
} else {
    $pdf->Cell(array_sum($widths), 10, 'No engineering products found.', 1, 1, 'C');
}

// Footer
$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 8, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'R');

// Output PDF
$pdf->Output('D', 'StoreB_Engineering_Inventory_List.pdf');
?>
