-- ============================================================================
-- 360Management ERP — Comprehensive Finance & Accounting Schema
-- ============================================================================

CREATE TABLE IF NOT EXISTS `coa_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `account_code` VARCHAR(20) NOT NULL,
  `account_name` VARCHAR(100) NOT NULL,
  `account_type` ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'COGS', 'Expense') NOT NULL,
  `parent_code` VARCHAR(20) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_comp_code` (`company_id`, `account_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `branch_id` VARCHAR(50) NOT NULL DEFAULT 'main',
  `voucher_number` VARCHAR(50) NOT NULL UNIQUE,
  `entry_date` DATE NOT NULL,
  `event_type` VARCHAR(50) NOT NULL, -- pos_sale, grn_receipt, crate_deposit, payroll, loss_writeoff, manual
  `reference` VARCHAR(100) NOT NULL,
  `narration` TEXT,
  `total_debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `posted_by` VARCHAR(100) DEFAULT 'System',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `journal_lines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `entry_id` INT NOT NULL,
  `account_code` VARCHAR(20) NOT NULL,
  `account_name` VARCHAR(100) NOT NULL,
  `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `description` VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (`entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `general_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `account_code` VARCHAR(20) NOT NULL,
  `voucher_number` VARCHAR(50) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `running_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `narration` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `accounts_receivable` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `customer_id` INT NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `invoice_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` DECIMAL(15,2) NOT NULL,
  `status` ENUM('Unpaid', 'Partially Paid', 'Paid', 'Overdue') NOT NULL DEFAULT 'Unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `accounts_payable` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `supplier_id` INT NOT NULL,
  `supplier_name` VARCHAR(100) NOT NULL,
  `bill_number` VARCHAR(50) NOT NULL UNIQUE,
  `bill_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `total_amount` DECIMAL(15,2) NOT NULL,
  `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `balance_due` DECIMAL(15,2) NOT NULL,
  `status` ENUM('Unpaid', 'Partially Paid', 'Paid', 'Overdue') NOT NULL DEFAULT 'Unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `bank_name` VARCHAR(100) NOT NULL,
  `account_number` VARCHAR(30) NOT NULL,
  `currency` VARCHAR(5) DEFAULT 'NGN',
  `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `gl_account_code` VARCHAR(20) NOT NULL DEFAULT '1110'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bank_reconciliations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `bank_account_id` INT NOT NULL,
  `statement_date` DATE NOT NULL,
  `transaction_ref` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `type` ENUM('Credit', 'Debit') NOT NULL,
  `matched_pos_ref` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('Matched', 'Unmatched', 'Pending Review') DEFAULT 'Pending Review'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tax_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `tax_type` ENUM('VAT_Output', 'VAT_Input', 'WHT_Deducted', 'PAYE') NOT NULL,
  `tax_rate_percent` DECIMAL(5,2) NOT NULL,
  `taxable_amount` DECIMAL(15,2) NOT NULL,
  `tax_amount` DECIMAL(15,2) NOT NULL,
  `reference_voucher` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
