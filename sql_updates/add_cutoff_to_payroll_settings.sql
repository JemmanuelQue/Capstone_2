-- Update payroll_monthly_settings table to support cutoff periods
ALTER TABLE `payroll_monthly_settings` 
ADD COLUMN `cutoff_period` ENUM('1-15', '16-31', 'full') DEFAULT 'full' AFTER `month`,
DROP INDEX `unique_user_month`,
ADD UNIQUE KEY `unique_user_month_cutoff` (`user_id`, `year`, `month`, `cutoff_period`);

-- Update the index to include cutoff period
ALTER TABLE `payroll_monthly_settings` 
DROP INDEX `idx_user_date`,
ADD KEY `idx_user_date_cutoff` (`user_id`, `year`, `month`, `cutoff_period`);
