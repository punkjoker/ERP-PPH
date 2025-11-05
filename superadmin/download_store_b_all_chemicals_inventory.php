<?php
require('fpdf.php');
require('db_con.php');

// Fetch search filter if any
$search = $_GET['search'] ?? '';
$search_sql = "";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $search_sql = " AND (cn.chemical_name LIKE '%$search_safe%' OR cn.chemical_code LIKE '%$search_safe%') ";
}

// ✅ Fetch chemicals and their inventory quantities
$query = "
    SELECT cn.chemical_name, cn.group_name, cn.category, cn.chemical_code,
           IFNULL(SUM(sbci.remaining_quantity), '0') AS total_quantity
    FROM chemical_names cn
    LEFT JOIN store_b_chemicals_in sbci
    ON cn.chemical_code = sbci.chemical_code
    WHERE cn.main_category = 'Chemicals' $search_sql
    GROUP BY cn.chemical_code, cn.chemical_name, cn.group_name, cn.category
    ORDER BY cn.chemical_name ASC
";

$result = $conn->query($query);

// ✅ Custom PDF Class
class PDF extends FPDF
{
    function Header()
    {
        // Logo
        $this->Image('images/lynn_logo.png', 10, 8, 20);
        // Company name
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(3);
        // Title
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'STORE B - ALL CHEMICALS INVENTORY REPORT', 0, 1, 'C');
        $this->Ln(4);
        // Date generated
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 8, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(3);
        // Table header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(60, 8, 'Chemical Name', 1, 0, 'C', true);
        $this->Cell(50, 8, 'Group Name', 1, 0, 'C', true);
        $this->Cell(50, 8, 'Category', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Chemical Code', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Total Quantity', 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// ✅ Create PDF
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// ✅ Populate data
if ($result && $result->num_rows > 0) {
    $totalChemicals = 0;
    $grandTotalQuantity = 0;

    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(60, 8, substr($row['chemical_name'], 0, 35), 1);
        $pdf->Cell(50, 8, substr($row['group_name'], 0, 30), 1);
        $pdf->Cell(50, 8, substr($row['category'], 0, 30), 1);
        $pdf->Cell(35, 8, $row['chemical_code'], 1, 0, 'C');
        $pdf->Cell(35, 8, number_format($row['total_quantity'], 2), 1, 1, 'C');

        $totalChemicals++;
        $grandTotalQuantity += $row['total_quantity'];
    }

    // ✅ Summary row
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(195, 8, "TOTAL CHEMICALS: $totalChemicals", 1, 0, 'C', true);
    $pdf->Cell(35, 8, number_format($grandTotalQuantity, 2), 1, 1, 'C', true);

} else {
    $pdf->Cell(0, 10, 'No chemical records found in Store B inventory.', 1, 1, 'C');
}

// ✅ Output the PDF
$pdf->Output('D', 'StoreB_All_Chemicals_Inventory_Report.pdf');
exit;
?>
