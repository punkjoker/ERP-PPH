<?php
require_once 'sacco_common.php';$error='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  sacco_csrf();$mid=(int)($_POST['sacco_member_id']??0);$type=$_POST['transaction_type']??'';$amount=(float)($_POST['amount']??0);
  $date=$_POST['transaction_date']??'';$month=$_POST['contribution_month']??'';$method=$_POST['payment_method']??'Other';
  $ref=trim($_POST['reference_number']??'');$desc=trim($_POST['description']??'');
  if($mid<1||!in_array($type,['contribution','withdrawal','opening_balance','adjustment_credit','adjustment_debit'],true)||$amount<=0)throw new RuntimeException('Select a member, valid transaction type and amount greater than zero.');
  if(!in_array($method,['Cash','Bank','M-Pesa','Other'],true))throw new RuntimeException('Invalid payment method.');
  $d=DateTime::createFromFormat('Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new RuntimeException('Invalid transaction date.');
  $monthDate=null;if($type==='contribution'&&$month!==''){if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))throw new RuntimeException('Invalid contribution month.');$monthDate=$month.'-01';}
  $q=$conn->prepare("SELECT sacco_member_id FROM hr_sacco_members WHERE sacco_member_id=?");$q->bind_param('i',$mid);$q->execute();if(!$q->get_result()->fetch_assoc())throw new RuntimeException('Membership not found.');$q->close();
  $createdBy=null;foreach(['user_id','id'] as $k)if(isset($_SESSION[$k])&&is_numeric($_SESSION[$k])){$createdBy=(int)$_SESSION[$k];break;}
  $q=$conn->prepare("INSERT INTO hr_sacco_transactions(sacco_member_id,transaction_type,amount,transaction_date,contribution_month,payment_method,reference_number,description,created_by) VALUES(?,?,?,?,?,?,?,?,?)");
  $q->bind_param('isdsssssi',$mid,$type,$amount,$date,$monthDate,$method,$ref,$desc,$createdBy);$q->execute();$q->close();header('Location: record_sacco_contribution.php?saved=1');exit;
 }
}catch(Throwable $e){$error=$e->getMessage();}
$members=$conn->query(sacco_member_select_sql()." WHERE m.status='Active' AND s.status='Active' ORDER BY s.sacco_name,full_name");
require_once 'navbar.php';
?>
<main class="ml-64 p-6 max-w-5xl mx-auto"><div class="mb-6"><h1 class="text-2xl font-bold"><i class="fa-solid fa-hand-holding-dollar text-blue-600 mr-2"></i>Record SACCO Transaction</h1><p class="text-sm text-gray-500">Independent manual contributions and other ledger entries.</p></div>
<?php if(isset($_GET['saved'])):?><div class="bg-green-50 text-green-700 p-3 rounded mb-4">Transaction recorded successfully.</div><?php endif;?><?php if($error):?><div class="bg-red-50 text-red-700 p-3 rounded mb-4"><?=se($error)?></div><?php endif;?>
<div class="bg-white border rounded-xl p-6"><form method="post" class="grid md:grid-cols-2 gap-4">
<input type="hidden" name="csrf_token" value="<?=se($_SESSION['sacco_csrf_token'])?>">
<div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Employee / SACCO *</label><select name="sacco_member_id" required class="w-full border rounded p-3"><option value="">Choose membership</option><?php while($m=$members->fetch_assoc()):?><option value="<?=$m['sacco_member_id']?>"><?=se($m['full_name'].' — '.$m['sacco_name'].($m['sacco_account_no']?' / '.$m['sacco_account_no']:''))?></option><?php endwhile;?></select></div>
<div><label class="block text-sm font-semibold mb-1">Transaction Type *</label><select name="transaction_type" id="txType" class="w-full border rounded p-3"><option value="contribution">Contribution</option><option value="withdrawal">Withdrawal</option><option value="opening_balance">Opening Balance</option><option value="adjustment_credit">Adjustment Credit</option><option value="adjustment_debit">Adjustment Debit</option></select></div>
<div><label class="block text-sm font-semibold mb-1">Amount (KSh) *</label><input type="number" name="amount" min=".01" step=".01" required class="w-full border rounded p-3"></div>
<div><label class="block text-sm font-semibold mb-1">Transaction Date *</label><input type="date" name="transaction_date" value="<?=date('Y-m-d')?>" required class="w-full border rounded p-3"></div>
<div id="monthField"><label class="block text-sm font-semibold mb-1">Contribution Month</label><input type="month" name="contribution_month" value="<?=date('Y-m')?>" class="w-full border rounded p-3"></div>
<div><label class="block text-sm font-semibold mb-1">Payment Method</label><select name="payment_method" class="w-full border rounded p-3"><option>Other</option><option>Cash</option><option>Bank</option><option>M-Pesa</option></select></div>
<div><label class="block text-sm font-semibold mb-1">Reference Number</label><input name="reference_number" maxlength="150" class="w-full border rounded p-3"></div>
<div class="md:col-span-2"><label class="block text-sm font-semibold mb-1">Description / Notes</label><textarea name="description" rows="3" class="w-full border rounded p-3"></textarea></div>
<div class="md:col-span-2 flex gap-3"><button class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold">Save Transaction</button><a href="sacco_transactions.php" class="bg-gray-100 px-6 py-3 rounded-lg">Transaction History</a></div>
</form></div></main>
<script>const t=document.getElementById('txType'),m=document.getElementById('monthField');function sync(){m.style.display=t.value==='contribution'?'block':'none'}t.addEventListener('change',sync);sync();</script>
