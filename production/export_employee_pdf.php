<?php 
require('fpdf.php');
include 'db_con.php';

$employee_id = $_GET['id'] ?? 0;
$employee_id = intval($employee_id);

// Fetch employee info
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found.");
}

// Fetch additional info
$info_stmt = $conn->prepare("SELECT * FROM employee_information WHERE employee_id = ?");
$info_stmt->bind_param("i", $employee_id);
$info_stmt->execute();
$info = $info_stmt->get_result()->fetch_assoc();
$info_stmt->close();

// Fetch education and experience
$items_stmt = $conn->prepare("SELECT * FROM employee_information_items WHERE employee_id = ? ORDER BY category");
$items_stmt->bind_param("i", $employee_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$education = [];
$experience = [];
while ($row = $items_result->fetch_assoc()) {
    if ($row['category'] === 'education') $education[] = $row;
    elseif ($row['category'] === 'experience') $experience[] = $row;
}
$items_stmt->close();

function calculateDuration($start, $end) {
    if (empty($start) || empty($end)) return "";
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $diff = $startDate->diff($endDate);
    $years = $diff->y;
    $months = $diff->m;
    if ($years > 0 && $months > 0) return "({$years} yr {$months} mo)";
    elseif ($years > 0) return "({$years} yr)";
    elseif ($months > 0) return "({$months} mo)";
    return "";
}

// Start PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// Logo and header
if(file_exists('images/lynn_logo.png')) {
    $pdf->Image('images/lynn_logo.png', 10, 8, 25);
}
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 15, 'Employee Profile', 0, 1, 'C');
$pdf->Ln(5);

// Passport Photo
if(!empty($employee['passport_path']) && file_exists($employee['passport_path'])) {
    $pdf->Image($employee['passport_path'], 160, 30, 35, 35);
}

$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0, 0, 0);

// ==================== EMPLOYEE DETAILS ====================
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Personal Information', 0, 1);
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 12);

$details = [
    "Full Name" => $employee['first_name'].' '.$employee['last_name'],
    "National ID" => $employee['national_id'],
    "KRA PIN" => $employee['kra_pin'],
    "NSSF Number" => $employee['nssf_number'],
    "NHIF Number" => $employee['nhif_number'],
    "Email" => $employee['email'],
    "Phone" => $employee['phone'],
    "Department" => $employee['department'],
    "Position" => $employee['position'],
    "Date of Hire" => $employee['date_of_hire'],
    "Status" => $employee['status'],
    "Employment Type" => $employee['employment_type'] ?? 'Permanent'
];

foreach ($details as $label => $value) {
    $pdf->Cell(60, 8, "$label:", 0, 0, 'L');
    $pdf->Cell(0, 8, $value, 0, 1, 'L');
}

if($employee['employment_type'] === 'Contract') {
    $pdf->Cell(60, 8, "Contract Start:", 0, 0, 'L');
    $pdf->Cell(0, 8, $employee['contract_start'], 0, 1, 'L');
    $pdf->Cell(60, 8, "Contract End:", 0, 0, 'L');
    $pdf->Cell(0, 8, $employee['contract_end'], 0, 1, 'L');
}

// ==================== EDUCATION ====================
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Education Background', 0, 1);
$pdf->SetFont('Arial', '', 12);

if (!empty($education)) {
    foreach ($education as $edu) {
        $pdf->MultiCell(0, 8, 
            "Institution: {$edu['field1']}\n" .
            "Level: {$edu['field2']}\n" .
            "Start: {$edu['field3']} | End: {$edu['field4']}\n" .
            "Result: {$edu['field5']}\n", 
            0, 'L');
        $pdf->Ln(2);
    }
} else {
    $pdf->Cell(0, 8, 'No education details added.', 0, 1);
}

// ==================== EXPERIENCE ====================
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Work Experience', 0, 1);
$pdf->SetFont('Arial', '', 12);

if (!empty($experience)) {
    foreach ($experience as $exp) {
        $duration = calculateDuration($exp['field3'], $exp['field4']);
        $pdf->MultiCell(0, 8, 
            "{$exp['field1']} ({$exp['field3']} - {$exp['field4']}) $duration\n" .
            "{$exp['field2']} {$exp['field5']}\n", 
            0, 'L');
        $pdf->Ln(2);
    }
} else {
    $pdf->Cell(0, 8, 'No work experience added.', 0, 1);
}

// ==================== SKILLS ====================
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Skills', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, !empty($info['skills']) ? $info['skills'] : 'No skills added.', 0, 'L');

// ==================== CERTIFICATIONS ====================
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Certifications', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, !empty($info['certifications']) ? $info['certifications'] : 'No certifications added.', 0, 'L');

// ==================== SALARY INFO ====================
$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Salary Information', 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(60, 8, "Expected Salary:", 0, 0, 'L');
$pdf->Cell(0, 8, "KES ".number_format($info['expected_salary'] ?? 0, 2), 0, 1);
$pdf->Cell(60, 8, "Approved Salary:", 0, 0, 'L');
$pdf->Cell(0, 8, "KES ".number_format($info['approved_salary'] ?? 0, 2), 0, 1);

// ==================== FOOTER ====================
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 8, 'Generated by LynnTech Employee Management System', 0, 1, 'C');

$pdf->Output('I', 'Employee_Profile.pdf');
?>
