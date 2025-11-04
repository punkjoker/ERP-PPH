<?php
require 'db_con.php';
require('fpdf.php');

$delivery_id = $_GET['id'] ?? 0;

// Fetch delivery info
$stmt = $conn->prepare("SELECT * FROM order_deliveries WHERE id = ?");
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch linked orders
$query = "
    SELECT odi.destination, do.id AS delivery_order_id, 
           do.invoice_number, do.delivery_number, do.original_status,
           d.company_name
    FROM order_delivery_items odi
    JOIN delivery_orders do ON odi.delivery_order_id = do.id
    JOIN delivery_details d ON do.delivery_id = d.id
    WHERE odi.delivery_id = ?
";
$stmt2 = $conn->prepare($query);
$stmt2->bind_param('i', $delivery_id);
$stmt2->execute();
$linked_orders = $stmt2->get_result();

// Initialize PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Delivery Batch Details',0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->Cell(50,8,'Delivery Day:',1,0);
$pdf->Cell(0,8,$delivery['delivery_day'],1,1);
$pdf->Cell(50,8,'Delivery Date:',1,0);
$pdf->Cell(0,8,$delivery['delivery_date'],1,1);
$pdf->Cell(50,8,'Status:',1,0);
$pdf->Cell(0,8,$delivery['status'],1,1);

$pdf->Ln(8);
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,8,'Linked Delivery Orders',0,1);

foreach($linked_orders as $i => $order) {
    $i++;
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(10,8,$i,1);
    $pdf->Cell(40,8,$order['company_name'],1);
    $pdf->Cell(40,8,$order['destination'],1);
    $pdf->Cell(35,8,$order['invoice_number'],1);
    $pdf->Cell(35,8,$order['delivery_number'],1);
    $pdf->Cell(30,8,$order['original_status'],1,1);

    // Fetch order items
    $stmt3 = $conn->prepare("
        SELECT item_name, quantity_removed, unit, material_name, pack_size, source_table
        FROM delivery_order_items
        WHERE order_id = ?
    ");
    $stmt3->bind_param("i", $order['delivery_order_id']);
    $stmt3->execute();
    $items = $stmt3->get_result();

    $pdf->SetFont('Arial','',11);
    if($items->num_rows > 0){
        while($it = $items->fetch_assoc()){
            $line = $it['item_name'];
            if($it['source_table'] === 'finished_products') {
                $line .= ", ".$it['material_name'];
                if(!empty($it['pack_size'])) $line .= ", ".$it['pack_size']." ".($it['unit'] ?? '');
                $line .= ", ".number_format($it['quantity_removed'],0)." (pack)";
            } else {
                $line .= " - ".$it['quantity_removed']." ".$it['unit'];
            }
            $pdf->MultiCell(0,6,$line);
        }
    } else {
        $pdf->Cell(0,6,'No items',1,1);
    }
    $pdf->Ln(3);
}

// Output PDF
$pdf->Output('D','DeliveryBatch_'.$delivery['delivery_day'].'.pdf');
?>
