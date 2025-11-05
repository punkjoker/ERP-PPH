<?php
require('fpdf.php');
include 'db_con.php';

// Validate delivery ID
$delivery_id = intval($_GET['id'] ?? 0);
if ($delivery_id <= 0) {
    die("Invalid delivery ID.");
}

// Fetch delivery batch info
$stmt = $conn->prepare("SELECT * FROM order_deliveries_store_b WHERE id = ?");
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();
if (!$delivery) {
    die("Delivery batch not found.");
}

// Fetch linked orders
$query = "
    SELECT 
        odi.destination,
        do.id AS order_id,
        do.invoice_number,
        do.delivery_number,
        do.company_name,
        do.remarks,
        do.original_status
    FROM order_delivery_items_store_b odi
    JOIN delivery_orders_store_b do 
        ON odi.delivery_order_id = do.id
    WHERE odi.delivery_id = ?
";
$stmt2 = $conn->prepare($query);
$stmt2->bind_param('i', $delivery_id);
$stmt2->execute();
$orders = $stmt2->get_result();

class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('images/lynn_logo.png', 10, 8, 20);
        // Company Name
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Store B Delivery Details Report', 0, 1, 'C');
        $this->Ln(5);
        $this->Line(10, 28, 200, 28);
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('d-m-Y H:i:s'), 0, 0, 'L');
        $this->Cell(0, 10, 'Page ' . $this->PageNo().'/{nb}', 0, 0, 'R');
    }
}

// PDF init
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// --- Delivery Info ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Delivery Batch Information', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 6, 'Delivery Day: ' . $delivery['delivery_day'], 0, 1);
$pdf->Cell(60, 6, 'Delivery Date: ' . $delivery['delivery_date'], 0, 1);
$pdf->Cell(60, 6, 'Status: ' . $delivery['status'], 0, 1);
$pdf->Cell(60, 6, 'Created At: ' . $delivery['created_at'], 0, 1);
$pdf->Ln(5);

// --- Orders & Items ---
if ($orders->num_rows > 0) {
    $count = 1;
    while ($o = $orders->fetch_assoc()) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, "{$count}. " . $o['company_name'], 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Invoice #: ' . $o['invoice_number'] . ' | Delivery #: ' . $o['delivery_number'], 0, 1);
        $pdf->Cell(0, 6, 'Destination: ' . $o['destination'], 0, 1);
        $pdf->MultiCell(0, 6, 'Remarks: ' . ($o['remarks'] ?? '-'));
        $pdf->Cell(0, 6, 'Status: ' . $o['original_status'], 0, 1);
        $pdf->Ln(3);

        // Table header for items
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(70, 7, 'Item Name', 1, 0, 'C');
        $pdf->Cell(30, 7, 'Pack Size', 1, 0, 'C');
        $pdf->Cell(30, 7, 'Qty Removed', 1, 0, 'C');
        $pdf->Cell(30, 7, 'Unit', 1, 1, 'C');

        // Fetch items for this order
        $items_stmt = $conn->prepare("SELECT item_name, pack_size, quantity_removed, unit 
                                      FROM delivery_order_items_store_b 
                                      WHERE order_id = ?");
        $items_stmt->bind_param('i', $o['order_id']);
        $items_stmt->execute();
        $items = $items_stmt->get_result();

        $pdf->SetFont('Arial', '', 10);
        if ($items->num_rows > 0) {
            while ($it = $items->fetch_assoc()) {
                $pdf->Cell(70, 7, $it['item_name'], 1);
                $pdf->Cell(30, 7, $it['pack_size'], 1);
                $pdf->Cell(30, 7, (int)$it['quantity_removed'], 1);
                $pdf->Cell(30, 7, $it['unit'], 1, 1);
            }
        } else {
            $pdf->Cell(160, 7, 'No items found for this order', 1, 1, 'C');
        }
        $pdf->Ln(6);
        $count++;
    }
} else {
    $pdf->Cell(0, 10, 'No orders linked to this delivery batch.', 0, 1);
}

$pdf->Output('D', 'Store_B_Delivery_' . $delivery_id . '.pdf');
?>
