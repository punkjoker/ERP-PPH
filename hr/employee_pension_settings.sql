-- Run once after backing up the HR database.
CREATE TABLE IF NOT EXISTS employee_pension_settings (
  user_id INT NOT NULL PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  tax_qualified TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Existing payroll_records schema remains unchanged.
