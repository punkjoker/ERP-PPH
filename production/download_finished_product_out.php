<?php
require('fpdf.php');
include 'db_con.php';

// Capture filters
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// SQL query for Approved Finished Products
$query = "
SELECT 
    bom.id, 
    bom.created_at, 
    bom.requested_by, 
    bom.description, 
    bom.batch_number,
    p.name AS product_name,
    pr.status AS production_status,
    MAX(CASE WHEN qi.qc_status = 'Approved Product' THEN 'Approved Product' END) AS qc_status
FROM bill_of_materials bom
JOIN products p ON bom.product_id = p.id
LEFT JOIN production_runs pr ON pr.request_id = bom.id
LEFT JOIN qc_inspections qi ON qi.production_run_id = pr.id
WHERE pr.status = 'Completed'
";

if ($from_date && $to_date) {
    $query .= " AND bom.created_at BETWEEN '$from_date' AND '$to_date'";
}

$query .= "
GROUP BY 
    bom.id, 
    bom.created_at, 
    bom.requested_by, 
    bom.description,
    bom.batch_number, 
    p.name, 
    pr.status
HAVING qc_status = 'Approved Product'
ORDER BY bom.created_at DESC
";

$result = $conn->query($query);
if (!$result) {
    die('Database Error: ' . $conn->error);
}

// === FPDF Setup ===
class PDF extends FPDF {
    function Header() {
        // Company Logo
        $this->Image('images/lynn_logo.png', 10, 8, 25); 

        // Company Name Header
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0, 51, 153); // light blue tone
        $this->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(2);

        // Report Title
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'Approved Finished Products Report', 0, 1, 'C');
        $this->Ln(2);

        // Date
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(5);

        // Table Header
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(224, 235, 255); // light blue
        $this->Cell(30, 8, 'Date', 1, 0, 'C', true);
        $this->Cell(45, 8, 'Product Name', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Batch #', 1, 0, 'C', true); // Extended width
        $this->Cell(35, 8, 'Requested By', 1, 0, 'C', true);
        $this->Cell(75, 8, 'Description', 1, 0, 'C', true);
        $this->Cell(25, 8, 'QC Status', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape layout
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// === Table Content ===
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(30, 8, date('d M Y', strtotime($row['created_at'])), 1);
        $pdf->Cell(45, 8, utf8_decode($row['product_name']), 1);
        $pdf->Cell(35, 8, utf8_decode($row['batch_number']), 1);
        $pdf->Cell(35, 8, utf8_decode($row['requested_by']), 1);
        $pdf->Cell(75, 8, utf8_decode(substr($row['description'], 0, 65)), 1);
        $pdf->Cell(25, 8, 'Approved', 1, 1, 'C');
    }
} else {
    $pdf->Cell(245, 10, 'No approved finished products found for the selected period.', 1, 1, 'C');
}

// === Output PDF ===
$pdf->Output('D', 'Approved_Finished_Products.pdf');
exit;
?>
