<?php
require('fpdf.php');
require('db_con.php');

$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    die("Invalid order ID.");
}

// --- Fetch order details ---
$stmt = $conn->prepare("SELECT * FROM delivery_orders_store_b WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found.");
}

// --- Fetch order items ---
$stmt = $conn->prepare("SELECT * FROM delivery_order_items_store_b WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

class PDF extends FPDF
{
    function Header()
    {
        $this->Image('images/lynn_logo.png', 10, 8, 20);
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 8, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(3);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Store B - Delivery Order Details', 0, 1, 'C');
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

// --- Generate PDF ---
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// --- Order Info ---
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Delivery Order # ' . $order['id'], 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Customer: ' . $order['company_name'], 0, 1);
$pdf->Cell(0, 6, 'Invoice #: ' . $order['invoice_number'], 0, 1);
$pdf->Cell(0, 6, 'Delivery #: ' . $order['delivery_number'], 0, 1);
$pdf->Cell(0, 6, 'Status: ' . $order['original_status'], 0, 1);
$pdf->MultiCell(0, 6, 'Remarks: ' . $order['remarks'], 0, 1);
$pdf->Ln(5);

// --- Table Header ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(200, 220, 255);
$headers = ['Item Name', 'Pack Size', 'Quantity Removed', 'Unit'];
$widths = [90, 35, 40, 25];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// --- Table Data ---
$pdf->SetFont('Arial', '', 9);
if ($items->num_rows > 0) {
    while ($it = $items->fetch_assoc()) {
        $pdf->Cell($widths[0], 8, $it['item_name'], 1);
        $pdf->Cell($widths[1], 8, $it['pack_size'], 1);
        $pdf->Cell($widths[2], 8, $it['quantity_removed'], 1, 0, 'C');
        $pdf->Cell($widths[3], 8, $it['unit'], 1, 0, 'C');
        $pdf->Ln();
    }
} else {
    $pdf->Cell(array_sum($widths), 10, 'No items found for this delivery order.', 1, 1, 'C');
}

$pdf->Output('D', 'StoreB_Order_' . $order_id . '.pdf');
?>
