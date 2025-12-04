-- Alter table to support Monthly Salary Credit breakdown per official SSS layout
-- Adds MSC Regular SS, EC, MPF, and TOTAL subcolumns

ALTER TABLE sss_contribution_table
    ADD COLUMN msc_regular_ss DECIMAL(10,2) NULL AFTER monthly_salary_credit,
    ADD COLUMN msc_ec DECIMAL(10,2) NULL AFTER msc_regular_ss,
    ADD COLUMN msc_mpf DECIMAL(10,2) NULL AFTER msc_ec,
    ADD COLUMN msc_total DECIMAL(10,2) NULL AFTER msc_mpf;

-- Optional: keep msc_total consistent if not provided
-- You can run an update to derive totals for existing rows where parts are present
-- UPDATE sss_contribution_table
-- SET msc_total = COALESCE(msc_regular_ss,0) + COALESCE(msc_ec,0) + COALESCE(msc_mpf,0)
-- WHERE msc_total IS NULL;
