<?php
require('fpdf.php');
require 'db_con.php';

// ✅ Handle date filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// ✅ Prepare the base SQL
$sql = "
    SELECT 
      d.company_name,
      o.invoice_number,
      o.delivery_number,
      i.item_name,
      i.source_table,
      i.quantity_removed,
      i.unit,
      i.created_at
    FROM delivery_order_items i
    JOIN delivery_orders o ON i.order_id = o.id
    JOIN delivery_details d ON o.delivery_id = d.id
";

$params = [];
$conditions = [];

if (!empty($start_date)) {
    $conditions[] = "DATE(i.created_at) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $conditions[] = "DATE(i.created_at) <= ?";
    $params[] = $end_date;
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY i.created_at DESC";

// ✅ Execute query safely
$stmt = $conn->prepare($sql);
if ($params) {
    $types = str_repeat("s", count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ✅ Custom PDF Class
class PDF extends FPDF
{
    function Header()
    {
        $this->Image('images/lynn_logo.png', 10, 8, 25);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 8, 'ALL DELIVERY ORDER ITEMS REPORT', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Generated on ' . date('d M Y h:i A') . ' | Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function TableHeader()
{
    $this->SetFont('Arial', 'B', 9);
    $this->SetFillColor(230, 230, 230);
    $this->Cell(40, 8, 'Company Name', 1, 0, 'C', true);
    $this->Cell(22, 8, 'Invoice No.', 1, 0, 'C', true);
    $this->Cell(22, 8, 'Delivery No.', 1, 0, 'C', true);
    $this->Cell(65, 8, 'Item Name', 1, 0, 'C', true); // ✅ Extended
    $this->Cell(25, 8, 'Quantity', 1, 0, 'C', true);
    $this->Cell(20, 8, 'Unit', 1, 0, 'C', true);
    $this->Cell(40, 8, 'Delivered On', 1, 1, 'C', true);
}

}

// ✅ Create PDF
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();

// ✅ Filter info
$pdf->SetFont('Arial', 'I', 9);
if (!empty($start_date) || !empty($end_date)) {
    $pdf->Cell(0, 8, 'Filtered Period: ' . ($start_date ?: '...') . ' to ' . ($end_date ?: '...'), 0, 1, 'R');
}
$pdf->Ln(3);

// ✅ Table Header
$pdf->TableHeader();
$pdf->SetFont('Arial', '', 9);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
    $pdf->Cell(40, 8, substr($row['company_name'], 0, 28), 1);
    $pdf->Cell(22, 8, $row['invoice_number'], 1);
    $pdf->Cell(22, 8, $row['delivery_number'], 1);
    $pdf->Cell(65, 8, substr($row['item_name'], 0, 50), 1); // ✅ Extended field
    $pdf->Cell(25, 8, number_format($row['quantity_removed'], 0), 1, 0, 'R');
    $pdf->Cell(20, 8, $row['unit'], 1, 0, 'C');
    $pdf->Cell(40, 8, date('Y-m-d', strtotime($row['created_at'])), 1, 1);
}

} else {
    $pdf->Cell(235, 10, 'No delivery items found for the selected period.', 1, 1, 'C');
}

// ✅ Output
$pdf->Output('D', 'All_Delivery_Order_Items_' . date('Ymd_His') . '.pdf');
exit;
?>
