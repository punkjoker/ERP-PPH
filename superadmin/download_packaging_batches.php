<?php
require('fpdf.php');
include 'db_con.php';

$product_id = intval($_GET['product_id'] ?? 0);
if ($product_id <= 0) {
    die("Invalid product id.");
}

// Fetch product name
$productRes = $conn->query("SELECT name FROM products WHERE id = $product_id");
$product = $productRes ? $productRes->fetch_assoc() : null;
$productName = $product['name'] ?? 'Unknown Product';

// Fetch batches
$batches = $conn->query("
    SELECT 
        f.batch_number, 
        f.obtained_yield, 
        f.pack_size, 
        f.remaining_size, 
        f.unit, 
        f.packaged_quantity, 
        f.created_at,
        m.material_name
    FROM finished_products f
    LEFT JOIN materials m ON f.material_id = m.id
    WHERE f.product_id = $product_id
    ORDER BY f.created_at DESC
");

// PDF setup
class PDF extends FPDF
{
    function Header()
    {
        // Logo + Header
        $this->Image('images/lynn_logo.png', 10, 8, 25);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 7, 'PACKAGING BATCHES REPORT', 0, 1, 'C');
        $this->Ln(6);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Generated on ' . date('d M Y h:i A') . ' | Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function TableHeader()
    {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(200, 220, 255);
        $this->Cell(35, 8, 'Batch Number', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Obtained Yield', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Packed IN', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Pack Size', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Packaged Qty', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Remaining Packs', 1, 0, 'C', true);
        $this->Cell(37, 8, 'Date', 1, 1, 'C', true);
    }
}

// Create PDF
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();

// Product name title
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, "Product: " . $productName, 0, 1, 'L');
$pdf->Ln(3);

// Table header
$pdf->TableHeader();
$pdf->SetFont('Arial', '', 10);

if ($batches && $batches->num_rows > 0) {
    while ($b = $batches->fetch_assoc()) {
        $pdf->Cell(35, 8, $b['batch_number'], 1);
        $pdf->Cell(35, 8, number_format($b['obtained_yield'], 2) . ' ' . $b['unit'], 1);
        $pdf->Cell(40, 8, $b['material_name'] ?? 'N/A', 1);
        $pdf->Cell(35, 8, number_format($b['pack_size'], 2) . ' ' . $b['unit'], 1);
        $pdf->Cell(35, 8, number_format($b['packaged_quantity'], 2) . ' ' . $b['unit'], 1);
        $pdf->Cell(35, 8, number_format($b['remaining_size'], 0), 1);
        $pdf->Cell(37, 8, date('Y-m-d', strtotime($b['created_at'])), 1, 1);
    }
} else {
    $pdf->Cell(252, 10, 'No batches found for this product.', 1, 1, 'C');
}

$pdf->Output('D', 'Packaging_Batches_' . str_replace(' ', '_', $productName) . '.pdf');
exit;
?>
