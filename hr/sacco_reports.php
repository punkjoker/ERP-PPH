
<?php
require_once 'sacco_common.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// ==========================================
// REPORT FILTERS
// ==========================================

$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

$start = $month . '-01';

$end = (new DateTime($start))
    ->modify('first day of next month')
    ->format('Y-m-d');

$sid = (int)($_GET['sacco_id'] ?? 0);

// ==========================================
// SACCO LIST
// ==========================================

$saccos = $conn->query("
    SELECT sacco_id, sacco_name
    FROM hr_saccos
    ORDER BY sacco_name
")->fetch_all(MYSQLI_ASSOC);

// ==========================================
// SACCO CONTRIBUTION SUMMARY
// ==========================================

$sql = "
    SELECT
        s.sacco_id,
        s.sacco_name,

        COALESCE(
            SUM(
                CASE
                    WHEN t.transaction_type = 'contribution'
                    THEN t.amount ELSE 0
                END
            ), 0
        ) AS contributions,

        COALESCE(
            SUM(
                CASE
                    WHEN t.transaction_type = 'withdrawal'
                    THEN t.amount ELSE 0
                END
            ), 0
        ) AS withdrawals,

        COUNT(
            DISTINCT CASE
                WHEN t.transaction_type = 'contribution'
                THEN m.sacco_member_id
            END
        ) AS contributing_members

    FROM hr_saccos s

    LEFT JOIN hr_sacco_members m
        ON m.sacco_id = s.sacco_id

    LEFT JOIN hr_sacco_transactions t
        ON t.sacco_member_id = m.sacco_member_id
        AND t.transaction_date >= ?
        AND t.transaction_date < ?
";

if ($sid) {
    $sql .= " WHERE s.sacco_id = ?";
}

$sql .= "
    GROUP BY s.sacco_id, s.sacco_name
    ORDER BY s.sacco_name
";

$st = $conn->prepare($sql);

if ($sid) {
    $st->bind_param('ssi', $start, $end, $sid);
} else {
    $st->bind_param('ss', $start, $end);
}

$st->execute();
$summary = $st->get_result();

// ==========================================
// EMPLOYEE CONTRIBUTION DETAILS
// ==========================================

$sql2 = "
    SELECT
        m.sacco_member_id,
        m.sacco_account_no,
        s.sacco_name,

        COALESCE(
            NULLIF(
                TRIM(CONCAT(e.first_name, ' ', e.last_name)),
                ''
            ),
            u.email,
            CONCAT('User #', u.user_id)
        ) AS full_name,

        COALESCE(
            SUM(
                CASE
                    WHEN t.transaction_type = 'contribution'
                    THEN t.amount ELSE 0
                END
            ), 0
        ) AS contributions,

        COALESCE(
            SUM(
                CASE
                    WHEN t.transaction_type = 'withdrawal'
                    THEN t.amount ELSE 0
                END
            ), 0
        ) AS withdrawals

    FROM hr_sacco_members m

    JOIN hr_saccos s
        ON s.sacco_id = m.sacco_id

    JOIN users u
        ON u.user_id = m.user_id

    LEFT JOIN employees e
        ON e.employee_id = u.employee_id

    LEFT JOIN hr_sacco_transactions t
        ON t.sacco_member_id = m.sacco_member_id
        AND t.transaction_date >= ?
        AND t.transaction_date < ?
";

if ($sid) {
    $sql2 .= " WHERE m.sacco_id = ?";
}

$sql2 .= "
    GROUP BY
        m.sacco_member_id,
        m.sacco_account_no,
        s.sacco_name,
        full_name

    ORDER BY s.sacco_name, full_name
";

$st2 = $conn->prepare($sql2);

if ($sid) {
    $st2->bind_param('ssi', $start, $end, $sid);
} else {
    $st2->bind_param('ss', $start, $end);
}

$st2->execute();
$detail = $st2->get_result();

// ==========================================
// PROCESS CONTRIBUTION SUMMARY
// ==========================================

$rows = [];
$tc = 0;
$tw = 0;

while ($r = $summary->fetch_assoc()) {

    $r['contributions'] = (float)$r['contributions'];
    $r['withdrawals'] = (float)$r['withdrawals'];

    $tc += $r['contributions'];
    $tw += $r['withdrawals'];

    $rows[] = $r;
}

// ==========================================
// SACCO LOAN SUMMARY
// ==========================================

$loanSummarySql = "
    SELECT
        s.sacco_id,

        COALESCE(l.month_issued, 0) AS loan_issued,
        COALESCE(r.month_repaid, 0) AS loan_repayments,

        GREATEST(
            0,
            COALESCE(l.total_issued, 0) -
            COALESCE(r.total_repaid, 0)
        ) AS outstanding_loans

    FROM hr_saccos s

    LEFT JOIN (
        SELECT
            m.sacco_id,

            SUM(
                CASE
                    WHEN l.issue_date >= ?
                     AND l.issue_date < ?
                    THEN l.principal_amount + l.interest_amount
                    ELSE 0
                END
            ) AS month_issued,

            SUM(
                CASE
                    WHEN l.issue_date < ?
                    THEN l.principal_amount + l.interest_amount
                    ELSE 0
                END
            ) AS total_issued

        FROM hr_sacco_loans l

        JOIN hr_sacco_members m
            ON m.sacco_member_id = l.sacco_member_id

        GROUP BY m.sacco_id

    ) l ON l.sacco_id = s.sacco_id

    LEFT JOIN (
        SELECT
            m.sacco_id,

            SUM(
                CASE
                    WHEN lr.repayment_date >= ?
                     AND lr.repayment_date < ?
                    THEN lr.amount
                    ELSE 0
                END
            ) AS month_repaid,

            SUM(
                CASE
                    WHEN lr.repayment_date < ?
                    THEN lr.amount
                    ELSE 0
                END
            ) AS total_repaid

        FROM hr_sacco_loan_repayments lr

        JOIN hr_sacco_loans loan
            ON loan.loan_id = lr.loan_id

        JOIN hr_sacco_members m
            ON m.sacco_member_id = loan.sacco_member_id

        GROUP BY m.sacco_id

    ) r ON r.sacco_id = s.sacco_id
";

if ($sid) {
    $loanSummarySql .= " WHERE s.sacco_id = ?";
}

$loanSummaryStmt = $conn->prepare($loanSummarySql);

if ($sid) {
    $loanSummaryStmt->bind_param(
        "ssssssi",
        $start,
        $end,
        $end,
        $start,
        $end,
        $end,
        $sid
    );
} else {
    $loanSummaryStmt->bind_param(
        "ssssss",
        $start,
        $end,
        $end,
        $start,
        $end,
        $end
    );
}

$loanSummaryStmt->execute();

$loanSummaryResult = $loanSummaryStmt->get_result();

$loanSummary = [];

$loanIssuedTotal = 0;
$loanRepaymentsTotal = 0;
$outstandingLoansTotal = 0;

while ($loan = $loanSummaryResult->fetch_assoc()) {

    $loan['loan_issued'] = (float)$loan['loan_issued'];
    $loan['loan_repayments'] = (float)$loan['loan_repayments'];
    $loan['outstanding_loans'] = (float)$loan['outstanding_loans'];

    $loanSummary[(int)$loan['sacco_id']] = $loan;

    $loanIssuedTotal += $loan['loan_issued'];
    $loanRepaymentsTotal += $loan['loan_repayments'];
    $outstandingLoansTotal += $loan['outstanding_loans'];
}

$loanSummaryStmt->close();

// ==========================================
// EMPLOYEE LOAN DETAILS
// ==========================================

$loanDetailSql = "
    SELECT
        m.sacco_member_id,

        COALESCE(l.month_issued, 0) AS loan_issued,
        COALESCE(r.month_repaid, 0) AS loan_repayments,

        GREATEST(
            0,
            COALESCE(l.total_issued, 0) -
            COALESCE(r.total_repaid, 0)
        ) AS outstanding_loans

    FROM hr_sacco_members m

    LEFT JOIN (
        SELECT
            loan.sacco_member_id,

            SUM(
                CASE
                    WHEN loan.issue_date >= ?
                     AND loan.issue_date < ?
                    THEN loan.principal_amount + loan.interest_amount
                    ELSE 0
                END
            ) AS month_issued,

            SUM(
                CASE
                    WHEN loan.issue_date < ?
                    THEN loan.principal_amount + loan.interest_amount
                    ELSE 0
                END
            ) AS total_issued

        FROM hr_sacco_loans loan

        GROUP BY loan.sacco_member_id

    ) l ON l.sacco_member_id = m.sacco_member_id

    LEFT JOIN (
        SELECT
            loan.sacco_member_id,

            SUM(
                CASE
                    WHEN lr.repayment_date >= ?
                     AND lr.repayment_date < ?
                    THEN lr.amount
                    ELSE 0
                END
            ) AS month_repaid,

            SUM(
                CASE
                    WHEN lr.repayment_date < ?
                    THEN lr.amount
                    ELSE 0
                END
            ) AS total_repaid

        FROM hr_sacco_loan_repayments lr

        JOIN hr_sacco_loans loan
            ON loan.loan_id = lr.loan_id

        GROUP BY loan.sacco_member_id

    ) r ON r.sacco_member_id = m.sacco_member_id
";

if ($sid) {
    $loanDetailSql .= " WHERE m.sacco_id = ?";
}

$loanDetailStmt = $conn->prepare($loanDetailSql);

if ($sid) {
    $loanDetailStmt->bind_param(
        "ssssssi",
        $start,
        $end,
        $end,
        $start,
        $end,
        $end,
        $sid
    );
} else {
    $loanDetailStmt->bind_param(
        "ssssss",
        $start,
        $end,
        $end,
        $start,
        $end,
        $end
    );
}

$loanDetailStmt->execute();

$loanDetailResult = $loanDetailStmt->get_result();

$loanDetails = [];

while ($loan = $loanDetailResult->fetch_assoc()) {

    $loan['loan_issued'] = (float)$loan['loan_issued'];
    $loan['loan_repayments'] = (float)$loan['loan_repayments'];
    $loan['outstanding_loans'] = (float)$loan['outstanding_loans'];

    $loanDetails[(int)$loan['sacco_member_id']] = $loan;
}

$loanDetailStmt->close();

require_once 'navbar.php';
?>

<main class="ml-64 p-6 max-w-7xl mx-auto">

    <!-- PAGE HEADER -->
    <div class="flex justify-between flex-wrap gap-3 mb-6">

        <div>
            <h1 class="text-2xl font-bold">
                <i class="fa-solid fa-chart-line text-blue-600 mr-2"></i>
                SACCO Reports
            </h1>

            <p class="text-sm text-gray-500">
                Monthly contribution, withdrawal and loan summaries.
            </p>
        </div>

        <button
            onclick="window.print()"
            class="bg-gray-800 text-white px-4 py-2 rounded">
            <i class="fa-solid fa-print mr-1"></i>
            Print Report
        </button>

    </div>

    <!-- FILTERS -->
    <form method="get"
          class="bg-white border rounded-xl p-4 mb-5 flex flex-wrap items-end gap-3">

        <label class="text-sm">
            Month

            <input
                type="month"
                name="month"
                value="<?=se($month)?>"
                class="block border rounded p-2">
        </label>

        <label class="text-sm">
            SACCO

            <select name="sacco_id" class="block border rounded p-2">

                <option value="0">All SACCOs</option>

                <?php foreach ($saccos as $s): ?>

                    <option
                        value="<?=$s['sacco_id']?>"
                        <?=$sid === (int)$s['sacco_id'] ? 'selected' : ''?>>

                        <?=se($s['sacco_name'])?>

                    </option>

                <?php endforeach; ?>

            </select>
        </label>

        <button class="bg-blue-600 text-white rounded px-5 py-2">
            Generate
        </button>

    </form>

    <!-- CONTRIBUTION SUMMARY CARDS -->
    <h2 class="font-bold text-lg mb-3">Contribution Summary</h2>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-7">

        <div class="bg-white border rounded-xl p-4">
            <span class="text-sm text-gray-500">Contributions</span>

            <b class="block text-2xl text-green-700">
                KSh <?=sm($tc)?>
            </b>
        </div>

        <div class="bg-white border rounded-xl p-4">
            <span class="text-sm text-gray-500">Withdrawals</span>

            <b class="block text-2xl text-orange-600">
                KSh <?=sm($tw)?>
            </b>
        </div>

        <div class="bg-white border rounded-xl p-4">
            <span class="text-sm text-gray-500">Net Movement</span>

            <b class="block text-2xl text-blue-700">
                KSh <?=sm($tc - $tw)?>
            </b>
        </div>

    </div>

    <!-- LOAN SUMMARY CARDS -->
    <h2 class="font-bold text-lg mb-3">Loan Summary</h2>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-7">

        <div class="bg-white border rounded-xl p-4">
            <span class="text-sm text-gray-500">Loans Issued This Month</span>

            <b class="block text-2xl text-blue-700">
                KSh <?=sm($loanIssuedTotal)?>
            </b>
        </div>

        <div class="bg-white border rounded-xl p-4">
            <span class="text-sm text-gray-500">Loan Repayments This Month</span>

            <b class="block text-2xl text-green-700">
                KSh <?=sm($loanRepaymentsTotal)?>
            </b>
        </div>

        <div class="bg-white border rounded-xl p-4">
            <span class="text-sm text-gray-500">Outstanding Loans</span>

            <b class="block text-2xl text-red-700">
                KSh <?=sm($outstandingLoansTotal)?>
            </b>

            <span class="text-xs text-gray-500">
                Balance as at <?=se(date('d M Y', strtotime($end . ' -1 day')))?>
            </span>
        </div>

    </div>

    <!-- SACCO SUMMARY TABLE -->
    <section class="bg-white border rounded-xl overflow-x-auto mb-6">

        <h2 class="font-bold p-4 border-b">
            SACCO Summary — <?=se(date('F Y', strtotime($start)))?>
        </h2>

        <table class="w-full text-sm">

            <thead class="bg-gray-100">
                <tr>
                    <th class="p-3 text-left">SACCO</th>
                    <th class="p-3 text-center">Contributing Members</th>
                    <th class="p-3 text-right">Contributions</th>
                    <th class="p-3 text-right">Withdrawals</th>
                    <th class="p-3 text-right">Net</th>
                    <th class="p-3 text-right">Loans Issued</th>
                    <th class="p-3 text-right">Repayments</th>
                    <th class="p-3 text-right">Outstanding Loans</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($rows): ?>

                    <?php foreach ($rows as $r):

                        $loanRow = $loanSummary[(int)$r['sacco_id']] ?? [
                            'loan_issued' => 0,
                            'loan_repayments' => 0,
                            'outstanding_loans' => 0
                        ];

                    ?>

                        <tr class="border-t">

                            <td class="p-3 font-semibold">
                                <?=se($r['sacco_name'])?>
                            </td>

                            <td class="p-3 text-center">
                                <?=$r['contributing_members']?>
                            </td>

                            <td class="p-3 text-right">
                                <?=sm($r['contributions'])?>
                            </td>

                            <td class="p-3 text-right">
                                <?=sm($r['withdrawals'])?>
                            </td>

                            <td class="p-3 text-right font-semibold">
                                <?=sm($r['contributions'] - $r['withdrawals'])?>
                            </td>

                            <td class="p-3 text-right">
                                <?=sm($loanRow['loan_issued'])?>
                            </td>

                            <td class="p-3 text-right">
                                <?=sm($loanRow['loan_repayments'])?>
                            </td>

                            <td class="p-3 text-right font-semibold text-red-700">
                                <?=sm($loanRow['outstanding_loans'])?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="p-6 text-center text-gray-500">
                            No SACCO records found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </section>

    <!-- EMPLOYEE DETAILS -->
    <section class="bg-white border rounded-xl overflow-x-auto">

        <h2 class="font-bold p-4 border-b">
            Employee Contribution and Loan Details
        </h2>

        <table class="w-full text-sm">

            <thead class="bg-gray-100">
                <tr>
                    <th class="p-3 text-left">Employee</th>
                    <th class="p-3 text-left">SACCO</th>
                    <th class="p-3 text-left">Account</th>
                    <th class="p-3 text-right">Contributions</th>
                    <th class="p-3 text-right">Withdrawals</th>
                    <th class="p-3 text-right">Net</th>
                    <th class="p-3 text-right">Loans Issued</th>
                    <th class="p-3 text-right">Repayments</th>
                    <th class="p-3 text-right">Outstanding Loan</th>
                    <th class="p-3 text-center">Statement</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($detail->num_rows): ?>

                    <?php while ($r = $detail->fetch_assoc()):

                        $memberLoan = $loanDetails[(int)$r['sacco_member_id']] ?? [
                            'loan_issued' => 0,
                            'loan_repayments' => 0,
                            'outstanding_loans' => 0
                        ];

                    ?>

                        <tr class="border-t">

                            <td class="p-3">
                                <?=se($r['full_name'])?>
                            </td>

                            <td class="p-3">
                                <?=se($r['sacco_name'])?>
                            </td>

                            <td class="p-3">
                                <?=se($r['sacco_account_no'] ?: '—')?>
                            </td>

                            <td class="p-3 text-right">
                                <?=sm($r['contributions'])?>
                            </td>

                            <td class="p-3 text-right">
                                <?=sm($r['withdrawals'])?>
                            </td>

                            <td class="p-3 text-right font-semibold">
                                <?=sm($r['contributions'] - $r['withdrawals'])?>
                            </td>

                            <td class="p-3 text-right">
                                <?=sm($memberLoan['loan_issued'])?>
                            </td>

                            <td class="p-3 text-right">
                                <?=sm($memberLoan['loan_repayments'])?>
                            </td>

                            <td class="p-3 text-right font-semibold text-red-700">
                                <?=sm($memberLoan['outstanding_loans'])?>
                            </td>

                            <td class="p-3 text-center">
                                <a
                                    class="text-blue-600 underline"
                                    href="sacco_member_statement.php?id=<?=$r['sacco_member_id']?>">
                                    Open
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10" class="p-6 text-center text-gray-500">
                            No SACCO members found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </section>

</main>

<style>
@media print {

    .hr-sidebar,
    .hr-mobile-toggle,
    .hr-sidebar-overlay,
    form,
    button {
        display: none !important;
    }

    main {
        margin: 0 !important;
        padding: 10px !important;
        max-width: none !important;
    }

    body {
        background: white !important;
    }

    section {
        break-inside: avoid;
    }

    table {
        font-size: 9px !important;
    }

    th, td {
        padding: 5px !important;
    }
}
</style>
