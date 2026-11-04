<?php
require 'db_con.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id || $user_id < 1) { http_response_code(400); exit('Invalid Employee ID'); }
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function isStatutoryName(string $name): bool {
    return (bool)preg_match('/^(PAYE|NSSF|SHIF|SHA|AHL|HOUSING LEVY|AFFORDABLE HOUSING LEVY|PENSION|EMPLOYEE PENSION)$/i', trim($name));
}
$st=$conn->prepare('SELECT full_name,email FROM users WHERE user_id=? LIMIT 1');
$st->bind_param('i',$user_id);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
if (!$user) {http_response_code(404);exit('Employee not found');}
$st=$conn->prepare('SELECT * FROM employee_salary WHERE user_id=? LIMIT 1');
$st->bind_param('i',$user_id);$st->execute();$salary=$st->get_result()->fetch_assoc() ?: [];$st->close();
$allDed=[];
$r=$conn->query('SELECT deduction_id,deduction_name,rate,amount FROM deductions ORDER BY deduction_name ASC');
while($d=$r->fetch_assoc()) {
    if(isStatutoryName($d['deduction_name']))continue;
    $rate=(float)($d['rate']??0);$amount=(float)($d['amount']??0);
    if($rate<=0 && $amount<=0)continue;
    $allDed[]=['id'=>(int)$d['deduction_id'],'name'=>$d['deduction_name'],'rate'=>$rate,'amount'=>$amount,'basis'=>$rate>0?'percent':'fixed'];
}
$validIds=array_column($allDed,'id');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
 try {
    $basic=filter_var($_POST['base_salary']??null,FILTER_VALIDATE_FLOAT);
    $bank=trim((string)($_POST['bank_name']??''));$account=trim((string)($_POST['bank_account']??''));
    if($basic===false||!is_finite((float)$basic)||$basic<0||$bank===''||$account==='')throw new InvalidArgumentException('Enter a valid basic salary and bank details.');
    $names=$_POST['allowance_name']??[];$amounts=$_POST['allowance_amount']??[];
    $ids=$_POST['deduction_id']??[];
    if(!is_array($names)||!is_array($amounts)||!is_array($ids))throw new InvalidArgumentException('Invalid allowance or deduction input.');
    $cleanAllow=[];
    foreach($names as $i=>$name){
        $name=trim((string)$name);$v=filter_var($amounts[$i]??null,FILTER_VALIDATE_FLOAT);
        if($name==='' && ($amounts[$i]??'')==='')continue;
        if($name===''||$v===false||!is_finite((float)$v)||$v<=0)throw new InvalidArgumentException('Each allowance needs a name and positive amount.');
        $cleanAllow[]=[$name,round((float)$v,2)];
    }
    $cleanIds=[];
    foreach($ids as $id){$id=filter_var($id,FILTER_VALIDATE_INT);if($id===false||!in_array($id,$validIds,true))throw new InvalidArgumentException('Invalid custom deduction selection.');$cleanIds[$id]=$id;}
    $conn->begin_transaction();
    $st=$conn->prepare('SELECT salary_id FROM employee_salary WHERE user_id=? LIMIT 1');$st->bind_param('i',$user_id);$st->execute();$exists=$st->get_result()->fetch_assoc();$st->close();
    if($exists){$st=$conn->prepare('UPDATE employee_salary SET base_salary=?,bank_name=?,bank_account=? WHERE user_id=?');$st->bind_param('dssi',$basic,$bank,$account,$user_id);}
    else{$st=$conn->prepare('INSERT INTO employee_salary (base_salary,bank_name,bank_account,user_id) VALUES (?,?,?,?)');$st->bind_param('dssi',$basic,$bank,$account,$user_id);}
    $st->execute();$st->close();
    $st=$conn->prepare('DELETE FROM employee_allowances WHERE user_id=?');$st->bind_param('i',$user_id);$st->execute();$st->close();
    $st=$conn->prepare('INSERT INTO employee_allowances (user_id,allowance_name,amount) VALUES (?,?,?)');
    foreach($cleanAllow as [$name,$amount]){$st->bind_param('isd',$user_id,$name,$amount);$st->execute();}$st->close();
    $st=$conn->prepare('DELETE FROM employee_deductions WHERE user_id=?');$st->bind_param('i',$user_id);$st->execute();$st->close();
    $st=$conn->prepare('INSERT INTO employee_deductions (user_id,deduction_id) VALUES (?,?)');
    foreach($cleanIds as $id){$st->bind_param('ii',$user_id,$id);$st->execute();}$st->close();
    $conn->commit();
    header('Location: payroll_details.php?updated=1');exit;
 } catch(Throwable $e){try{$conn->rollback();}catch(Throwable $ignored){}$error=$e instanceof InvalidArgumentException?$e->getMessage():'Unable to save salary details. Check server logs.';if(!($e instanceof InvalidArgumentException))error_log($e->getMessage());}
}
$allowances=[];$st=$conn->prepare('SELECT allowance_name,amount FROM employee_allowances WHERE user_id=?');$st->bind_param('i',$user_id);$st->execute();$r=$st->get_result();while($a=$r->fetch_assoc())$allowances[]=['name'=>$a['allowance_name'],'amount'=>(float)$a['amount']];$st->close();
$selected=[];$st=$conn->prepare('SELECT deduction_id FROM employee_deductions WHERE user_id=?');$st->bind_param('i',$user_id);$st->execute();$r=$st->get_result();while($d=$r->fetch_assoc())$selected[]=(int)$d['deduction_id'];$st->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Update Salary Details</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"></head>
<body class="bg-gray-100"><?php include 'navbar.php'; ?>
<main class="ml-64 p-6 max-w-5xl mx-auto"><h1 class="text-xl font-bold mb-4 text-gray-700">Update Salary - <?=esc($user['full_name'])?></h1>
<?php if($error):?><div class="bg-red-100 text-red-800 p-3 rounded mb-4"><?=esc($error)?></div><?php endif;?>
<form method="POST" id="salaryForm" class="space-y-6">
<section class="bg-white shadow rounded-lg p-5"><h2 class="font-semibold text-lg mb-3">Basic Salary & Bank</h2><label class="block mb-3">Base Salary (Ksh)<input type="number" min="0" step="0.01" name="base_salary" value="<?=esc($salary['base_salary']??'')?>" class="border rounded px-3 py-2 w-full mt-1" required></label><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><label>Bank Name<input type="text" name="bank_name" value="<?=esc($salary['bank_name']??'')?>" class="border rounded px-3 py-2 w-full mt-1" required></label><label>Bank Account<input type="text" name="bank_account" value="<?=esc($salary['bank_account']??'')?>" class="border rounded px-3 py-2 w-full mt-1" required></label></div></section>
<section class="bg-white shadow rounded-lg p-5"><h2 class="font-semibold text-lg mb-3">Allowances</h2><div id="allowances" class="space-y-2"></div><button type="button" id="addAllowance" class="bg-green-600 text-white px-3 py-2 rounded mt-3">+ Add Allowance</button></section>
<section class="bg-white shadow rounded-lg p-5"><h2 class="font-semibold text-lg mb-2">Custom Deductions</h2><p class="text-sm text-gray-600 mb-3">A positive rate uses a percentage of gross salary; otherwise a positive fixed amount is deducted. Statutory deductions and pension are calculated separately in Process Payroll.</p><div id="deductions" class="space-y-2"></div><button type="button" id="addDeduction" class="bg-red-600 text-white px-3 py-2 rounded mt-3">+ Add Deduction</button></section>
<button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Save Salary Details</button><a href="payroll_details.php" class="inline-block bg-gray-600 text-white px-6 py-2 rounded ml-2">Cancel</a>
</form></main>
<script>
const deductionOptions=<?=json_encode($allDed,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
const initialAllowances=<?=json_encode($allowances,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>;
const initialSelected=<?=json_encode($selected)?>;
function input(type,name,value,placeholder){const el=document.createElement('input');el.type=type;el.name=name;el.value=value??'';el.placeholder=placeholder;el.className='border px-3 py-2 rounded w-full';if(type==='number'){el.step='0.01';el.min='0.01';}el.required=true;return el;}
function removeButton(row){const b=document.createElement('button');b.type='button';b.textContent='Remove';b.className='bg-red-500 text-white px-3 py-2 rounded';b.onclick=()=>row.remove();return b;}
function addAllowanceRow(name='',amount=''){const row=document.createElement('div');row.className='grid grid-cols-1 md:grid-cols-5 gap-2';const a=input('text','allowance_name[]',name,'Allowance Name');a.className+=' md:col-span-2';const v=input('number','allowance_amount[]',amount,'Amount (Ksh)');v.className+=' md:col-span-2';row.append(a,v,removeButton(row));document.getElementById('allowances').appendChild(row);}
function addDeductionRow(id=''){const row=document.createElement('div');row.className='flex flex-wrap gap-2 items-center';const sel=document.createElement('select');sel.name='deduction_id[]';sel.className='border px-3 py-2 rounded flex-1 min-w-0';sel.required=true;const placeholder=document.createElement('option');placeholder.value='';placeholder.textContent='Select deduction';sel.appendChild(placeholder);deductionOptions.forEach(d=>{const opt=document.createElement('option');opt.value=d.id;opt.textContent=d.name+' — '+(d.basis==='percent'?d.rate+'% of gross':'Ksh '+Number(d.amount).toLocaleString('en-KE',{minimumFractionDigits:2}));sel.appendChild(opt);});sel.value=String(id);row.append(sel,removeButton(row));document.getElementById('deductions').appendChild(row);}
document.getElementById('addAllowance').onclick=()=>addAllowanceRow();
document.getElementById('addDeduction').onclick=()=>{if(!deductionOptions.length)return alert('Add a custom deduction first in Payroll Deductions.');addDeductionRow();};
initialAllowances.forEach(a=>addAllowanceRow(a.name,a.amount));initialSelected.filter(id=>deductionOptions.some(d=>d.id===id)).forEach(id=>addDeductionRow(id));
document.getElementById('salaryForm').addEventListener('submit',e=>{const selected=[...document.querySelectorAll('select[name="deduction_id[]"]')].map(x=>x.value);if(selected.some(x=>!x)||new Set(selected).size!==selected.length){e.preventDefault();alert('Select valid deductions without duplicates.');}});
</script></body></html>
