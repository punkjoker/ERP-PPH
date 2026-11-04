Update: deductions.rate takes priority when > 0; otherwise deductions.amount is used as a fixed Ksh deduction.
Replace process_payroll.php from this package. Replace your employee salary edit page with update_salary.php (rename it to your actual existing filename).
Other pension payroll files are included unchanged for completeness.
Ensure deductions.amount exists (DECIMAL(12,2) NULL DEFAULT NULL). Update payroll_deductions.php to insert/edit/display amount as well as rate.
Existing approved/paid payroll records remain unchanged. Test on pending payroll first.
