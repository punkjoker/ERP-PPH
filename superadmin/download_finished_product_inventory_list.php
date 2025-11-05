<?php
require('fpdf.php');
require('db_con.php');

$search = $_GET['search'] ?? '';
$search_sql = "";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $search_sql = " AND (p.name LIKE '%$search_safe%' OR p.product_code LIKE '%$search_safe%') ";
}

class PDF extends FPDF
{
    function Header()
    {
        // Logo + Header
        $this->Image('images/lynn_logo.png', 10, 8, 20);
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 8, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(3);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Store B Finished Products Inventory List', 0, 1, 'C');
        $this->Cell(0, 6, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(6);

        // Table headers
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(200, 220, 255);
        $this->Cell(50, 8, 'Product Name', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Category', 1, 0, 'C', true);
        $this->Cell(60, 8, 'Description', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Remaining Qty', 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
}

// Fetch data
$sql = "SELECT 
            p.name AS product_name,
            p.category,
            p.description,
            IFNULL(SUM(sfp.remaining_quantity), 0) AS total_remaining
        FROM products p
        LEFT JOIN store_b_finished_products_in sfp
        ON p.product_code = sfp.product_code
        WHERE 1 $search_sql
        GROUP BY p.product_code, p.name, p.category, p.description
        ORDER BY p.name ASC";

$result = $conn->query($sql);

// Generate PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(50, 8, $row['product_name'], 1);
        $pdf->Cell(40, 8, $row['category'], 1);
        $pdf->Cell(60, 8, substr($row['description'], 0, 50), 1);
        $pdf->Cell(30, 8, $row['total_remaining'], 1, 1, 'C');
    }
} else {
    $pdf->Cell(0, 10, 'No finished products found.', 1, 1, 'C');
}

$pdf->Output('D', 'Finished_Product_Inventory_List.pdf');
?>
