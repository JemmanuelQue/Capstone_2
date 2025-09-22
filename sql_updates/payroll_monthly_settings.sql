-- Create table for monthly payroll settings/contributions
CREATE TABLE IF NOT EXISTS `payroll_monthly_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `year` int(4) NOT NULL,
    `month` int(2) NOT NULL,
    `sss_contribution` decimal(10,2) DEFAULT 0.00,
    `philhealth_contribution` decimal(10,2) DEFAULT 0.00,
    `pagibig_contribution` decimal(10,2) DEFAULT 0.00,
    `tax_withholding` decimal(10,2) DEFAULT 0.00,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `updated_by` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_month` (`user_id`, `year`, `month`),
    KEY `idx_user_date` (`user_id`, `year`, `month`),
    KEY `fk_user_settings` (`user_id`),
    KEY `fk_updated_by` (`updated_by`),
    CONSTRAINT `fk_payroll_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
    CONSTRAINT `fk_payroll_settings_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`User_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
