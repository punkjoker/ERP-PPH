<?php
require 'db_con.php';
require_once 'kenya_payroll_calculator.php';
require_once 'pension_config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {http_response_code(405);exit('POST required.');}
function payrollDate($value,string $label): ?string {
    if ($value===null || trim((string)$value)==='')return null;
    $value=trim((string)$value);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if(!$d||$d->format('Y-m-d')!==$value)throw new InvalidArgumentException('Invalid '.$label.'.');
    return $value;
}
try {
    $userId=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);
    $month=trim((string)($_POST['month']??''));
    $year=filter_var($_POST['year']??null,FILTER_VALIDATE_INT);
    if(!$userId||!$year||$year<2000||$year>2100)throw new InvalidArgumentException('Invalid employee or year.');
    kenyaPayrollPeriod($month,$year);
    $no=trim((string)($_POST['payroll_no']??''));
    if(strlen($no)>100)throw new InvalidArgumentException('Payroll number too long.');
    $preparedBy=trim((string)($_POST['prepared_by']??''));
    $preparedDate=payrollDate($_POST['date_prepared']??'','preparation date');
    $preparation=(string)($_POST['preparation_status']??'Pending');
    $approvedBy=trim((string)($_POST['approved_by']??''));
    $approvedDate=payrollDate($_POST['approval_date']??'','approval date');
    $approval=(string)($_POST['approval_status']??'Pending');
    $accountant=trim((string)($_POST['accountant_name']??''));
    $paymentDate=payrollDate($_POST['payment_date']??'','payment date');
    $payment=(string)($_POST['payment_status']??'Pending Pay');
    if(!in_array($preparation,['Pending','Prepared'],true)||!in_array($approval,['Pending','Approved'],true)||!in_array($payment,['Pending Pay','Paid'],true))throw new InvalidArgumentException('Invalid workflow status.');
    if($preparation==='Prepared'&&(!$preparedBy||!$preparedDate))throw new InvalidArgumentException('Enter preparer and date.');
    if($approval==='Approved'&&(!$approvedBy||!$approvedDate))throw new InvalidArgumentException('Enter approver and date.');
    if($payment==='Paid'&&(!$accountant||!$paymentDate))throw new InvalidArgumentException('Enter accountant and payment date.');
    if($approval==='Approved'&&$preparation!=='Prepared')throw new InvalidArgumentException('Prepare payroll before approving it.');
    if($payment==='Paid'&&$approval!=='Approved')throw new InvalidArgumentException('Approve payroll before marking it paid.');
    // Caller authentication and role-based authorization must be enforced by the host application.
    $conn->begin_transaction();
    $stmt=$conn->prepare('SELECT full_name FROM users WHERE user_id=? LIMIT 1');
    $stmt->bind_param('i',$userId);$stmt->execute();$employee=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$employee)throw new InvalidArgumentException('Employee not found.');
    $stmt=$conn->prepare('SELECT * FROM payroll_records WHERE user_id=? AND month=? AND year=? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('isi',$userId,$month,$year);$stmt->execute();$existing=$stmt->get_result()->fetch_assoc();$stmt->close();
    if($existing){
        $oldApproved=($existing['approval_status']==='Approved');$oldPaid=($existing['payment_status']==='Paid');
        if($oldPaid && $payment!=='Paid')throw new InvalidArgumentException('Paid payroll cannot be reopened here.');
        if($oldApproved && $approval!=='Approved')throw new InvalidArgumentException('Approved payroll cannot be unapproved here.');
        $oldDetails=json_decode((string)($existing['details']??'{}'),true);
        $legacy=($oldDetails['calculation']['rule_set']??'')!=='KE_STANDARD_2026_02_PENSION';
        if($oldApproved||$oldPaid||$legacy){
            // Workflow-only update: financial fields are left completely unchanged.
            $stmt=$conn->prepare('UPDATE payroll_records SET payroll_no=?,prepared_by=?,date_prepared=?,preparation_status=?,approved_by=?,approval_date=?,approval_status=?,accountant_name=?,payment_date=?,payment_status=? WHERE payroll_id=?');
            $id=(int)$existing['payroll_id'];
            $stmt->bind_param('ssssssssssi',$no,$preparedBy,$preparedDate,$preparation,$approvedBy,$approvedDate,$approval,$accountant,$paymentDate,$payment,$id);
            $stmt->execute();$stmt->close();
            $conn->commit();echo 'Payroll workflow updated; existing financial amounts preserved.';exit;
        }
    }
    $stmt=$conn->prepare('SELECT base_salary FROM employee_salary WHERE user_id=? LIMIT 1');
    $stmt->bind_param('i',$userId);$stmt->execute();$salary=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$salary)throw new InvalidArgumentException('Employee salary not configured.');
    $allowances=json_decode((string)($_POST['allowances']??'[]'),true,512,JSON_THROW_ON_ERROR);
    $custom=json_decode((string)($_POST['other_deductions']??'[]'),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($allowances)||!is_array($custom))throw new InvalidArgumentException('Invalid payroll lines.');
    $pension=pensionConfigFromPost($_POST);
    $calc=kenyaStandardPayroll((float)$salary['base_salary'],$allowances,$custom,$month,$year,$pension);
    $base=$calc['base_salary'];$allow=$calc['total_allowances'];$deduct=$calc['total_deductions'];$net=$calc['net_pay'];
    $details=json_encode($calc['details'],JSON_THROW_ON_ERROR);
    $fullName=$employee['full_name'];
    if($existing){
        $id=(int)$existing['payroll_id'];
        $stmt=$conn->prepare('UPDATE payroll_records SET payroll_no=?,full_name=?,base_salary=?,total_allowances=?,total_deductions=?,net_pay=?,details=?,prepared_by=?,date_prepared=?,preparation_status=?,approved_by=?,approval_date=?,approval_status=?,accountant_name=?,payment_date=?,payment_status=? WHERE payroll_id=?');
        $stmt->bind_param('ssddddssssssssssi',$no,$fullName,$base,$allow,$deduct,$net,$details,$preparedBy,$preparedDate,$preparation,$approvedBy,$approvedDate,$approval,$accountant,$paymentDate,$payment,$id);
    } else {
        $stmt=$conn->prepare('INSERT INTO payroll_records (user_id,payroll_no,full_name,base_salary,total_allowances,total_deductions,net_pay,month,year,details,prepared_by,date_prepared,preparation_status,approved_by,approval_date,approval_status,accountant_name,payment_date,payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('issddddsissssssssss',$userId,$no,$fullName,$base,$allow,$deduct,$net,$month,$year,$details,$preparedBy,$preparedDate,$preparation,$approvedBy,$approvedDate,$approval,$accountant,$paymentDate,$payment);
    }
    $stmt->execute();$stmt->close();
    storePensionConfig($conn,$userId,$pension);
    $conn->commit();
    echo $existing?'Payroll updated successfully.':'Payroll saved successfully.';
} catch(InvalidArgumentException|JsonException $e){
    try{$conn->rollback();}catch(Throwable $ignored){}
    http_response_code(400);echo $e->getMessage();
} catch(Throwable $e){
    try{$conn->rollback();}catch(Throwable $ignored){}
    error_log('Payroll save [' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Unable to save payroll. Check server logs.';
}
