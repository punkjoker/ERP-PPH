LYNNTECH HR SACCO MODULE — UPDATED CONTRIBUTION AND LOAN TRACKING

FILES
- sacco_common.php (shared helper required by SACCO pages)
- sacco_members.php (membership register; includes previous contribution opening balance)
- record_sacco_contribution.php (manual contribution and contribution ledger entries)
- sacco_transactions.php (contribution transaction history)
- sacco_member_statement.php (contribution statement)
- sacco_reports.php (contribution reports)
- sacco_loans.php (add multiple loans per member and record repayments)
- sacco_loan_statement.php (individual loan repayment statement)
- sacco_loan_schema.sql (creates the two new loan tables)

INSTALLATION
1. Back up your LYNNTECH_MNGMENT/hr folder and database.
2. Copy the PHP files into LYNNTECH_MNGMENT/hr/.
3. Keep your existing db_con.php and navbar.php.
4. In phpMyAdmin, select lynntech_manager and run sacco_loan_schema.sql.
5. Ensure the existing tables hr_saccos, hr_sacco_members and hr_sacco_transactions exist.
6. Open sacco_members.php to register members. Enter any previously accumulated contribution in the new Opening Contribution Balance field. This creates an opening_balance ledger entry dated on the membership date (or today if no membership date is supplied).
7. Open sacco_loans.php to add a loan. Add another loan as a separate record whenever the member receives another loan. Record repayments against the specific loan, and use the loan register/statement to review balances.

IMPORTANT BEHAVIOUR
- Contributions and loans are separate ledgers.
- The opening contribution is added once at membership registration; do not enter it again as a normal contribution.
- Multiple loans per member per SACCO are supported. Each repayment belongs to one selected loan.
- The system prevents a repayment larger than that loan's remaining balance.
- Loan outstanding = principal + entered interest - recorded repayments.
- All tracking is manual and independent from payroll. No payroll deduction, net pay, or payslip calculation is changed.
- If the module was already installed and members exist, use Record SACCO Transaction and choose Opening Balance to bring forward their existing contribution balances. Do not re-register those members just to add an opening balance.

DATABASE / LINKAGE
- hr_sacco_members.user_id stores users.user_id (as confirmed).
- Existing display queries expect users.employee_id and employees.employee_id, plus employees.first_name/last_name and users.email. If your users-to-employees link differs, those joins need to be aligned with your actual table structure.

SECURITY
- Forms use prepared statements and CSRF tokens. Apply the same HR authentication/session guard used by your protected HR pages. navbar.php is navigation only and does not secure the pages.
