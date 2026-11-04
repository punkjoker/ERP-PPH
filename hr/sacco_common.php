<?php
require_once __DIR__ . '/db_con.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['sacco_csrf_token'])) $_SESSION['sacco_csrf_token'] = bin2hex(random_bytes(32));
function se($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sm($v): string { return number_format((float)$v, 2); }
function sacco_csrf(): void {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['sacco_csrf_token'] ?? '', $_POST['csrf_token']))
        throw new RuntimeException('Invalid or expired form token. Refresh and try again.');
}
function sacco_employee_options(mysqli $conn): array {
    $uc=[]; $ec=[];
    foreach($conn->query("SHOW COLUMNS FROM users") as $r) $uc[strtolower($r['Field'])]=$r['Field'];
    foreach($conn->query("SHOW COLUMNS FROM employees") as $r) $ec[strtolower($r['Field'])]=$r['Field'];
    $uid=$uc['id']??$uc['user_id']??null; $eid=$ec['employee_id']??null;
    if(!$uid||!$eid) throw new RuntimeException('Cannot identify users/employees ID columns. Check table structures.');
    if(isset($uc['employee_id'])) $join="e.`$eid`=u.`{$uc['employee_id']}`";
    elseif(isset($uc['email'],$ec['email'])) $join="LOWER(TRIM(e.`{$ec['email']}`))=LOWER(TRIM(u.`{$uc['email']}`))";
    else throw new RuntimeException('Could not determine users-to-employees link. Expected users.employee_id or matching email columns.');
    $name=isset($ec['first_name'],$ec['last_name'])
        ? "TRIM(CONCAT(COALESCE(e.`{$ec['first_name']}`,''),' ',COALESCE(e.`{$ec['last_name']}`,'')))"
        : (isset($ec['full_name']) ? "e.`{$ec['full_name']}`" : "CONCAT('Employee #',e.`$eid`)");
    $where=[];
    if(isset($ec['status'])) $where[]="LOWER(TRIM(e.`{$ec['status']}`))='active'";
    if(isset($uc['group_id'])) $where[]="u.`{$uc['group_id']}`=9";
    $sql="SELECT u.`$uid` user_id,e.`$eid` employee_id,$name full_name".
         (isset($ec['email'])?",e.`{$ec['email']}` email":",'' email").
         " FROM users u JOIN employees e ON $join ".($where?'WHERE '.implode(' AND ',$where):'')." ORDER BY full_name";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}
function sacco_balance(mysqli $conn,int $id): float {
    $q=$conn->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_type IN ('opening_balance','contribution','adjustment_credit') THEN amount ELSE -amount END),0) bal FROM hr_sacco_transactions WHERE sacco_member_id=?");
    $q->bind_param('i',$id);$q->execute();$v=(float)$q->get_result()->fetch_assoc()['bal'];$q->close();return $v;
}
function sacco_member_select_sql(): string {
    return "SELECT m.sacco_member_id,m.user_id,m.sacco_id,m.sacco_account_no,m.membership_date,m.expected_monthly_contribution,m.status,s.sacco_name,COALESCE(NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),''),u.email,CONCAT('User #',u.user_id)) full_name FROM hr_sacco_members m JOIN hr_saccos s ON s.sacco_id=m.sacco_id JOIN users u ON u.user_id=m.user_id LEFT JOIN employees e ON e.employee_id=u.employee_id";
}
function sacco_type_label($v): string { return ucwords(str_replace('_',' ',$v)); }
?>
