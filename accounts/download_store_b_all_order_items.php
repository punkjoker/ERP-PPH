<?php
require('fpdf.php');
include 'db_con.php';

class PDF extends FPDF {
    function Header() {
        $this->Image('images/lynn_logo.png', 10, 6, 25);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 8, 'All Store B Delivery Items Report', 0, 1, 'C');
        $this->Ln(5);
        $this->Line(10, 32, 200, 32);
        $this->Ln(8);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'L');
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'R');
    }
}

$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$item_name = $_GET['item_name'] ?? '';

$sql = "
    SELECT 
        o.company_name,
        o.invoice_number,
        o.delivery_number,
        i.item_name,
        i.material_name,
        i.pack_size,
        i.quantity_removed,
        i.unit,
        o.original_status,
        o.created_at
    FROM delivery_order_items_store_b i
    JOIN delivery_orders_store_b o ON i.order_id = o.id
    WHERE 1
";

$params = [];
$types = '';

if (!empty($from_date) && !empty($to_date)) {
    $sql .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
}

if (!empty($item_name)) {
    $sql .= " AND i.item_name = ?";
    $params[] = $item_name;
    $types .= "s";
}

$sql .= " ORDER BY o.id DESC, i.id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 10);

// Table Header
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(35, 8, 'Company', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Invoice No', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Delivery No', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Item', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Pack Size', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Unit', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Status', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

// Table Rows
while ($row = $result->fetch_assoc()) {
    $pdf->Cell(35, 8, $row['company_name'], 1);
    $pdf->Cell(25, 8, $row['invoice_number'], 1);
    $pdf->Cell(25, 8, $row['delivery_number'], 1);
    $pdf->Cell(30, 8, $row['item_name'], 1);
    $pdf->Cell(20, 8, $row['pack_size'], 1);
    $pdf->Cell(20, 8, $row['quantity_removed'], 1);
    $pdf->Cell(20, 8, $row['unit'], 1);
    $pdf->Cell(25, 8, $row['original_status'], 1, 1);
}

$pdf->Output('D', 'StoreB_Delivery_Items_Report.pdf');
?>
