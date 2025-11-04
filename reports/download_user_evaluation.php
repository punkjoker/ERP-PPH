<?php
session_start();
require 'db_con.php';
require('fpdf.php');

if (!isset($_GET['id'])) die("Missing evaluation ID.");
$eval_id = intval($_GET['id']);

// Fetch evaluation, behaviours, and related appraisals
$sql = "SELECT e.*, u.full_name, u.national_id
        FROM user_performance_evaluation e
        JOIN users u ON e.user_id = u.user_id
        WHERE e.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eval_id);
$stmt->execute();
$evaluation = $stmt->get_result()->fetch_assoc();
$stmt->close();

$ratings_stmt = $conn->prepare("SELECT category, rating FROM user_performance_behaviours WHERE evaluation_id = ?");
$ratings_stmt->bind_param("i", $eval_id);
$ratings_stmt->execute();
$ratings_result = $ratings_stmt->get_result();
$behaviours = [];
while ($r = $ratings_result->fetch_assoc()) $behaviours[$r['category']] = $r['rating'];
$ratings_stmt->close();

$app_stmt = $conn->prepare("SELECT * FROM employee_appraisal WHERE user_id = ? ORDER BY eval_date DESC");
$app_stmt->bind_param("i", $evaluation['user_id']);
$app_stmt->execute();
$app_results = $app_stmt->get_result();

// Initialize FPDF
$pdf = new FPDF();
$pdf->AddPage();

// ---- Header Table ----
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(200,220,255);
$pdf->Cell(30,10,$pdf->Image('images/lynn_logo.png',$pdf->GetX()+2,$pdf->GetY()+2,26),1,0,'C',true); // logo cell
$pdf->Cell(160,10,"EMPLOYEE APPRAISAL FORM QF-45",1,1,'C',true);

$pdf->SetFont('Arial','',10);
$pdf->SetFillColor(245,245,245);
$pdf->Cell(40,6,"EFF. DATE",1,0,'L',true);
$pdf->Cell(50,6,"02/2024",1,0,'L');
$pdf->Cell(40,6,"ISSUE DATE",1,0,'L',true);
$pdf->Cell(50,6,"02/2024",1,1,'L');

$pdf->Cell(40,6,"REVIEW DATE",1,0,'L',true);
$pdf->Cell(50,6,"01/2027",1,0,'L');
$pdf->Cell(40,6,"MANUAL NO",1,0,'L',true);
$pdf->Cell(50,6,"LYNNTECH-QM-02",1,1,'L');

$pdf->Cell(40,6,"ISSUE NO",1,0,'L',true);
$pdf->Cell(50,6,"007",1,0,'L');
$pdf->Cell(40,6,"REVISION NO",1,0,'L',true);
$pdf->Cell(50,6,"006",1,1,'L');

$pdf->Cell(40,6,"DOCUMENT NO",1,0,'L',true);
$pdf->Cell(50,6,"LYNNTECH-QP-13",1,0,'L');
$pdf->Cell(40,6,"PAGE",1,0,'L',true);
$pdf->Cell(50,6,"1",1,1,'L');
$pdf->Ln(5);

// ---- Employee Info ----
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,6,"Performance Evaluation Details",0,1,'C');
$pdf->Ln(3);
$pdf->SetFont('Arial','',11);

$pdf->SetFillColor(220,220,220); // header shading

$labelWidth = 50;
$valueWidth = 90; // adjust as needed

// Full Name
$pdf->Cell($labelWidth,6,"Full Name",1,0,'L',true);
$pdf->Cell($valueWidth,6,$evaluation['full_name'],1,1,'L');

// National ID
$pdf->Cell($labelWidth,6,"National ID",1,0,'L',true);
$pdf->Cell($valueWidth,6,$evaluation['national_id'],1,1,'L');

// Evaluation Date
$pdf->Cell($labelWidth,6,"Evaluation Date",1,0,'L',true);
$pdf->Cell($valueWidth,6,$evaluation['eval_date'],1,1,'L');

// Evaluator
$pdf->Cell($labelWidth,6,"Evaluator",1,0,'L',true);
$pdf->Cell($valueWidth,6,$evaluation['evaluator_name'],1,1,'L');

$pdf->Ln(5);

// ---- Behavioural Ratings Table ----
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,6,"Behavioural Ratings",0,1);
$pdf->SetFont('Arial','',11);
$pdf->SetFillColor(220,220,220);
$pdf->Cell(120,8,"Category",1,0,'C',true);
$pdf->Cell(40,8,"Rating",1,1,'C',true);

$categories = ["Integrity","Coworker Relations","Client Relations","Technical Skills","Dependability","Punctuality","Attendance"];
foreach($categories as $cat){
    $pdf->Cell(120,6,$cat,1);
    $pdf->Cell(40,6,$behaviours[$cat] ?? '-',1,1,'C');
}
$pdf->Ln(5);

// ---- Text Areas ----
$areas = [
    "strengths"=>"Strengths",
    "key_activities"=>"Key Activities Undertaken",
    "accomplishments"=>"Accomplishments",
    "challenges"=>"Challenges and Solutions",
    "improvement_plan"=>"Improvement Plan",
    "previous_goals"=>"Previous Goals",
    "future_goals"=>"Future Goals",
    "manager_support"=>"Support Needed from Management",
    "employee_concerns"=>"Employee Concerns or Suggestions"
];
foreach($areas as $key=>$label){
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0,6,$label,0,1);
    $pdf->SetFont('Arial','',11);
    $pdf->MultiCell(0,6,$evaluation[$key] ?? '');
    $pdf->Ln(3);
}

// ---- Related Appraisals Table ----
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,6,"Related Employee Appraisal Records",0,1);
$pdf->SetFont('Arial','',11);
while($row=$app_results->fetch_assoc()){
    $pdf->SetFillColor(245,245,245);
    $pdf->Cell(0,6,"Evaluator: ".$row['evaluator_name']." | Dept: ".$row['evaluator_department']." | Date: ".$row['eval_date'],1,1,'L',true);
    $categories=[
        'Quality of Work'=>$row['quality_of_work'],
        'Work Consistency'=>$row['work_consistency'],
        'Communication'=>$row['communication'],
        'Independent Work'=>$row['independent_work'],
        'Takes Initiative'=>$row['takes_initiative'],
        'Exercises Teamwork'=>$row['exercises_teamwork'],
        'Productivity'=>$row['productivity'],
        'Creativity'=>$row['creativity'],
        'Honesty'=>$row['honesty']
    ];
    foreach($categories as $cat=>$val){
        $pdf->Cell(120,6,$cat,1);
        $pdf->Cell(40,6,$val ?? '-',1,1,'C');
    }
    $pdf->Cell(0,6,"Total Score: ".$row['total_score']."%",1,1);
    $pdf->Ln(3);
}

// ---- Approval ----
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,6,"Approval Details",0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(50,6,"Approved By",1,0,'L',true);
$pdf->Cell(90,6,$evaluation['approved_by']??'-',1,1);
$pdf->Cell(50,6,"Authorized By",1,0,'L',true);
$pdf->Cell(90,6,$evaluation['authorized_by']??'-',1,1);
$pdf->Cell(50,6,"Approved Date",1,0,'L',true);
$pdf->Cell(90,6,$evaluation['approved_date']??'-',1,1);

// Output PDF
$pdf->Output('D','Evaluation_'.$evaluation['full_name'].'.pdf');
