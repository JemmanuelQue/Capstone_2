-- SSS Contribution Table DDL
-- Run this once to create the table; adjust effective_date as needed

CREATE TABLE IF NOT EXISTS sss_contribution_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    range_min DECIMAL(10,2) NOT NULL,
    range_max DECIMAL(10,2) NOT NULL,
    monthly_salary_credit DECIMAL(10,2) NOT NULL,

    regular_ss_employer DECIMAL(10,2) NOT NULL,
    regular_ss_employee DECIMAL(10,2) NOT NULL,

    mpf_employer DECIMAL(10,2) NOT NULL,
    mpf_employee DECIMAL(10,2) NOT NULL,

    ec_contribution DECIMAL(10,2) NOT NULL,

    total_employer DECIMAL(10,2) NOT NULL,
    total_employee DECIMAL(10,2) NOT NULL,
    total_contribution DECIMAL(10,2) NOT NULL,

    effective_date DATE NOT NULL DEFAULT ('2025-01-01'),

    CONSTRAINT chk_range CHECK (range_min <= range_max)
);

-- Helpful index for lookups by effective date and salary
CREATE INDEX IF NOT EXISTS idx_sss_effective_date ON sss_contribution_table (effective_date);
CREATE INDEX IF NOT EXISTS idx_sss_range ON sss_contribution_table (range_min, range_max);

-- Optional unique to prevent overlapping ranges for same effective_date
-- MySQL lacks partial unique, so we rely on app validation to avoid overlaps.
