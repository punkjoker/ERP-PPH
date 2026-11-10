<?php
require 'db_con.php';
require_once 'kenya_payroll_calculator.php';
require_once 'pension_config.php';

function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$user_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$user_id) {
    http_response_code(400);
    exit('Invalid employee ID');
}

$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$dt = DateTimeImmutable::createFromFormat('!Y-m', $selectedMonth);
$payrollMonth = $dt->format('F');
$payrollYear = (int)$dt->format('Y');

$stmt = $conn->prepare('SELECT full_name,email,national_id FROM users WHERE user_id=? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    exit('Employee not found');
}

$stmt = $conn->prepare('SELECT * FROM employee_salary WHERE user_id=? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$salary = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$salary) {
    http_response_code(400);
    exit('Please configure employee salary first.');
}

$stmt = $conn->prepare('SELECT * FROM payroll_records WHERE user_id=? AND month=? AND year=? LIMIT 1');
$stmt->bind_param('isi', $user_id, $payrollMonth, $payrollYear);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isExisting = (bool)$existing;
$pensionConfig = loadPensionConfig($conn, $user_id);

$workflow = $existing ?: [
    'payroll_no' => '',
    'prepared_by' => '',
    'date_prepared' => '',
    'preparation_status' => 'Pending',
    'approved_by' => '',
    'approval_date' => '',
    'approval_status' => 'Pending',
    'accountant_name' => '',
    'payment_date' => '',
    'payment_status' => 'Pending Pay'
];

$saved = json_decode((string)($existing['details'] ?? '{}'), true);
if (!is_array($saved)) {
    $saved = [];
}

if ($isExisting && isset($saved['pension'])) {
    $pensionConfig = [
        'enabled' => (bool)($saved['pension']['enabled'] ?? false),
        'tax_qualified' => (bool)($saved['pension']['tax_qualified'] ?? false)
    ];
}

$allowances = [];
$custom = [];

if ($isExisting) {
    foreach (($saved['allowances'] ?? []) as $a) {
        if (is_array($a)) {
            $allowances[] = [
                'name' => (string)($a['name'] ?? ''),
                'amount' => (float)($a['amount'] ?? 0)
            ];
        }
    }

    foreach (($saved['deductions'] ?? []) as $d) {
        if (!is_array($d)) {
            continue;
        }

        $name = (string)($d['name'] ?? '');

        if (preg_match('/^(PAYE|NSSF|SHIF|SHA|AHL|HOUSING LEVY|AFFORDABLE HOUSING LEVY|PENSION|EMPLOYEE PENSION)$/i', $name)) {
            continue;
        }

        if (in_array(($d['type'] ?? ''), ['statutory', 'pension'], true)) {
            continue;
        }

        $custom[] = [
            'name' => $name,
            'type' => (string)($d['basis'] ?? 'fixed'),
            'value' => (float)($d['value'] ?? $d['amount'] ?? 0)
        ];
    }
} else {
    $stmt = $conn->prepare('SELECT allowance_name,amount FROM employee_allowances WHERE user_id=?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $r = $stmt->get_result();

    while ($a = $r->fetch_assoc()) {
        $allowances[] = [
            'name' => $a['allowance_name'],
            'amount' => (float)$a['amount']
        ];
    }
    $stmt->close();

    $stmt = $conn->prepare('
        SELECT d.deduction_name,d.rate,d.amount
        FROM employee_deductions ed
        JOIN deductions d ON d.deduction_id=ed.deduction_id
        WHERE ed.user_id=?
    ');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $r = $stmt->get_result();

    while ($d = $r->fetch_assoc()) {
        if (preg_match('/^(PAYE|NSSF|SHIF|SHA|AHL|HOUSING LEVY|AFFORDABLE HOUSING LEVY|PENSION|EMPLOYEE PENSION)$/i', trim($d['deduction_name']))) {
            continue;
        }

        $rate = (float)($d['rate'] ?? 0);
        $amount = (float)($d['amount'] ?? 0);

        if ($rate > 0) {
            $custom[] = ['name' => $d['deduction_name'], 'type' => 'percent', 'value' => $rate];
        } elseif ($amount > 0) {
            $custom[] = ['name' => $d['deduction_name'], 'type' => 'fixed', 'value' => $amount];
        }
    }
    $stmt->close();
}

$locked = $isExisting && (
    ($existing['approval_status'] ?? '') === 'Approved' ||
    ($existing['payment_status'] ?? '') === 'Paid'
);

$legacy = $isExisting &&
    (($saved['calculation']['rule_set'] ?? '') !== 'KE_STANDARD_2026_02_PENSION');

$calculation = null;
$calcError = '';

if (!$isExisting || !$legacy) {
    try {
        $calculation = kenyaStandardPayroll(
            (float)($isExisting ? $existing['base_salary'] : $salary['base_salary']),
            $allowances,
            $custom,
            $payrollMonth,
            $payrollYear,
            $pensionConfig
        );
    } catch (Throwable $e) {
        $calcError = $e->getMessage();
    }
}

$displayBasic = (float)($isExisting ? $existing['base_salary'] : $salary['base_salary']);
$displayAllow = (float)($isExisting ? $existing['total_allowances'] : ($calculation['total_allowances'] ?? 0));
$displayDed = (float)($isExisting ? $existing['total_deductions'] : ($calculation['total_deductions'] ?? 0));
$displayNet = (float)($isExisting ? $existing['net_pay'] : ($calculation['net_pay'] ?? 0));
$displayGross = $displayBasic + $displayAllow;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Process Payroll</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
<?php include 'navbar.php'; ?>

<main class="ml-64 p-6 max-w-7xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold text-blue-800">
        Process Payroll — <?=h($user['full_name'])?>
    </h1>

    <?php if ($locked): ?>
        <div class="bg-yellow-100 text-yellow-900 p-3 rounded">
            Approved or paid payroll: financial amounts are locked. Workflow updates are permitted subject to server-side checks.
        </div>
    <?php endif; ?>

    <?php if ($legacy): ?>
        <div class="bg-orange-100 text-orange-900 p-3 rounded">
            Legacy payroll: saved financial amounts are preserved. Do not recalculate this record automatically; use a controlled migration for historical records.
        </div>
    <?php endif; ?>

    <?php if ($calcError): ?>
        <div class="bg-red-100 text-red-800 p-3 rounded"><?=h($calcError)?></div>
    <?php endif; ?>

    <section class="bg-white rounded shadow p-5 grid md:grid-cols-2 gap-3">
        <div>
            <h2 class="font-semibold mb-2">Employee Information</h2>
            <p>Email: <?=h($user['email'])?></p>
            <p>National ID: <?=h($user['national_id'])?></p>
            <p>Bank: <?=h($salary['bank_name'] ?? '-')?></p>
            <p>Account: <?=h($salary['bank_account'] ?? '-')?></p>
        </div>
        <div>
            <h2 class="font-semibold mb-2">Salary Information</h2>
            <p>Basic salary: Ksh <?=number_format($displayBasic, 2)?></p>
            <label class="block mt-2">Payroll No.
                <input id="payrollNo" class="border p-2 rounded w-full" maxlength="100" value="<?=h($workflow['payroll_no'] ?? '')?>">
            </label>
            <p class="text-sm text-gray-500 mt-2">Payroll month: <?=h($payrollMonth . ' ' . $payrollYear)?></p>
        </div>
    </section>

    <section class="bg-white rounded shadow p-5">
        <h2 class="font-semibold text-lg mb-1">Allowances</h2>
        <p class="text-sm text-gray-600 mb-3">You can edit the name and amount of any saved allowance below. Changes recalculate the payroll automatically.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="allowanceTable">
                <thead class="bg-green-100">
                    <tr>
                        <th class="p-2 text-left">Allowance</th>
                        <th class="p-2 text-right">Amount (Ksh)</th>
                        <th class="p-2">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
            <input id="allowanceName" placeholder="Allowance name" class="border p-2 rounded">
            <input id="allowanceAmount" type="number" min="0.01" step="0.01" placeholder="Amount" class="border p-2 rounded">
            <button id="addAllowance" type="button" class="bg-green-600 text-white px-4 py-2 rounded">Add allowance</button>
        </div>
    </section>

    <section class="bg-white rounded shadow p-5">
        <h2 class="font-semibold text-lg mb-3">Optional Employee Pension</h2>
        <p class="text-sm text-gray-600 mb-3">
            Employee contribution: 6% of gross pay. Employer contribution: 0%. NSSF continues separately. Enrollment is saved per employee; payroll records retain a monthly snapshot.
        </p>
        <label class="flex items-center gap-2 mb-2">
            <input id="pensionEnabled" type="checkbox" <?=$pensionConfig['enabled'] ? 'checked' : ''?>>
            Enroll employee in pension
        </label>
        <label class="flex items-center gap-2">
            <input id="pensionQualified" type="checkbox" <?=$pensionConfig['tax_qualified'] ? 'checked' : ''?>>
            Confirm scheme qualifies for Kenyan PAYE pension relief
        </label>
        <p class="text-xs text-orange-700 mt-2">
            Only tick tax relief after confirming the pension scheme qualifies. NSSF and qualifying pension share the applicable monthly tax-deduction cap.
        </p>
    </section>

    <section class="bg-white rounded shadow p-5">
        <h2 class="font-semibold text-lg mb-2">Deductions</h2>
        <p class="text-sm text-gray-600 mb-3">
            Statutory amounts are calculated automatically. You can edit the name, basis and value of custom deductions. Percentage deductions are calculated against gross pay.
        </p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="deductionTable">
                <thead class="bg-red-100">
                    <tr>
                        <th class="p-2 text-left">Deduction</th>
                        <th class="p-2 text-left">Basis</th>
                        <th class="p-2 text-right">Amount (Ksh)</th>
                        <th class="p-2">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
            <input id="deductionName" placeholder="Other deduction name" class="border p-2 rounded">
            <input id="deductionAmount" type="number" min="0.01" step="0.01" placeholder="Fixed amount" class="border p-2 rounded">
            <button id="addDeduction" type="button" class="bg-red-600 text-white px-4 py-2 rounded">Add deduction</button>
        </div>
    </section>

    <section class="bg-white rounded shadow p-5">
        <h2 class="font-semibold text-lg mb-3">Payroll Period and Workflow</h2>
        <div class="grid md:grid-cols-2 gap-3">
            <label>Month
                <select id="payrollMonth" class="border p-2 rounded w-full">
                    <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
                        <option value="<?=h($m)?>" <?=$m === $payrollMonth ? 'selected' : ''?>><?=h($m)?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Year
                <input id="payrollYear" class="border p-2 rounded w-full" readonly value="<?=$payrollYear?>">
            </label>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mt-4">
            <?php foreach ([
                ['preparedBy','Prepared By','prepared_by','text'],
                ['datePrepared','Date Prepared','date_prepared','date'],
                ['approvedBy','Approved By','approved_by','text'],
                ['approvalDate','Approval Date','approval_date','date'],
                ['accountantName','Accountant Name','accountant_name','text'],
                ['paymentDate','Payment Date','payment_date','date']
            ] as $field): ?>
                <label class="text-sm"><?=h($field[1])?>
                    <input id="<?=h($field[0])?>" type="<?=h($field[3])?>" class="border p-2 rounded w-full" value="<?=h($workflow[$field[2]] ?? '')?>">
                </label>
            <?php endforeach; ?>

            <?php foreach ([
                ['preparationStatus','Preparation Status','preparation_status',['Pending','Prepared']],
                ['approvalStatus','Approval Status','approval_status',['Pending','Approved']],
                ['paymentStatus','Payment Status','payment_status',['Pending Pay','Paid']]
            ] as $field): ?>
                <label class="text-sm"><?=h($field[1])?>
                    <select id="<?=h($field[0])?>" class="border p-2 rounded w-full">
                        <?php foreach ($field[3] as $option): ?>
                            <option value="<?=h($option)?>" <?=($workflow[$field[2]] ?? '') === $option ? 'selected' : ''?>><?=h($option)?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bg-white rounded shadow p-5">
        <h2 class="font-semibold text-lg mb-3">Salary Summary</h2>
        <div class="grid sm:grid-cols-2 gap-2">
            <p>Basic salary: <strong id="sumBasic">Ksh <?=number_format($displayBasic, 2)?></strong></p>
            <p>Allowances: <strong id="sumAllow">Ksh <?=number_format($displayAllow, 2)?></strong></p>
            <p>Gross pay: <strong id="grossPay">Ksh <?=number_format($displayGross, 2)?></strong></p>
            <p>Taxable pay: <strong id="taxablePay">—</strong></p>
            <p>Total deductions: <strong id="totalDeductions">Ksh <?=number_format($displayDed, 2)?></strong></p>
            <p class="font-bold text-blue-700">Net pay: <strong id="netPay">Ksh <?=number_format($displayNet, 2)?></strong></p>
        </div>
        <p id="message" role="status" class="mt-3 text-sm"></p>
        <div class="flex flex-wrap gap-3 mt-4">
            <button id="savePayrollBtn" type="button" class="bg-blue-600 text-white px-5 py-2 rounded">Save Payroll</button>
            <a href="payroll_list.php" class="bg-gray-600 text-white px-5 py-2 rounded">Back to Payroll List</a>
        </div>
    </section>
</main>

<script>
const userId = <?=json_encode($user_id)?>;
const baseSalary = <?=json_encode($displayBasic)?>;

const existing = <?=json_encode($isExisting)?>;
const legacy = <?=json_encode($legacy)?>;
const locked = <?=json_encode($locked)?>;

let allowances = <?=json_encode($allowances, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)?>;
let custom = <?=json_encode($custom, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)?>;
let calculated = null;

const savedSummary = <?=json_encode([
    'gross_pay' => $displayGross,
    'total_allowances' => $displayAllow,
    'total_deductions' => $displayDed,
    'net_pay' => $displayNet
])?>;

const savedDeductions = <?=json_encode($saved['deductions'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)?>;

const $ = id => document.getElementById(id);
const money = n => 'Ksh ' + Number(n || 0).toLocaleString('en-KE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
});

function cell(row, value, cls = 'p-2 border-b') {
    const td = document.createElement('td');
    td.className = cls;
    td.textContent = value;
    row.appendChild(td);
    return td;
}

function makeInput(value, type = 'text', placeholder = '') {
    const input = document.createElement('input');
    input.type = type;
    input.value = value ?? '';
    input.placeholder = placeholder;
    input.className = 'border rounded px-2 py-1 w-full min-w-24';
    if (type === 'number') {
        input.min = '0.01';
        input.step = '0.01';
    }
    return input;
}

function makeRemoveButton(onClick) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Remove';
    button.className = 'text-red-700 hover:underline';
    button.onclick = onClick;
    return button;
}

function render() {
    const allowanceBody = $('allowanceTable').tBodies[0];
    allowanceBody.replaceChildren();

    allowances.forEach((allowance, index) => {
        const tr = document.createElement('tr');

        if (!locked && !legacy) {
            const nameCell = cell(tr, '');
            const nameInput = makeInput(allowance.name, 'text', 'Allowance name');
            nameInput.addEventListener('change', () => {
                const name = nameInput.value.trim();
                if (!name) {
                    alert('Allowance name cannot be empty.');
                    nameInput.value = allowances[index].name;
                    return;
                }
                allowances[index].name = name;
                refresh();
            });
            nameCell.appendChild(nameInput);

            const amountCell = cell(tr, '', 'p-2 border-b text-right');
            const amountInput = makeInput(allowance.amount, 'number');
            amountInput.addEventListener('change', () => {
                const amount = Number(amountInput.value);
                if (!Number.isFinite(amount) || amount <= 0) {
                    alert('Enter a valid allowance amount.');
                    amountInput.value = allowances[index].amount;
                    return;
                }
                allowances[index].amount = amount;
                refresh();
            });
            amountCell.appendChild(amountInput);

            cell(tr, '').appendChild(makeRemoveButton(() => {
                allowances.splice(index, 1);
                refresh();
            }));
        } else {
            cell(tr, allowance.name);
            cell(tr, money(allowance.amount), 'p-2 border-b text-right');
            cell(tr, '');
        }

        allowanceBody.appendChild(tr);
    });

    const deductionBody = $('deductionTable').tBodies[0];
    deductionBody.replaceChildren();

    const lines = (legacy || locked)
        ? savedDeductions
        : (calculated?.details?.deductions || []);

    lines.forEach(deduction => {
        const tr = document.createElement('tr');
        const isCustom = deduction.type === 'custom';
        const customIndex = isCustom
            ? custom.findIndex(item => item.name === deduction.name)
            : -1;

        if (isCustom && customIndex >= 0 && !locked && !legacy) {
            const nameCell = cell(tr, '');
            const nameInput = makeInput(custom[customIndex].name, 'text', 'Deduction name');
            nameInput.addEventListener('change', () => {
                const name = nameInput.value.trim();
                if (!name) {
                    alert('Deduction name cannot be empty.');
                    nameInput.value = custom[customIndex].name;
                    return;
                }
                if (/^(PAYE|NSSF|SHIF|SHA|AHL|HOUSING LEVY|AFFORDABLE HOUSING LEVY|PENSION|EMPLOYEE PENSION)$/i.test(name)) {
                    alert('Statutory deductions are calculated automatically. Choose another name.');
                    nameInput.value = custom[customIndex].name;
                    return;
                }
                custom[customIndex].name = name;
                refresh();
            });
            nameCell.appendChild(nameInput);

            const basisCell = cell(tr, '');
            const basisSelect = document.createElement('select');
            basisSelect.className = 'border rounded px-2 py-1 w-full';
            [
                ['fixed', 'Fixed amount'],
                ['percent', '% of gross']
            ].forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                basisSelect.appendChild(option);
            });
            basisSelect.value = custom[customIndex].type === 'percent' ? 'percent' : 'fixed';
            basisSelect.addEventListener('change', () => {
                custom[customIndex].type = basisSelect.value;
                refresh();
            });
            basisCell.appendChild(basisSelect);

            const valueCell = cell(tr, '', 'p-2 border-b text-right');
            const valueInput = makeInput(custom[customIndex].value, 'number');
            valueInput.addEventListener('change', () => {
                const value = Number(valueInput.value);
                if (!Number.isFinite(value) || value <= 0) {
                    alert('Enter a valid deduction value.');
                    valueInput.value = custom[customIndex].value;
                    return;
                }
                custom[customIndex].value = value;
                refresh();
            });
            valueCell.appendChild(valueInput);

            cell(tr, '').appendChild(makeRemoveButton(() => {
                custom.splice(customIndex, 1);
                refresh();
            }));
        } else {
            cell(tr, deduction.name);
            const basis = deduction.type === 'statutory'
                ? 'Statutory'
                : deduction.type === 'pension'
                    ? 'Pension'
                    : (deduction.basis === 'percent' ? `${deduction.value}% gross` : 'Fixed');
            cell(tr, basis);
            cell(
                tr,
                money(typeof deduction.amount === 'number' ? deduction.amount : parseFloat(deduction.amount) || 0),
                'p-2 border-b text-right'
            );
            cell(tr, '');
        }

        deductionBody.appendChild(tr);
    });

    const summary = (legacy || locked) ? savedSummary : calculated;
    if (summary) {
        $('sumBasic').textContent = money(baseSalary);
        $('sumAllow').textContent = money(summary.total_allowances);
        $('grossPay').textContent = money(summary.gross_pay);
        $('totalDeductions').textContent = money(summary.total_deductions);
        $('netPay').textContent = money(summary.net_pay);
        $('taxablePay').textContent = summary.taxable_pay === undefined
            ? 'Saved record'
            : money(summary.taxable_pay);
    }
}

let requestNumber = 0;

async function refresh() {
    if (legacy || locked) {
        render();
        return;
    }

    const sequence = ++requestNumber;
    const fd = new FormData();
    fd.set('user_id', userId);
    fd.set('month', $('payrollMonth').value);
    fd.set('year', $('payrollYear').value);
    fd.set('allowances', JSON.stringify(allowances));
    fd.set('other_deductions', JSON.stringify(custom));
    fd.set('pension_enabled', $('pensionEnabled').checked ? '1' : '0');
    fd.set('pension_tax_qualified', $('pensionQualified').checked ? '1' : '0');

    $('message').textContent = 'Calculating...';
    calculated = null;

    try {
        const response = await fetch('calculate_payroll.php', {
            method: 'POST',
            body: fd
        });
        const data = await response.json();

        if (sequence !== requestNumber) return;
        if (!response.ok) throw new Error(data.error || 'Calculation failed');

        calculated = data;
        $('message').textContent = '';
        render();
    } catch (error) {
        if (sequence !== requestNumber) return;
        $('message').textContent = error.message;
        render();
    }
}

$('addAllowance').onclick = () => {
    const name = $('allowanceName').value.trim();
    const amount = Number($('allowanceAmount').value);

    if (!name || !Number.isFinite(amount) || amount <= 0) {
        return alert('Enter a valid allowance.');
    }

    allowances.push({name, amount});
    $('allowanceName').value = '';
    $('allowanceAmount').value = '';
    refresh();
};

$('addDeduction').onclick = () => {
    const name = $('deductionName').value.trim();
    const amount = Number($('deductionAmount').value);

    if (!name || !Number.isFinite(amount) || amount <= 0) {
        return alert('Enter a valid deduction.');
    }

    if (/^(PAYE|NSSF|SHIF|SHA|AHL|HOUSING LEVY|AFFORDABLE HOUSING LEVY|PENSION|EMPLOYEE PENSION)$/i.test(name)) {
        return alert('Statutory deductions are automatic.');
    }

    custom.push({name, type: 'fixed', value: amount});
    $('deductionName').value = '';
    $('deductionAmount').value = '';
    refresh();
};

$('pensionEnabled').onchange = refresh;
$('pensionQualified').onchange = refresh;

if (locked || legacy) {
    for (const id of [
        'allowanceName', 'allowanceAmount', 'addAllowance',
        'deductionName', 'deductionAmount', 'addDeduction',
        'pensionEnabled', 'pensionQualified'
    ]) {
        $(id).disabled = true;
    }
}

$('payrollMonth').onchange = () => {
    const month = $('payrollMonth').selectedIndex + 1;
    location.href = 'process_payroll.php?id=' + userId +
        '&month=' + $('payrollYear').value + '-' + String(month).padStart(2, '0');
};

$('savePayrollBtn').onclick = async () => {
    if (!existing && !calculated) {
        return alert('Payroll must calculate successfully before saving.');
    }

    if (!legacy && !locked && !calculated) {
        return alert('Wait for payroll calculation.');
    }

    const val = id => $(id).value.trim();

    if (val('preparationStatus') === 'Prepared' && (!val('preparedBy') || !val('datePrepared'))) {
        return alert('Enter preparer and date.');
    }

    if (val('approvalStatus') === 'Approved' && (!val('approvedBy') || !val('approvalDate'))) {
        return alert('Enter approver and date.');
    }

    if (val('paymentStatus') === 'Paid' && (!val('accountantName') || !val('paymentDate'))) {
        return alert('Enter accountant and payment date.');
    }

    const fd = new FormData();
    const data = {
        user_id: userId,
        payroll_no: val('payrollNo'),
        month: val('payrollMonth'),
        year: val('payrollYear'),
        prepared_by: val('preparedBy'),
        date_prepared: val('datePrepared'),
        preparation_status: val('preparationStatus'),
        approved_by: val('approvedBy'),
        approval_date: val('approvalDate'),
        approval_status: val('approvalStatus'),
        accountant_name: val('accountantName'),
        payment_date: val('paymentDate'),
        payment_status: val('paymentStatus')
    };

    Object.entries(data).forEach(([key, value]) => fd.set(key, value));

    if (!legacy && !locked) {
        fd.set('allowances', JSON.stringify(allowances));
        fd.set('other_deductions', JSON.stringify(custom));
        fd.set('pension_enabled', $('pensionEnabled').checked ? '1' : '0');
        fd.set('pension_tax_qualified', $('pensionQualified').checked ? '1' : '0');
    }

    const button = $('savePayrollBtn');
    button.disabled = true;
    button.textContent = 'Saving...';

    try {
        const response = await fetch('save_payroll.php', {
            method: 'POST',
            body: fd
        });
        const message = await response.text();

        if (!response.ok) throw new Error(message);

        alert(message);
        location.reload();
    } catch (error) {
        alert(error.message);
    } finally {
        button.disabled = false;
        button.textContent = 'Save Payroll';
    }
};

refresh();
</script>
</body>
</html>
