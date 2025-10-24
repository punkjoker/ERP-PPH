<?php
require('fpdf.php');
include 'db_con.php';

$from_date = $_GET['from'] ?? date('Y-m-01');
$to_date   = $_GET['to'] ?? date('Y-m-t');

// --- Fetch Totals ---
$stmt1 = $conn->prepare("
    SELECT SUM(i.total_cost) AS total_bom_cost
    FROM bill_of_material_items i
    JOIN bill_of_materials b ON i.bom_id = b.id
    WHERE b.bom_date BETWEEN ? AND ?
");
$stmt1->bind_param('ss', $from_date, $to_date);
$stmt1->execute();
$bill_total = $stmt1->get_result()->fetch_assoc()['total_bom_cost'] ?? 0;

$stmt2 = $conn->prepare("
    SELECT SUM(p.total_cost) AS total_pack_cost
    FROM packaging p
    WHERE DATE(p.packaging_date) BETWEEN ? AND ?
");
$stmt2->bind_param('ss', $from_date, $to_date);
$stmt2->execute();
$pack_total = $stmt2->get_result()->fetch_assoc()['total_pack_cost'] ?? 0;

$total_production_cost = $bill_total + $pack_total;

// --- PDF CLASS ---
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->Cell(0,10,'Production Cost Report',0,1,'C');
        $this->Ln(4);
    }

    // ✅ Improved Vertical Bar Chart Renderer
    function DrawVerticalChart($title, $data, $unit='', $chartWidth=180, $chartHeight=90, $barWidth=8) {
        $this->SetFont('Arial','B',12);
        $this->Cell(0,8,$title,0,1,'L');
        $this->Ln(4);

        if(empty($data)) {
            $this->SetFont('Arial','I',10);
            $this->Cell(0,8,'No data available for this period.',0,1,'C');
            $this->Ln(4);
            return;
        }

        // Dynamic scaling
        $maxValue = max(array_column($data, 'value')) ?: 1;
        $scale = min($chartHeight / $maxValue, 2); // prevents very tall bars

        $x0 = $this->GetX();
        $y0 = $this->GetY() + $chartHeight;

        // Draw X-axis
        $this->Line($x0, $y0, $x0 + $chartWidth, $y0);

        $count = count($data);
        $barWidth = min(12, ($chartWidth - 20) / max($count, 1)); // adapt width if many bars
        $space = ($chartWidth - 20) / max($count, 1);
        $x = $x0 + 10;

        $this->SetFont('Arial','',8);
        foreach($data as $row) {
            $barHeight = $row['value'] * $scale;
            $barY = $y0 - $barHeight;

            $this->SetFillColor(59,130,246);
            $this->Rect($x, $barY, $barWidth, $barHeight, 'F');

            // Value above
            $this->SetXY($x, $barY - 5);
            $this->Cell($barWidth, 5, number_format($row['value'], 1), 0, 0, 'C');

            // Label below
            $this->SetXY($x - 5, $y0 + 2);
            $this->MultiCell($barWidth + 10, 3, substr($row['label'], 0, 10), 0, 'C');

            $x += $space;
        }

        $this->Ln($chartHeight + 20);
    }
}

// --- Generate PDF ---
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial','',12);

// --- Date Range + Summary (Page 1) ---
$pdf->Cell(0,10,"Reporting Period: $from_date to $to_date",0,1);
$pdf->Ln(4);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,'Summary of Production Costs',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(90,8,'Total BOM Cost:',0,0);
$pdf->Cell(50,8,number_format($bill_total,2).' Ksh',0,1);
$pdf->Cell(90,8,'Total Packaging Cost:',0,0);
$pdf->Cell(50,8,number_format($pack_total,2).' Ksh',0,1);
$pdf->Cell(90,8,'Total Production Cost:',0,0);
$pdf->Cell(50,8,number_format($total_production_cost,2).' Ksh',0,1);

// =======================
// 1️⃣ Chemical Cost Chart
// =======================
$pdf->AddPage();
$stmtChemCost = $conn->prepare("
    SELECT c.chemical_name AS label, IFNULL(SUM(i.total_cost), 0) AS value
    FROM chemicals_in c
    LEFT JOIN bill_of_material_items i ON c.chemical_code = i.chemical_code
    LEFT JOIN bill_of_materials b ON i.bom_id = b.id AND b.bom_date BETWEEN ? AND ?
    GROUP BY c.chemical_name
    ORDER BY c.chemical_name ASC
");
$stmtChemCost->bind_param('ss', $from_date, $to_date);
$stmtChemCost->execute();
$res = $stmtChemCost->get_result();
$chemCostData = [];
while ($r = $res->fetch_assoc()) $chemCostData[] = $r;
$pdf->DrawVerticalChart('Chemical Cost Distribution (All Chemicals)', $chemCostData, 'Ksh');

// =======================
// 2️⃣ Chemical Quantity Chart
// =======================
$pdf->AddPage();
$stmtChemQty = $conn->prepare("
    SELECT c.chemical_name AS label, IFNULL(SUM(i.quantity_requested), 0) AS value
    FROM chemicals_in c
    LEFT JOIN bill_of_material_items i ON c.chemical_code = i.chemical_code
    LEFT JOIN bill_of_materials b ON i.bom_id = b.id AND b.bom_date BETWEEN ? AND ?
    GROUP BY c.chemical_name
    ORDER BY c.chemical_name ASC
");
$stmtChemQty->bind_param('ss', $from_date, $to_date);
$stmtChemQty->execute();
$res2 = $stmtChemQty->get_result();
$chemQtyData = [];
while ($r = $res2->fetch_assoc()) $chemQtyData[] = $r;
$pdf->DrawVerticalChart('Chemical Quantity Usage (All Chemicals)', $chemQtyData, 'Kg');

// =======================
// 3️⃣ Packaging Cost Chart
// =======================
$pdf->AddPage();
$stmtPackCost = $conn->prepare("
    SELECT p.item_name AS label, IFNULL(SUM(p.total_cost), 0) AS value
    FROM packaging p
    WHERE DATE(p.packaging_date) BETWEEN ? AND ?
    GROUP BY p.item_name
    ORDER BY p.item_name ASC
");
$stmtPackCost->bind_param('ss', $from_date, $to_date);
$stmtPackCost->execute();
$res3 = $stmtPackCost->get_result();
$packCostData = [];
while ($r = $res3->fetch_assoc()) $packCostData[] = $r;
$pdf->DrawVerticalChart('Packaging Material Cost Distribution', $packCostData, 'Ksh');

// =======================
// 4️⃣ Packaging Quantity Chart
// =======================
$pdf->AddPage();
$stmtPackQty = $conn->prepare("
    SELECT p.item_name AS label, IFNULL(SUM(p.quantity_used), 0) AS value
    FROM packaging p
    WHERE DATE(p.packaging_date) BETWEEN ? AND ?
    GROUP BY p.item_name
    ORDER BY p.item_name ASC
");
$stmtPackQty->bind_param('ss', $from_date, $to_date);
$stmtPackQty->execute();
$res4 = $stmtPackQty->get_result();
$packQtyData = [];
while ($r = $res4->fetch_assoc()) $packQtyData[] = $r;
$pdf->DrawVerticalChart('Packaging Quantity Used', $packQtyData, 'Units');

// --- Output PDF ---
$pdf->Output('D', 'Production_Report_' . date('Ymd') . '.pdf');
exit;
?>
