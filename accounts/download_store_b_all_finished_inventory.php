<?php
require('fpdf.php');
require('db_con.php');

$product_code = $_GET['product_code'] ?? '';

if (empty($product_code)) {
    die("Error: Missing product code.");
}

// Fetch product details
$stmt = $conn->prepare("SELECT product_name, category FROM store_b_finished_products_in WHERE product_code = ? LIMIT 1");
$stmt->bind_param("s", $product_code);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch inventory data
$sql = "SELECT * FROM store_b_finished_products_in WHERE product_code = ? ORDER BY receiving_date DESC, id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $product_code);
$stmt->execute();
$result = $stmt->get_result();

class PDF extends FPDF
{
    function Header()
    {
        $this->Image('images/lynn_logo.png', 10, 8, 20);
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 8, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(3);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Store B Finished Product Inventory Details', 0, 1, 'C');
        $this->Cell(0, 6, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(6);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }
}

// Generate PDF
$pdf = new PDF('L', 'mm', 'A4'); // Landscape for more columns
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Product header
if ($product) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Product: ' . $product['product_name'], 0, 1);
    $pdf->Cell(0, 8, 'Category: ' . $product['category'], 0, 1);
    $pdf->Cell(0, 8, 'Product Code: ' . $product_code, 0, 1);
    $pdf->Ln(4);
}

// Table Header
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(200, 220, 255);
$headers = ['Delivery No.', 'Qty Received', 'Remaining Qty', 'Units', 'Pack Size', 'Unit Cost', 'PO Number', 'Received By', 'Receiving Date'];
$widths = [30, 25, 25, 20, 25, 25, 30, 35, 35];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Table Data
$pdf->SetFont('Arial', '', 9);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell($widths[0], 8, $row['delivery_number'], 1);
        $pdf->Cell($widths[1], 8, $row['quantity_received'], 1, 0, 'C');
        $pdf->Cell($widths[2], 8, $row['remaining_quantity'], 1, 0, 'C');
        $pdf->Cell($widths[3], 8, $row['units'], 1, 0, 'C');
        $pdf->Cell($widths[4], 8, $row['pack_size'], 1, 0, 'C');
        $pdf->Cell($widths[5], 8, number_format($row['unit_cost'], 2), 1, 0, 'C');
        $pdf->Cell($widths[6], 8, $row['po_number'], 1);
        $pdf->Cell($widths[7], 8, $row['received_by'], 1);
        $pdf->Cell($widths[8], 8, $row['receiving_date'], 1);
        $pdf->Ln();
    }
} else {
    $pdf->Cell(array_sum($widths), 10, 'No inventory records found for this product.', 1, 1, 'C');
}

$pdf->Output('D', 'StoreB_Finished_Inventory_' . $product_code . '.pdf');
?>
