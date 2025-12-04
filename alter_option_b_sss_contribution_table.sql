-- ALTER script to migrate existing sss_contribution_table to Option B schema
-- Rename employer/employee columns to match official SSS layout
ALTER TABLE sss_contribution_table RENAME COLUMN regular_ss_employer TO employer_regular_ss;
ALTER TABLE sss_contribution_table RENAME COLUMN mpf_employer TO employer_mpf;
ALTER TABLE sss_contribution_table RENAME COLUMN ec_contribution TO employer_ec;
ALTER TABLE sss_contribution_table RENAME COLUMN total_employer TO employer_total;
ALTER TABLE sss_contribution_table RENAME COLUMN regular_ss_employee TO employee_regular_ss;
ALTER TABLE sss_contribution_table RENAME COLUMN mpf_employee TO employee_mpf;
ALTER TABLE sss_contribution_table RENAME COLUMN total_employee TO employee_total;

-- Ensure MSC columns exist (skip if already added)
ALTER TABLE sss_contribution_table ADD COLUMN IF NOT EXISTS msc_regular_ss DECIMAL(10,2) NULL;
ALTER TABLE sss_contribution_table ADD COLUMN IF NOT EXISTS msc_ec DECIMAL(10,2) NULL;
ALTER TABLE sss_contribution_table ADD COLUMN IF NOT EXISTS msc_mpf DECIMAL(10,2) NULL;
ALTER TABLE sss_contribution_table ADD COLUMN IF NOT EXISTS msc_total DECIMAL(10,2) NULL;

-- Optional: Drop legacy monthly_salary_credit if no longer needed
ALTER TABLE sss_contribution_table DROP COLUMN monthly_salary_credit;
