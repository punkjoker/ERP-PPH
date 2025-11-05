<?php
require('fpdf.php');
include 'db_con.php';

// --- Filters ---
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// --- Query ---
$query = "
SELECT 
    bom.id, 
    bom.bom_date, 
    bom.requested_by, 
    bom.description,
    bom.batch_number,
    p.name AS product_name,
    pr.id AS production_run_id,
    pr.status AS production_status,

    COALESCE(
        MAX(CASE WHEN qi.qc_status = 'Approved Product' THEN 'Approved Product' END),
        'Not Inspected'
    ) AS qc_status,

    CASE
        WHEN COUNT(qmr.id) = 0 THEN 'Pending'
        WHEN SUM(CASE WHEN qmr.response = 'No' THEN 1 ELSE 0 END) > 0 THEN 'Not Approved'
        ELSE 'Approved'
    END AS quality_review_status,

    MAX(qmr.created_at) AS reviewed_at

FROM bill_of_materials bom
JOIN products p ON bom.product_id = p.id
LEFT JOIN production_runs pr ON pr.request_id = bom.id
LEFT JOIN qc_inspections qi ON qi.production_run_id = pr.id
LEFT JOIN quality_manager_review qmr ON qmr.production_run_id = pr.id

WHERE pr.id IN (
    SELECT DISTINCT production_run_id 
    FROM packaging 
    WHERE status = 'Approved'
)
AND pr.status = 'Completed'
";

if ($from_date && $to_date) {
    $query .= " AND bom.bom_date BETWEEN '$from_date' AND '$to_date'";
}

$query .= "
GROUP BY 
    bom.id, 
    bom.bom_date, 
    bom.requested_by, 
    bom.description, 
    bom.batch_number,
    p.name, 
    pr.status
ORDER BY bom.bom_date DESC
";

$result = $conn->query($query);
if (!$result) {
    die('Query Error: ' . $conn->error);
}

// --- PDF Setup ---
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// --- Logo & Header ---
$pdf->Image('images/lynn_logo.png', 10, 8, 25);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'QUALITY MANAGER REVIEW REPORT', 0, 1, 'C');
$pdf->Ln(5);

// --- Date Range ---
$pdf->SetFont('Arial', '', 10);
if ($from_date && $to_date) {
    $pdf->Cell(0, 6, "Report Period: $from_date to $to_date", 0, 1, 'C');
}
$pdf->Ln(5);

// --- Table Header ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(220, 230, 241);
$pdf->Cell(30, 10, 'Date', 1, 0, 'C', true);
$pdf->Cell(45, 10, 'Product Name', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'Batch Number', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Requested By', 1, 0, 'C', true);
$pdf->Cell(70, 10, 'Description', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'Inspection Status', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'Review Status', 1, 1, 'C', true);

// --- Table Content ---
$pdf->SetFont('Arial', '', 9);

while ($row = $result->fetch_assoc()) {
    $qc_status = $row['qc_status'];
    $review_status = $row['quality_review_status'];

    // Colors for statuses
    if ($qc_status === 'Approved Product') {
        $qc_color = [0, 153, 0]; // green
        $qc_text = 'Approved';
    } else {
        $qc_color = [204, 0, 0]; // red
        $qc_text = 'Not Inspected';
    }

    if ($review_status === 'Approved') {
        $rev_color = [0, 153, 0];
    } elseif ($review_status === 'Pending') {
        $rev_color = [255, 204, 0];
    } else {
        $rev_color = [128, 128, 128];
    }

    // Data cells
    $pdf->SetTextColor(0);
    $pdf->Cell(30, 8, $row['bom_date'], 1);
    $pdf->Cell(45, 8, $row['product_name'], 1);
    $pdf->Cell(35, 8, $row['batch_number'], 1);
    $pdf->Cell(40, 8, $row['requested_by'], 1);
    $pdf->Cell(70, 8, $row['description'], 1);

    // QC status with color
    $pdf->SetTextColor($qc_color[0], $qc_color[1], $qc_color[2]);
    $pdf->Cell(35, 8, $qc_text, 1, 0, 'C');

    // Review status with color
    $pdf->SetTextColor($rev_color[0], $rev_color[1], $rev_color[2]);
    $pdf->Cell(35, 8, $review_status, 1, 1, 'C');
}

// --- Footer ---
$pdf->Ln(10);
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R');

$pdf->Output('D', 'QC_Review_Report.pdf');
?>
