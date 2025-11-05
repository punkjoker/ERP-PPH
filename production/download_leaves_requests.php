<?php
require('fpdf.php');
include 'db_con.php';

// --- Fetch filter values ---
$filter_user = $_GET['user_id'] ?? '';
$filter_type = $_GET['leave_type'] ?? '';
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';

$where = [];
$params = [];
$types = "";

// --- Base Query ---
$sql = "SELECT l.*, u.full_name, u.national_id
        FROM leaves l
        JOIN users u ON l.user_id = u.user_id
        JOIN groups g ON u.group_id = g.group_id
        WHERE g.group_name = 'staff'";

// --- Apply filters ---
if ($filter_user) {
    $where[] = "l.user_id = ?";
    $params[] = $filter_user;
    $types .= "i";
}
if ($filter_type) {
    $where[] = "l.leave_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if ($from_date && $to_date) {
    $where[] = "l.start_date BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
}

if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}
$sql .= " ORDER BY l.start_date DESC";

// --- Execute Query ---
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// --- Setup PDF ---
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Leave Requests Report', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Generated on: ' . date('d M Y H:i'), 0, 1, 'C');
        $this->Ln(5);

        // Table header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(200, 200, 200);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Name', 1, 0, 'C', true);
        $this->Cell(25, 8, 'National ID', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Type', 1, 0, 'C', true);
        $this->Cell(45, 8, 'Description', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Start', 1, 0, 'C', true);
        $this->Cell(20, 8, 'End', 1, 0, 'C', true);
        $this->Cell(15, 8, 'Days', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Status', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// --- Fill Data ---
$count = 1;
while ($row = $result->fetch_assoc()) {
    $pdf->Cell(10, 8, $count++, 1, 0, 'C');
    $pdf->Cell(40, 8, $row['full_name'], 1);
    $pdf->Cell(25, 8, $row['national_id'], 1);
    $pdf->Cell(25, 8, $row['leave_type'], 1);
    $desc = strlen($row['description']) > 30 ? substr($row['description'], 0, 30) . '...' : $row['description'];
    $pdf->Cell(45, 8, $desc, 1);
    $pdf->Cell(20, 8, $row['start_date'], 1);
    $pdf->Cell(20, 8, $row['end_date'], 1);
    $pdf->Cell(15, 8, $row['total_days'], 1, 0, 'C');
    $pdf->Cell(20, 8, $row['status'], 1, 1, 'C');
}

// --- If no records ---
if ($count === 1) {
    $pdf->Cell(0, 10, 'No leave requests found for the selected filters.', 1, 1, 'C');
}

$pdf->Output('D', 'Leave_Requests_Report_' . date('Y-m-d') . '.pdf');
exit;
?>
