<?php
require_once 'sacco_common.php';
$error=''; $notice='';
try {
 if($_SERVER['REQUEST_METHOD']==='POST'){
  sacco_csrf(); $action=$_POST['action']??'';
  if($action==='add_loan'){
   $mid=(int)($_POST['sacco_member_id']??0); $principal=(float)($_POST['principal_amount']??0);
   $interest=(float)($_POST['interest_amount']??0); $date=$_POST['issue_date']??'';
   $due=trim($_POST['due_date']??''); $ref=trim($_POST['loan_reference']??''); $notes=trim($_POST['notes']??'');
   if($mid<1||$principal<=0||$interest<0) throw new RuntimeException('Select a member and enter a valid loan amount. Interest cannot be negative.');
   $d=DateTime::createFromFormat('Y-m-d',$date); if(!$d||$d->format('Y-m-d')!==$date) throw new RuntimeException('Enter a valid loan issue date.');
   if($due!=='' && (!DateTime::createFromFormat('Y-m-d',$due)||DateTime::createFromFormat('Y-m-d',$due)->format('Y-m-d')!==$due)) throw new RuntimeException('Enter a valid due date.');
   $q=$conn->prepare("SELECT sacco_member_id FROM hr_sacco_members WHERE sacco_member_id=? AND status='Active'");$q->bind_param('i',$mid);$q->execute();$ok=$q->get_result()->fetch_assoc();$q->close();if(!$ok)throw new RuntimeException('Active SACCO membership not found.');
   $q=$conn->prepare("INSERT INTO hr_sacco_loans(sacco_member_id,loan_reference,principal_amount,interest_amount,issue_date,due_date,notes,status) VALUES(?,?,?,?,?,?,?,'Active')");
   $q->bind_param('isddsss',$mid,$ref,$principal,$interest,$date,$due,$notes);$q->execute();$q->close();header('Location: sacco_loans.php?saved=loan');exit;
  }
  if($action==='repay'){
   $loanId=(int)($_POST['loan_id']??0);$amount=(float)($_POST['amount']??0);$date=$_POST['repayment_date']??'';
   $method=$_POST['payment_method']??'Other';$ref=trim($_POST['reference_number']??'');$notes=trim($_POST['notes']??'');
   if($loanId<1||$amount<=0)throw new RuntimeException('Select a loan and enter a repayment greater than zero.');
   if(!in_array($method,['Cash','Bank','M-Pesa','Other'],true))throw new RuntimeException('Invalid payment method.');
   $d=DateTime::createFromFormat('Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new RuntimeException('Enter a valid repayment date.');
   $conn->begin_transaction();
   try{
    $q=$conn->prepare("SELECT loan_id,principal_amount,interest_amount FROM hr_sacco_loans WHERE loan_id=? FOR UPDATE");
    $q->bind_param('i',$loanId);$q->execute();$loan=$q->get_result()->fetch_assoc();$q->close();if(!$loan)throw new RuntimeException('Loan not found.');
    $q=$conn->prepare("SELECT COALESCE(SUM(amount),0) repaid FROM hr_sacco_loan_repayments WHERE loan_id=?");$q->bind_param('i',$loanId);$q->execute();$repaid=(float)$q->get_result()->fetch_assoc()['repaid'];$q->close();
    $balance=(float)$loan['principal_amount']+(float)$loan['interest_amount']-$repaid;
    if($amount>$balance+0.00001)throw new RuntimeException('Repayment exceeds the outstanding balance of KSh '.number_format($balance,2).'.');
    $createdBy=null;foreach(['user_id','id'] as $k)if(isset($_SESSION[$k])&&is_numeric($_SESSION[$k])){$createdBy=(int)$_SESSION[$k];break;}
    $q=$conn->prepare("INSERT INTO hr_sacco_loan_repayments(loan_id,amount,repayment_date,payment_method,reference_number,notes,created_by) VALUES(?,?,?,?,?,?,?)");
    $q->bind_param('idssssi',$loanId,$amount,$date,$method,$ref,$notes,$createdBy);$q->execute();$q->close();
    $conn->query("UPDATE hr_sacco_loans SET status='Closed' WHERE loan_id=".(int)$loanId." AND principal_amount+interest_amount <= (SELECT COALESCE(SUM(amount),0) FROM hr_sacco_loan_repayments WHERE loan_id=".(int)$loanId.")");
    $conn->commit();
   }catch(Throwable $ex){$conn->rollback();throw $ex;}
   header('Location: sacco_loans.php?saved=repayment');exit;
  }
 }
}catch(Throwable $e){$error=$e->getMessage();}
if(($_GET['saved']??'')==='loan')$notice='New loan added. It is tracked separately from the member’s other loans.';
if(($_GET['saved']??'')==='repayment')$notice='Loan repayment recorded.';
$members=$conn->query(sacco_member_select_sql()." WHERE m.status='Active' AND s.status='Active' ORDER BY s.sacco_name,full_name")->fetch_all(MYSQLI_ASSOC);
$sql="SELECT l.*,m.sacco_member_id,s.sacco_name,COALESCE(NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),''),u.email,CONCAT('User #',u.user_id)) full_name,COALESCE(SUM(r.amount),0) total_repaid,(l.principal_amount+l.interest_amount-COALESCE(SUM(r.amount),0)) outstanding FROM hr_sacco_loans l JOIN hr_sacco_members m ON m.sacco_member_id=l.sacco_member_id JOIN hr_saccos s ON s.sacco_id=m.sacco_id JOIN users u ON u.user_id=m.user_id LEFT JOIN employees e ON e.employee_id=u.employee_id LEFT JOIN hr_sacco_loan_repayments r ON r.loan_id=l.loan_id GROUP BY l.loan_id,m.sacco_member_id,s.sacco_name,full_name ORDER BY l.issue_date DESC,l.loan_id DESC";
$loans=$conn->query($sql)->fetch_all(MYSQLI_ASSOC);
require_once 'navbar.php';
?>
<main class="ml-64 p-6 max-w-7xl mx-auto">
 <div class="mb-6"><h1 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-hand-holding-dollar text-blue-600 mr-2"></i>SACCO Loan Tracking</h1><p class="text-sm text-gray-500">Register every loan separately and track repayments and outstanding balances. This module does not change payroll.</p></div>
 <?php if($notice):?><div class="bg-green-50 text-green-700 border p-3 rounded mb-4"><?=se($notice)?></div><?php endif;?>
 <?php if($error):?><div class="bg-red-50 text-red-700 border p-3 rounded mb-4"><?=se($error)?></div><?php endif;?>
 <div class="grid xl:grid-cols-2 gap-6 mb-6">
  <section class="bg-white border rounded-xl p-5"><h2 class="font-bold mb-4">Add New Loan</h2><form method="post" class="grid md:grid-cols-2 gap-3"><input type="hidden" name="csrf_token" value="<?=se($_SESSION['sacco_csrf_token'])?>"><input type="hidden" name="action" value="add_loan">
   <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Employee / SACCO *</label><select name="sacco_member_id" required class="w-full border rounded p-3"><option value="">Choose membership</option><?php foreach($members as $m):?><option value="<?=$m['sacco_member_id']?>"><?=se($m['full_name'].' — '.$m['sacco_name'])?></option><?php endforeach;?></select></div>
   <div><label class="block text-sm font-semibold mb-1">Loan Amount (KSh) *</label><input type="number" name="principal_amount" min=".01" step=".01" required class="w-full border rounded p-3"></div>
   <div><label class="block text-sm font-semibold mb-1">Interest (KSh)</label><input type="number" name="interest_amount" min="0" step=".01" value="0" class="w-full border rounded p-3"></div>
   <div><label class="block text-sm font-semibold mb-1">Issue Date *</label><input type="date" name="issue_date" value="<?=date('Y-m-d')?>" required class="w-full border rounded p-3"></div>
   <div><label class="block text-sm font-semibold mb-1">Due Date</label><input type="date" name="due_date" class="w-full border rounded p-3"></div>
   <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Loan Reference</label><input name="loan_reference" maxlength="100" class="w-full border rounded p-3" placeholder="Optional unique reference"></div>
   <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border rounded p-3"></textarea></div>
   <div class="md:col-span-2"><button class="bg-blue-600 text-white px-5 py-3 rounded-lg font-semibold">Add Loan</button></div>
  </form></section>
  <section class="bg-white border rounded-xl p-5"><h2 class="font-bold mb-4">Record Loan Repayment</h2><form method="post" class="grid md:grid-cols-2 gap-3"><input type="hidden" name="csrf_token" value="<?=se($_SESSION['sacco_csrf_token'])?>"><input type="hidden" name="action" value="repay">
   <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Loan *</label><select name="loan_id" required class="w-full border rounded p-3"><option value="">Choose loan</option><?php foreach($loans as $l):if($l['outstanding']>0.009):?><option value="<?=$l['loan_id']?>"><?=se($l['full_name'].' — '.$l['sacco_name'].' — '.($l['loan_reference']?:'Loan #'.$l['loan_id']).' — Balance KSh '.number_format($l['outstanding'],2))?></option><?php endif;endforeach;?></select></div>
   <div><label class="block text-sm font-semibold mb-1">Repayment Amount (KSh) *</label><input type="number" name="amount" min=".01" step=".01" required class="w-full border rounded p-3"></div>
   <div><label class="block text-sm font-semibold mb-1">Repayment Date *</label><input type="date" name="repayment_date" value="<?=date('Y-m-d')?>" required class="w-full border rounded p-3"></div>
   <div><label class="block text-sm font-semibold mb-1">Payment Method</label><select name="payment_method" class="w-full border rounded p-3"><option>Other</option><option>Cash</option><option>Bank</option><option>M-Pesa</option></select></div>
   <div><label class="block text-sm font-semibold mb-1">Reference Number</label><input name="reference_number" maxlength="150" class="w-full border rounded p-3"></div>
   <div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border rounded p-3"></textarea></div>
   <div class="md:col-span-2"><button class="bg-emerald-600 text-white px-5 py-3 rounded-lg font-semibold">Record Repayment</button></div>
  </form></section>
 </div>
 <section class="bg-white border rounded-xl overflow-hidden"><div class="p-4 border-b"><h2 class="font-bold">Loan Register</h2><input id="loanSearch" placeholder="Search employee, SACCO or reference" class="border rounded p-2 mt-3 w-full"></div><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-100"><tr><th class="p-3 text-left">Employee / SACCO</th><th class="p-3 text-left">Loan Ref.</th><th class="p-3">Issue Date</th><th class="p-3 text-right">Loan Amount</th><th class="p-3 text-right">Repaid</th><th class="p-3 text-right">Outstanding</th><th class="p-3">Status</th><th class="p-3">Statement</th></tr></thead><tbody>
 <?php if($loans):foreach($loans as $l):?><tr class="loan-row border-t"><td class="p-3"><b><?=se($l['full_name'])?></b><div class="text-xs text-gray-500"><?=se($l['sacco_name'])?></div></td><td class="p-3"><?=se($l['loan_reference']?:'Loan #'.$l['loan_id'])?></td><td class="p-3 text-center"><?=se($l['issue_date'])?></td><td class="p-3 text-right">KSh <?=sm($l['principal_amount']+$l['interest_amount'])?></td><td class="p-3 text-right">KSh <?=sm($l['total_repaid'])?></td><td class="p-3 text-right font-semibold">KSh <?=sm(max(0,$l['outstanding']))?></td><td class="p-3 text-center"><span class="px-2 py-1 rounded text-xs <?=$l['outstanding']<=0.009?'bg-green-100 text-green-700':'bg-amber-100 text-amber-700'?>"><?=$l['outstanding']<=0.009?'Closed':'Active'?></span></td><td class="p-3 text-center"><a class="text-blue-600 underline" href="sacco_loan_statement.php?id=<?=$l['loan_id']?>">View</a></td></tr><?php endforeach;else:?><tr><td colspan="8" class="p-8 text-center text-gray-500">No loans recorded yet.</td></tr><?php endif;?>
 </tbody></table></div></section>
</main><script>document.getElementById('loanSearch').addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('.loan-row').forEach(r=>r.style.display=r.innerText.toLowerCase().includes(q)?'':'none')});</script>
<?php
