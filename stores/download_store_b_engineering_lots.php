<?php
require('fpdf.php');
include 'db_con.php';

// ✅ Get product_code
$product_code = $_GET['product_code'] ?? '';

if (empty($product_code)) {
    die('Invalid request');
}

// ✅ Fetch product details
$stmt = $conn->prepare("SELECT chemical_name AS product_name, group_name, category, main_category 
                        FROM chemical_names WHERE chemical_code = ?");
$stmt->bind_param("s", $product_code);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

// ✅ Fetch all inventories
$sql = "SELECT * FROM store_b_engineering_products_in 
        WHERE product_code = ? ORDER BY receiving_date DESC, id DESC";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("s", $product_code);
$stmt2->execute();
$result = $stmt2->get_result();

// ✅ Custom PDF class with header & footer
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('images/lynn_logo.png', 10, 6, 25);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 8, 'Store B Engineering Inventory Report', 0, 1, 'C');
        $this->Ln(5);
        $this->Line(10, 30, 200, 30);
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'L');
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'R');
    }
}

// ✅ Initialize PDF
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// ✅ Product info section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Product Details', 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 8, 'Product Name: ' . ($product['product_name'] ?? '-'), 0, 0);
$pdf->Cell(95, 8, 'Product Code: ' . $product_code, 0, 1);
$pdf->Cell(95, 8, 'Group: ' . ($product['group_name'] ?? '-'), 0, 0);
$pdf->Cell(95, 8, 'Category: ' . ($product['category'] ?? '-'), 0, 1);
$pdf->Cell(95, 8, 'Main Category: ' . ($product['main_category'] ?? '-'), 0, 1);
$pdf->Ln(5);

// ✅ Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(20, 8, 'Delivery #', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Qty In', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Qty Left', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Units', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Pack Size', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Unit Cost', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'PO Number', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Received By', 1, 1, 'C', true);

// ✅ Table rows
$pdf->SetFont('Arial', '', 9);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(20, 8, $row['delivery_number'], 1);
        $pdf->Cell(25, 8, $row['quantity_received'], 1);
        $pdf->Cell(25, 8, $row['remaining_quantity'], 1);
        $pdf->Cell(20, 8, $row['units'], 1);
        $pdf->Cell(25, 8, $row['pack_size'], 1);
        $pdf->Cell(25, 8, $row['unit_cost'], 1);
        $pdf->Cell(25, 8, $row['po_number'], 1);
        $pdf->Cell(25, 8, $row['received_by'], 1, 1);
    }
} else {
    $pdf->Cell(190, 8, 'No inventory records found.', 1, 1, 'C');
}

// ✅ Output the file
$pdf->Output('D', 'StoreB_Engineering_' . $product_code . '_Lots.pdf');
?>
