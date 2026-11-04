<?php
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Keep PHP errors in server logs on production.

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require 'db_con.php';
require_once 'kenya_statutory_rules.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
$statutoryRules = kenyaStatutoryRules();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['payroll_csrf'])) $_SESSION['payroll_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['payroll_csrf'];
$errors = [];
$notice = $_SESSION['deduction_notice'] ?? null;
unset($_SESSION['deduction_notice']);
function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function safeDeductionLength($value) {
    $value = (string)$value;
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    if (function_exists('iconv_strlen')) {
        $length = iconv_strlen($value, 'UTF-8');
        if ($length !== false) return $length;
    }
    return strlen($value); // Byte-count fallback when Unicode extensions are unavailable.
}
function protectedDeduction($name) {
    return in_array(strtoupper(trim($name)), ['PAYE','NSSF','SHIF','AHL','AFFORDABLE HOUSING LEVY','HOUSING LEVY'], true);
}
function optionalPositiveNumber($value, $label, $maximum, &$errors) {
    $value = trim((string)($value ?? ''));
    if ($value === '') return null;
    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value <= 0 || (float)$value > $maximum) {
        $errors[] = "$label must be greater than zero and no more than $maximum.";
        return null;
    }
    return round((float)$value, 2);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        $errors[] = 'Security token expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add' || $action === 'update') {
            $id = filter_var($_POST['deduction_id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string)($_POST['deduction_name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $rate = optionalPositiveNumber($_POST['rate'] ?? '', 'Rate', 100, $errors);
            $amount = optionalPositiveNumber($_POST['amount'] ?? '', 'Fixed amount', 9999999999.99, $errors);
            if ($name === '' || safeDeductionLength($name) > 255) $errors[] = 'Enter a deduction name (up to 255 characters).';
            if (protectedDeduction($name)) $errors[] = 'This statutory deduction is system-managed.';
            if (($rate === null && $amount === null) || ($rate !== null && $amount !== null)) $errors[] = 'Enter either a percentage rate or a fixed amount, not both.';
            if ($action === 'update' && (!$id || $id < 1)) $errors[] = 'Invalid deduction ID.';
            if ($action === 'update' && $id > 0) {
                $check = $conn->prepare('SELECT deduction_name FROM deductions WHERE deduction_id=?');
                $check->bind_param('i', $id);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();
                $check->close();
                if (!$existing) $errors[] = 'Deduction not found.';
                elseif (protectedDeduction($existing['deduction_name'])) $errors[] = 'System-managed statutory deductions cannot be edited.';
            }
            if (!$errors) {
                if ($action === 'add') {
                    $stmt = $conn->prepare('INSERT INTO deductions (deduction_name, rate, amount, description) VALUES (?, ?, ?, ?)');
                    $stmt->bind_param('sdds', $name, $rate, $amount, $description);
                } else {
                    $stmt = $conn->prepare('UPDATE deductions SET deduction_name=?, rate=?, amount=?, description=? WHERE deduction_id=?');
                    $stmt->bind_param('sddsi', $name, $rate, $amount, $description, $id);
                }
                $stmt->execute();
                $stmt->close();
                $_SESSION['deduction_notice'] = $action === 'add' ? 'Deduction added successfully.' : 'Deduction updated successfully.';
                header('Location: payroll_deductions.php');
                exit;
            }
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['deduction_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) $errors[] = 'Invalid deduction ID.';
            if ($id > 0) {
                $check = $conn->prepare('SELECT deduction_name FROM deductions WHERE deduction_id=?');
                $check->bind_param('i', $id);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();
                $check->close();
                if (!$existing) $errors[] = 'Deduction not found.';
                elseif (protectedDeduction($existing['deduction_name'])) $errors[] = 'System-managed statutory deductions cannot be deleted.';
            }
            if (!$errors) {
                $assigned = $conn->prepare('SELECT COUNT(*) AS total FROM employee_deductions WHERE deduction_id=?');
                $assigned->bind_param('i', $id);
                $assigned->execute();
                $assignedCount = (int)$assigned->get_result()->fetch_assoc()['total'];
                $assigned->close();
                if ($assignedCount > 0) $errors[] = 'Cannot delete a deduction assigned to employees. Unassign it first.';
            }
            if (!$errors) {
                $stmt = $conn->prepare('DELETE FROM deductions WHERE deduction_id=?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['deduction_notice'] = 'Deduction deleted successfully.';
                header('Location: payroll_deductions.php');
                exit;
            }
        } else $errors[] = 'Unknown action.';
    }
}
$deductions = $conn->query('SELECT deduction_id, deduction_name, rate, amount, description FROM deductions ORDER BY deduction_id DESC')->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payroll Deductions</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>
<main class="md:ml-64 p-4 md:p-6">
<div class="max-w-6xl mx-auto">
<h1 class="text-2xl font-bold text-gray-800 mb-6">Payroll Deductions</h1>
<?php if ($notice): ?><div class="bg-green-100 border border-green-300 text-green-800 rounded-lg p-3 mb-5"><?= h($notice) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="bg-red-100 border border-red-300 text-red-800 rounded-lg p-3 mb-5"><ul class="list-disc pl-5"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="bg-white shadow rounded-lg p-6 mb-8">
<div class="flex flex-wrap items-center justify-between gap-2 mb-3"><h2 class="text-lg font-semibold text-blue-800">Mandatory Kenyan Statutory Deductions</h2><span class="text-xs font-semibold bg-blue-100 text-blue-800 rounded-full px-3 py-1">System Managed</span></div>
<p class="text-sm text-gray-600 mb-5">These rules are read from kenya_statutory_rules.php and calculated separately during payroll processing.</p>
<div class="overflow-x-auto"><table class="min-w-full border text-sm"><thead class="bg-blue-100 text-blue-900"><tr><th class="border px-3 py-2 text-left">Deduction</th><th class="border px-3 py-2 text-left">Calculation Type</th><th class="border px-3 py-2 text-left">Rate</th><th class="border px-3 py-2 text-left">Description</th><th class="border px-3 py-2">Status</th></tr></thead><tbody>
<?php foreach ($statutoryRules as $code => $rule): ?>
<tr class="hover:bg-gray-50"><td class="border px-3 py-2 font-medium"><?= h($rule['name'] ?? $code) ?></td><td class="border px-3 py-2"><?= h($rule['type'] ?? '') ?></td><td class="border px-3 py-2"><?php if ($code === 'PAYE'): ?>10% - 35%<?php elseif (isset($rule['rate']) && is_numeric($rule['rate'])): ?><?= number_format((float)$rule['rate'], 2) ?>%<?php else: ?>As defined by rules<?php endif; ?></td><td class="border px-3 py-2"><?= h($rule['description'] ?? '') ?></td><td class="border px-3 py-2 text-center"><span class="text-xs bg-green-100 text-green-700 rounded px-2 py-1">Protected</span></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (!empty($statutoryRules['PAYE']['bands'])): ?>
<h3 class="font-semibold text-gray-700 mt-6 mb-3">PAYE Monthly Tax Brackets</h3><div class="overflow-x-auto"><table class="min-w-full border text-sm"><thead class="bg-gray-100"><tr><th class="border px-3 py-2 text-left">Taxable Income Band</th><th class="border px-3 py-2 text-left">Tax Rate</th></tr></thead><tbody>
<?php foreach ($statutoryRules['PAYE']['bands'] as $index => $band): ?><tr><td class="border px-3 py-2"><?= $band['amount'] === null ? 'Remaining taxable income' : ($index === 0 ? 'First ' : 'Next ') . 'Ksh ' . number_format((float)$band['amount']) ?></td><td class="border px-3 py-2"><?= h($band['rate']) ?>%</td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php if (isset($statutoryRules['PAYE']['personal_relief'])): ?><p class="text-sm text-gray-600 mt-3">Resident employee personal relief: <strong>Ksh <?= number_format((float)$statutoryRules['PAYE']['personal_relief'], 2) ?></strong> per month.</p><?php endif; ?>
</section>
<section class="bg-white shadow rounded-lg p-6 mb-8"><h2 class="text-lg font-semibold mb-4">Add New Deduction</h2>
<form method="post" id="addForm" class="grid grid-cols-1 md:grid-cols-4 gap-4">
<input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="add">
<div><label for="add_name" class="block text-sm font-medium text-gray-700 mb-1">Deduction Name</label><input id="add_name" name="deduction_name" maxlength="255" required class="w-full border rounded-lg px-3 py-2"></div>
<div><label for="add_rate" class="block text-sm font-medium text-gray-700 mb-1">Rate (%)</label><input id="add_rate" name="rate" type="number" min="0.01" max="100" step="0.01" placeholder="Optional" class="w-full border rounded-lg px-3 py-2"></div>
<div><label for="add_amount" class="block text-sm font-medium text-gray-700 mb-1">Fixed Amount (KES)</label><input id="add_amount" name="amount" type="number" min="0.01" step="0.01" placeholder="Optional" class="w-full border rounded-lg px-3 py-2"></div>
<div><label for="add_desc" class="block text-sm font-medium text-gray-700 mb-1">Description</label><input id="add_desc" name="description" class="w-full border rounded-lg px-3 py-2"></div>
<div class="md:col-span-4"><p class="text-sm text-gray-500 mb-3">Enter either a percentage or a fixed amount. Percentage deductions require a defined payroll calculation base.</p><button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg" type="submit">Add Deduction</button></div>
</form></section>
<section class="bg-white shadow rounded-lg overflow-hidden"><div class="p-5 border-b"><h2 class="text-lg font-semibold">Custom Deductions</h2></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-blue-600 text-white"><tr><th class="px-4 py-3 text-left">#</th><th class="px-4 py-3 text-left">Deduction Name</th><th class="px-4 py-3 text-left">Rate (%)</th><th class="px-4 py-3 text-left">Fixed Amount (KES)</th><th class="px-4 py-3 text-left">Description</th><th class="px-4 py-3 text-left">Actions</th></tr></thead><tbody>
<?php foreach ($deductions as $i => $deduction): ?>
<tr class="border-b hover:bg-gray-50"><td class="px-4 py-3"><?= $i + 1 ?></td><td class="px-4 py-3 font-medium"><?= h($deduction['deduction_name']) ?></td><td class="px-4 py-3"><?= $deduction['rate'] !== null ? number_format((float)$deduction['rate'], 2) . '%' : '—' ?></td><td class="px-4 py-3"><?= $deduction['amount'] !== null ? 'KES ' . number_format((float)$deduction['amount'], 2) : '—' ?></td><td class="px-4 py-3"><?= h($deduction['description']) ?></td><td class="px-4 py-3 whitespace-nowrap"><button type="button" class="edit-btn bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-3 py-1 rounded mr-2" data-id="<?= (int)$deduction['deduction_id'] ?>" data-name="<?= h($deduction['deduction_name']) ?>" data-rate="<?= h($deduction['rate']) ?>" data-amount="<?= h($deduction['amount']) ?>" data-description="<?= h($deduction['description']) ?>">Edit</button><form method="post" class="inline" onsubmit="return confirm('Delete this deduction?');"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="deduction_id" value="<?= (int)$deduction['deduction_id'] ?>"><button class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded" type="submit">Delete</button></form></td></tr>
<?php endforeach; ?>
<?php if (!$deductions): ?><tr><td colspan="6" class="text-center py-6 text-gray-500">No custom deductions found.</td></tr><?php endif; ?>
</tbody></table></div></section>
</div></main>
<div id="editModal" class="fixed inset-0 z-50 bg-black bg-opacity-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="editTitle"><div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md"><h2 id="editTitle" class="text-lg font-semibold mb-4">Edit Deduction</h2><form method="post" id="editForm"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="deduction_id" id="edit_id"><div class="mb-3"><label for="edit_name" class="block text-sm font-medium mb-1">Name</label><input id="edit_name" name="deduction_name" required maxlength="255" class="w-full border rounded-lg px-3 py-2"></div><div class="mb-3"><label for="edit_rate" class="block text-sm font-medium mb-1">Rate (%)</label><input id="edit_rate" name="rate" type="number" step="0.01" min="0.01" max="100" class="w-full border rounded-lg px-3 py-2"></div><div class="mb-3"><label for="edit_amount" class="block text-sm font-medium mb-1">Fixed Amount (KES)</label><input id="edit_amount" name="amount" type="number" step="0.01" min="0.01" class="w-full border rounded-lg px-3 py-2"></div><div class="mb-4"><label for="edit_description" class="block text-sm font-medium mb-1">Description</label><textarea id="edit_description" name="description" class="w-full border rounded-lg px-3 py-2"></textarea></div><div class="flex justify-end gap-2"><button type="button" id="closeModal" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Cancel</button><button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update</button></div></form></div></div>
<script>
function bindExclusive(form) {
 const rate=form.querySelector('[name="rate"]'), amount=form.querySelector('[name="amount"]');
 rate.addEventListener('input',()=>{if(rate.value!=='')amount.value='';});
 amount.addEventListener('input',()=>{if(amount.value!=='')rate.value='';});
 form.addEventListener('submit',e=>{if((rate.value.trim()==='')===(amount.value.trim()==='')){e.preventDefault();alert('Enter either a rate or a fixed amount, not both.');}});
}
bindExclusive(document.getElementById('addForm'));
bindExclusive(document.getElementById('editForm'));
const modal=document.getElementById('editModal');
document.querySelectorAll('.edit-btn').forEach(button=>button.addEventListener('click',()=>{
 document.getElementById('edit_id').value=button.dataset.id;
 document.getElementById('edit_name').value=button.dataset.name;
 document.getElementById('edit_rate').value=button.dataset.rate;
 document.getElementById('edit_amount').value=button.dataset.amount;
 document.getElementById('edit_description').value=button.dataset.description;
 modal.classList.remove('hidden');modal.classList.add('flex');
}));
function closeModal(){modal.classList.add('hidden');modal.classList.remove('flex');}
document.getElementById('closeModal').addEventListener('click',closeModal);
modal.addEventListener('click',e=>{if(e.target===modal)closeModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
</script>
</body></html>
