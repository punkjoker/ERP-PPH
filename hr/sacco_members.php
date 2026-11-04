<?php
require_once 'sacco_common.php';
$error=''; $notice='';
try {
 if($_SERVER['REQUEST_METHOD']==='POST'){
  sacco_csrf(); $action=$_POST['action']??'';
  if($action==='add'){
   $sid=(int)($_POST['sacco_id']??0);$uid=(int)($_POST['user_id']??0);
   $acct=trim($_POST['sacco_account_no']??'');$date=$_POST['membership_date']?:null;
   $expected=(float)($_POST['expected_monthly_contribution']??0);$opening=(float)($_POST['opening_contribution']??0);$notes=trim($_POST['notes']??'');
   if($sid<1||$uid<1||$expected<0||$opening<0)throw new RuntimeException('Select a SACCO and employee and enter valid non-negative amounts.');
   $conn->begin_transaction();
   try {
    $q=$conn->prepare("INSERT INTO hr_sacco_members(sacco_id,user_id,sacco_account_no,membership_date,expected_monthly_contribution,status,notes) VALUES(?,?,?,?,?,'Active',?)");
    $q->bind_param('iissds',$sid,$uid,$acct,$date,$expected,$notes);$q->execute();$mid=(int)$conn->insert_id;$q->close();
    if($opening>0){$openingDate=$date?:date('Y-m-d');$desc='Opening contribution balance brought forward when SACCO tracking started.';$method='Other';$ref='OPENING';$createdBy=null;
     $q=$conn->prepare("INSERT INTO hr_sacco_transactions(sacco_member_id,transaction_type,amount,transaction_date,contribution_month,payment_method,reference_number,description,created_by) VALUES(?,'opening_balance',?,?,NULL,?,?,?,?)");
     $q->bind_param('idssssi',$mid,$opening,$openingDate,$method,$ref,$desc,$createdBy);$q->execute();$q->close();}
    $conn->commit();
   } catch(Throwable $ex){$conn->rollback();throw $ex;}
   header('Location: sacco_members.php?done=added');exit;
  }
  if($action==='status'){
   $mid=(int)$_POST['sacco_member_id'];$status=$_POST['status']??'';
   if($mid<1||!in_array($status,['Active','Inactive'],true))throw new RuntimeException('Invalid membership status.');
   $q=$conn->prepare("UPDATE hr_sacco_members SET status=? WHERE sacco_member_id=?");$q->bind_param('si',$status,$mid);$q->execute();$q->close();
   header('Location: sacco_members.php?done=status');exit;
  }
 }
} catch(Throwable $e){$error=$e->getMessage();}
if(($_GET['done']??'')==='added')$notice='Employee registered in the SACCO.';
if(($_GET['done']??'')==='status')$notice='Membership status updated.';
$saccos=$conn->query("SELECT sacco_id,sacco_name FROM hr_saccos WHERE status='Active' ORDER BY sacco_name")->fetch_all(MYSQLI_ASSOC);
$employees=[];try{$employees=sacco_employee_options($conn);}catch(Throwable $e){$error=$error?:$e->getMessage();}
$members=$conn->query(sacco_member_select_sql()." ORDER BY s.sacco_name,full_name");
require_once 'navbar.php';
?>
<main class="ml-64 p-6 max-w-7xl mx-auto">
 <div class="mb-6"><h1 class="text-2xl font-bold text-gray-800"><i class="fa-solid fa-users text-blue-600 mr-2"></i>SACCO Members</h1><p class="text-sm text-gray-500">Register employees in multiple SACCOs and bring forward their existing contribution balances.</p></div>
 <?php if($notice):?><div class="bg-green-50 text-green-700 border p-3 rounded mb-4"><?=se($notice)?></div><?php endif;?>
 <?php if($error):?><div class="bg-red-50 text-red-700 border p-3 rounded mb-4"><?=se($error)?></div><?php endif;?>
 <div class="grid lg:grid-cols-3 gap-6">
 <section class="bg-white border rounded-xl p-5 h-fit"><h2 class="font-bold mb-4">Register Membership</h2>
 <form method="post" class="space-y-4"><input type="hidden" name="csrf_token" value="<?=se($_SESSION['sacco_csrf_token'])?>"><input type="hidden" name="action" value="add">
 <div><label class="block text-sm font-semibold mb-1">SACCO *</label><select name="sacco_id" required class="w-full border rounded p-3"><option value="">Select SACCO</option><?php foreach($saccos as $s):?><option value="<?=$s['sacco_id']?>"><?=se($s['sacco_name'])?></option><?php endforeach;?></select></div>
 <div><label class="block text-sm font-semibold mb-1">Employee *</label><select name="user_id" required class="w-full border rounded p-3"><option value="">Select employee</option><?php foreach($employees as $e):?><option value="<?=$e['user_id']?>"><?=se($e['full_name'])?><?=($e['email']?' — '.se($e['email']):'')?></option><?php endforeach;?></select></div>
 <div><label class="block text-sm font-semibold mb-1">SACCO Account Number</label><input name="sacco_account_no" maxlength="100" class="w-full border rounded p-3"></div>
 <div><label class="block text-sm font-semibold mb-1">Membership Date</label><input type="date" name="membership_date" class="w-full border rounded p-3"></div>
 <div><label class="block text-sm font-semibold mb-1">Expected Monthly Contribution (KSh)</label><input type="number" name="expected_monthly_contribution" min="0" step=".01" value="0" class="w-full border rounded p-3" required></div>
 <div><label class="block text-sm font-semibold mb-1">Previously Contributed / Opening Balance (KSh)</label><input type="number" name="opening_contribution" min="0" step=".01" value="0" class="w-full border rounded p-3"><p class="text-xs text-gray-500 mt-1">Enter the member’s accumulated contributions before tracking began. Leave 0 if none.</p></div>
 <div><label class="block text-sm font-semibold mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border rounded p-3"></textarea></div>
 <button class="w-full bg-blue-600 text-white rounded-lg p-3 font-semibold" <?=(!$saccos||!$employees)?'disabled':''?>>Register Member</button></form></section>
 <section class="lg:col-span-2 bg-white border rounded-xl overflow-hidden"><div class="p-4 border-b"><h2 class="font-bold">Membership Register</h2><input id="search" placeholder="Search employee or SACCO" class="border rounded p-2 mt-3 w-full"></div>
 <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-100"><tr><th class="p-3 text-left">Employee / SACCO</th><th class="p-3 text-left">Account</th><th class="p-3 text-right">Expected / Month</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody>
 <?php if($members->num_rows):while($m=$members->fetch_assoc()):?><tr class="member-row border-t"><td class="p-3"><b><?=se($m['full_name'])?></b><div class="text-xs text-gray-500"><?=se($m['sacco_name'])?></div></td><td class="p-3"><?=se($m['sacco_account_no']?:'—')?></td><td class="p-3 text-right"><?=sm($m['expected_monthly_contribution'])?></td><td class="p-3 text-center"><?=se($m['status'])?></td><td class="p-3 text-center"><form method="post" onsubmit="return confirm('Change membership status?')"><input type="hidden" name="csrf_token" value="<?=se($_SESSION['sacco_csrf_token'])?>"><input type="hidden" name="action" value="status"><input type="hidden" name="sacco_member_id" value="<?=$m['sacco_member_id']?>"><input type="hidden" name="status" value="<?=$m['status']==='Active'?'Inactive':'Active'?>"><button class="text-blue-600 underline"><?=$m['status']==='Active'?'Deactivate':'Activate'?></button></form></td></tr><?php endwhile;else:?><tr><td colspan="5" class="p-8 text-center text-gray-500">No memberships recorded.</td></tr><?php endif;?>
 </tbody></table></div></section></div></main>
<script>document.getElementById('search').addEventListener('input',function(){let q=this.value.toLowerCase();document.querySelectorAll('.member-row').forEach(r=>r.style.display=r.innerText.toLowerCase().includes(q)?'':'none')});</script>
