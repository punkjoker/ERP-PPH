
<?php
// kenya_statutory_rules.php
// Rules applicable to October 2026 payroll.
// Review and version these rules when legislation changes.

function kenyaStatutoryRules(): array
{
    return [
        'PAYE' => [
            'name' => 'Pay As You Earn (PAYE)',
            'type' => 'Progressive',
            'description' => 'Calculated using taxable income and progressive tax bands.',
            'personal_relief' => 2400.00,
            'bands' => [
                ['amount' => 24000, 'rate' => 10],
                ['amount' => 8333, 'rate' => 25],
                ['amount' => 467667, 'rate' => 30],
                ['amount' => 300000, 'rate' => 32.5],
                ['amount' => null, 'rate' => 35]
            ]
        ],

        'NSSF' => [
            'name' => 'NSSF',
            'type' => 'Capped contribution',
            'description' => 'Employee contribution subject to applicable NSSF earnings limits.',
            'rate' => 6.00,
            'lower_earnings_limit' => 9000,
            'upper_earnings_limit' => 108000
        ],

        'SHIF' => [
            'name' => 'Social Health Insurance Fund',
            'type' => 'Percentage',
            'description' => '2.75% of gross monthly salary, subject to minimum contribution.',
            'rate' => 2.75,
            'minimum' => 300.00
        ],

        'AHL' => [
            'name' => 'Affordable Housing Levy',
            'type' => 'Percentage',
            'description' => 'Employee contribution of 1.5% of applicable gross monthly salary.',
            'rate' => 1.50
        ]
    ];
}

// Calculate PAYE tax before personal relief.
// Input must already be the correctly calculated taxable income.
function kenyaPAYEBeforeRelief(float $taxableIncome): float
{
    $rules = kenyaStatutoryRules()['PAYE'];
    $remaining = max(0, $taxableIncome);
    $tax = 0;

    foreach ($rules['bands'] as $band) {
        if ($remaining <= 0) {
            break;
        }

        $bandAmount = $band['amount'] === null
            ? $remaining
            : min($remaining, $band['amount']);

        $tax += $bandAmount * ($band['rate'] / 100);
        $remaining -= $bandAmount;
    }

    return round($tax, 2);
}
