<?php
/** Standard resident Kenyan payroll; rules explicitly scoped to Feb-Dec 2026. */
function kenyaPayrollPeriod(string $month, int $year): string {
    $months = array_flip(['January','February','March','April','May','June','July','August','September','October','November','December']);
    if (!isset($months[$month])) throw new InvalidArgumentException('Invalid payroll month.');
    return sprintf('%04d-%02d', $year, $months[$month] + 1);
}
function kenyaMoney($value): float {
    if (!is_numeric($value) || !is_finite((float)$value)) throw new InvalidArgumentException('Invalid payroll amount.');
    return round((float)$value, 2);
}
function kenyaStandardPayroll(float $basic, array $allowances, array $otherDeductions, string $month, int $year, array $pension = []): array {
    $period = kenyaPayrollPeriod($month, $year);
    if ($period < '2026-02' || $period > '2026-12') {
        throw new InvalidArgumentException('Automatic statutory rules are configured only for February–December 2026. Historical or later periods require their own verified rules.');
    }
    $basic = kenyaMoney($basic);
    if ($basic < 0) throw new InvalidArgumentException('Basic salary cannot be negative.');
    $allowanceTotal = 0.0;
    $cleanAllowances = [];
    foreach ($allowances as $a) {
        if (!is_array($a)) throw new InvalidArgumentException('Invalid allowance.');
        $name = trim((string)($a['name'] ?? ''));
        $amount = kenyaMoney($a['amount'] ?? null);
        if ($name === '' || $amount < 0) throw new InvalidArgumentException('Invalid allowance name or amount.');
        $cleanAllowances[] = ['name'=>$name, 'amount'=>$amount];
        $allowanceTotal += $amount;
    }
    $allowanceTotal = round($allowanceTotal, 2);
    $gross = round($basic + $allowanceTotal, 2);
    // Standard configuration: all cash allowances taxable and pensionable; no additional reliefs.
    $nssf = round(min($gross, 108000) * 0.06, 2);
    $shif = $gross > 0 ? max(300.0, round($gross * 0.0275, 2)) : 0.0;
    $ahl = round($gross * 0.015, 2);
    // Optional registered pension: employee 6% of gross; employer 0%.
    // Only confirmed qualifying schemes receive PAYE tax relief.
    $pensionEnabled = ($pension['enabled'] ?? false) === true;
    $pensionQualified = ($pension['tax_qualified'] ?? false) === true;
    $employeePension = $pensionEnabled ? round($gross * 0.06, 2) : 0.0;
    $employerPension = $pensionEnabled
    ? round($gross * 0.06, 2)
    : 0.0;
    // Shared monthly cap for qualifying employee NSSF + registered pension.
    $pensionTaxDeduction = min(30000.0, $nssf + ($pensionQualified ? $employeePension : 0.0));
    $taxable = max(0, round($gross - $pensionTaxDeduction - $shif - $ahl, 2));
    $bands = [[24000, .10], [8333, .25], [467667, .30], [300000, .325], [INF, .35]];
    $remaining = $taxable; $tax = 0.0;
    foreach ($bands as [$limit, $rate]) {
        $slice = min($remaining, $limit);
        $tax += $slice * $rate;
        $remaining -= $slice;
        if ($remaining <= 0) break;
    }
    $payeBeforeRelief = round($tax, 2);
    $personalRelief = 2400.00;
    $reliefApplied = min($payeBeforeRelief, $personalRelief);
    $paye = max(0, round($payeBeforeRelief - $reliefApplied, 2));
    $statutory = [
        ['name'=>'NSSF', 'amount'=>$nssf, 'type'=>'statutory'],
        ['name'=>'SHIF', 'amount'=>$shif, 'type'=>'statutory'],
        ['name'=>'Affordable Housing Levy', 'amount'=>$ahl, 'type'=>'statutory'],
        ['name'=>'PAYE', 'amount'=>$paye, 'type'=>'statutory'],
        ...($pensionEnabled ? [['name'=>'Employee Pension (6% gross)', 'amount'=>$employeePension, 'type'=>'pension', 'basis'=>'gross', 'rate'=>6]] : [])
    ];
    $custom = []; $customTotal = 0.0;
    foreach ($otherDeductions as $d) {
        if (!is_array($d)) throw new InvalidArgumentException('Invalid custom deduction.');
        $name = trim((string)($d['name'] ?? ''));
        $type = (string)($d['type'] ?? 'fixed');
        $value = kenyaMoney($d['value'] ?? null);
        if ($name === '' || $value < 0 || !in_array($type, ['fixed','percent'], true)) throw new InvalidArgumentException('Invalid custom deduction.');
        if (preg_match('/^(PAYE|NSSF|SHIF|SHA|AHL|HOUSING LEVY|AFFORDABLE HOUSING LEVY|PENSION|EMPLOYEE PENSION|EMPLOYEE PENSION \(6% GROSS\))$/i', $name)) {
            throw new InvalidArgumentException('Statutory deductions cannot be added manually: ' . $name);
        }
        if ($type === 'percent' && $value > 100) throw new InvalidArgumentException('Custom deduction rate cannot exceed 100%.');
        $amount = $type === 'percent' ? round($gross * $value / 100, 2) : $value;
        $custom[] = ['name'=>$name, 'amount'=>$amount, 'type'=>'custom', 'basis'=>$type, 'value'=>$value];
        $customTotal += $amount;
    }
    $statutoryTotal = round($nssf + $shif + $ahl + $paye + $employeePension, 2);
    $deductions = round($statutoryTotal + $customTotal, 2);
    if ($deductions > $gross) throw new InvalidArgumentException('Deductions exceed gross pay. Review the custom deductions.');
    return [
        'base_salary'=>$basic, 'total_allowances'=>$allowanceTotal, 'gross_pay'=>$gross,
        'taxable_pay'=>$taxable, 'net_taxable_pay'=>$taxable, 'tax_charged'=>$payeBeforeRelief,
        'paye_before_relief'=>$payeBeforeRelief, 'personal_relief'=>$personalRelief, 'tax_relief'=>$reliefApplied, 'paye_deducted'=>$paye,
        'statutory_deductions'=>$statutory, 'other_deductions'=>$custom,
        'total_deductions'=>$deductions, 'net_pay'=>round($gross-$deductions,2),
        'employer_nssf'=>$nssf, 'employer_housing_levy'=>$ahl,
        'employee_pension'=>$employeePension, 'employer_pension'=>$employerPension, 'pension_tax_deduction'=>$pensionTaxDeduction,
        'details'=>['allowances'=>$cleanAllowances,'deductions'=>array_merge($statutory,$custom),
            'audit'=>[
                'gross_pay'=>$gross,
                'taxable_pay'=>$gross,
                'qualifying_pre_tax_deductions'=>round($pensionTaxDeduction + $shif + $ahl, 2),
                'net_taxable_pay'=>$taxable,
                'tax_charged'=>$payeBeforeRelief,
                'personal_relief_entitlement'=>$personalRelief,
                'tax_relief'=>$reliefApplied,
                'paye_deducted'=>$paye,
                'net_pay'=>round($gross-$deductions,2)
            ],
'pension'=>[
    'enabled'=>$pensionEnabled,
    'employee_rate'=>6,
    'employer_rate'=>0,
    'basis'=>'gross',
    'tax_qualified'=>$pensionQualified,
    'employee_amount'=>$employeePension,
    'employer_amount'=>$employerPension,
    'tax_deduction_including_nssf'=>$pensionTaxDeduction
],
            'calculation'=>['rule_set'=>'KE_STANDARD_2026_02_PENSION', 'period'=>$period, 'taxable_pay'=>$taxable,
                'paye_before_relief'=>$payeBeforeRelief, 'personal_relief'=>$personalRelief,
                'net_taxable_pay'=>$taxable, 'tax_charged'=>$payeBeforeRelief, 'tax_relief'=>$reliefApplied, 'paye_deducted'=>$paye,
                'employer_nssf'=>$nssf,'employer_housing_levy'=>$ahl]]
    ];
}
