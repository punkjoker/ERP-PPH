-- Run once in the lynntech_manager database before using sacco_loans.php.
-- Loan tracking is independent of payroll and does not modify payroll tables.
CREATE TABLE IF NOT EXISTS hr_sacco_loans (
    loan_id INT NOT NULL AUTO_INCREMENT,
    sacco_member_id INT NOT NULL,
    loan_reference VARCHAR(100) NULL,
    principal_amount DECIMAL(12,2) NOT NULL,
    interest_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    notes TEXT NULL,
    status ENUM('Active','Closed') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (loan_id),
    KEY idx_sacco_loan_member (sacco_member_id),
    KEY idx_sacco_loan_issue_date (issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_sacco_loan_repayments (
    repayment_id INT NOT NULL AUTO_INCREMENT,
    loan_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    repayment_date DATE NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'Other',
    reference_number VARCHAR(150) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (repayment_id),
    KEY idx_sacco_repayment_loan (loan_id),
    KEY idx_sacco_repayment_date (repayment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
