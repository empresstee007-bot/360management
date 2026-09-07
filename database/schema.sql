-- =================================================================
-- 360Management ERP Database Schema & Seed Data
-- Database: selected by the environment/hosting user
-- Target Engine: MySQL / MariaDB (XAMPP Compatible)
-- =================================================================

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'manager',
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `company_name` VARCHAR(150) NOT NULL DEFAULT 'Empress Tee - Beverage Depot ERP',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create production users from the hosting control panel or a one-time installer.

-- 1b. HR / Workforce Master Data
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(50) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `user_id` INT DEFAULT NULL,
  `full_name` VARCHAR(160) NOT NULL,
  `photo_path` TEXT DEFAULT NULL,
  `gender` VARCHAR(30) DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `state_of_origin` VARCHAR(100) DEFAULT NULL,
  `lga` VARCHAR(100) DEFAULT NULL,
  `emergency_contact` VARCHAR(160) DEFAULT NULL,
  `next_of_kin` VARCHAR(160) DEFAULT NULL,
  `employment_date` DATE DEFAULT NULL,
  `job_title` VARCHAR(120) DEFAULT NULL,
  `department` VARCHAR(120) DEFAULT NULL,
  `assigned_location` VARCHAR(120) DEFAULT NULL,
  `employment_status` VARCHAR(40) NOT NULL DEFAULT 'Applicant',
  `employee_role` VARCHAR(80) NOT NULL DEFAULT 'Staff',
  `kyc_status` VARCHAR(50) NOT NULL DEFAULT 'Not Submitted',
  `base_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `allowances` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `bank_name` VARCHAR(120) DEFAULT NULL,
  `account_number_masked` VARCHAR(40) DEFAULT NULL,
  `account_name` VARCHAR(160) DEFAULT NULL,
  `profile_json` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_employees_company_status` (`company_id`, `employment_status`),
  INDEX `idx_employees_dept_role` (`company_id`, `department`, `employee_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `storage_ref` TEXT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `uploaded_by` VARCHAR(120) DEFAULT NULL,
  `uploaded_at` DATETIME NOT NULL,
  INDEX `idx_employee_documents` (`company_id`, `employee_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_kyc` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kyc_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `nin_masked` VARCHAR(40) DEFAULT NULL,
  `bvn_masked` VARCHAR(40) DEFAULT NULL,
  `provider` VARCHAR(120) DEFAULT NULL,
  `reference` VARCHAR(160) DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `verification_json` LONGTEXT DEFAULT NULL,
  `updated_by` VARCHAR(120) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_employee_kyc` (`company_id`, `employee_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_key` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `role_name` VARCHAR(120) NOT NULL,
  `permissions_json` LONGTEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `attendance_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `employee_name` VARCHAR(160) NOT NULL,
  `attendance_date` DATE NOT NULL,
  `clock_in` DATETIME DEFAULT NULL,
  `clock_out` DATETIME DEFAULT NULL,
  `branch_name` VARCHAR(160) DEFAULT NULL,
  `device_id` VARCHAR(120) DEFAULT NULL,
  `attendance_method` VARCHAR(50) DEFAULT NULL,
  `ip_address` VARCHAR(80) DEFAULT NULL,
  `status` VARCHAR(80) DEFAULT NULL,
  `lateness` VARCHAR(80) DEFAULT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 0,
  `record_json` LONGTEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uniq_attendance_day` (`company_id`, `employee_code`, `attendance_date`),
  INDEX `idx_attendance_date` (`company_id`, `attendance_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `work_calendars` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `calendar_date` DATE NOT NULL,
  `day_type` VARCHAR(50) NOT NULL DEFAULT 'Working Day',
  `description` VARCHAR(180) DEFAULT NULL,
  `created_by` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  UNIQUE KEY `uniq_work_calendar` (`company_id`, `calendar_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_leave` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `leave_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `leave_type` VARCHAR(80) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `approved_by` VARCHAR(120) DEFAULT NULL,
  `record_json` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_employee_leave` (`company_id`, `employee_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_periods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `period_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `payroll_month` VARCHAR(7) NOT NULL,
  `status` VARCHAR(60) NOT NULL DEFAULT 'Draft',
  `total_gross` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_deductions` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_net` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payroll_json` LONGTEXT NOT NULL,
  `created_by` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_payroll_period` (`company_id`, `payroll_month`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_deductions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `deduction_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `source_type` VARCHAR(80) DEFAULT NULL,
  `source_ref` VARCHAR(100) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
  `created_at` DATETIME NOT NULL,
  INDEX `idx_payroll_deductions` (`company_id`, `employee_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `damage_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `incident_number` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `incident_at` DATETIME NOT NULL,
  `location` VARCHAR(160) DEFAULT NULL,
  `department` VARCHAR(120) DEFAULT NULL,
  `asset` VARCHAR(160) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `estimated_loss` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `investigation_status` VARCHAR(80) NOT NULL DEFAULT 'Reported',
  `approved_liability` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `deduction_method` VARCHAR(80) DEFAULT NULL,
  `report_json` LONGTEXT DEFAULT NULL,
  `created_by` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_damage_reports` (`company_id`, `employee_code`, `investigation_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_performance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `performance_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `period_label` VARCHAR(40) NOT NULL,
  `score` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `review_json` LONGTEXT DEFAULT NULL,
  `created_by` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_employee_performance` (`company_id`, `employee_code`, `period_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_disciplinary_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `disciplinary_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `action_type` VARCHAR(80) NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `status` VARCHAR(60) NOT NULL DEFAULT 'Created',
  `reviewed_by` VARCHAR(120) DEFAULT NULL,
  `approved_by` VARCHAR(120) DEFAULT NULL,
  `evidence_ref` TEXT DEFAULT NULL,
  `record_json` LONGTEXT DEFAULT NULL,
  `created_by` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_employee_disciplinary` (`company_id`, `employee_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_pos_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `terminal_name` VARCHAR(120) DEFAULT NULL,
  `login_at` DATETIME NOT NULL,
  `logout_at` DATETIME DEFAULT NULL,
  `session_json` LONGTEXT DEFAULT NULL,
  INDEX `idx_employee_pos_sessions` (`company_id`, `employee_code`, `login_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cashier_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `drawer_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `employee_code` VARCHAR(50) NOT NULL,
  `terminal_name` VARCHAR(120) DEFAULT NULL,
  `opening_cash` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cash_sales` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `expected_cash` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `counted_cash` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `variance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `variance_reason` TEXT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Open',
  `session_json` LONGTEXT DEFAULT NULL,
  `opened_at` DATETIME NOT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  INDEX `idx_cashier_sessions` (`company_id`, `employee_code`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `user_name` VARCHAR(120) DEFAULT NULL,
  `employee_code` VARCHAR(50) DEFAULT NULL,
  `module` VARCHAR(80) NOT NULL,
  `action` VARCHAR(160) NOT NULL,
  `entity_type` VARCHAR(80) DEFAULT NULL,
  `entity_id` VARCHAR(120) DEFAULT NULL,
  `old_value_json` LONGTEXT DEFAULT NULL,
  `new_value_json` LONGTEXT DEFAULT NULL,
  `ip_address` VARCHAR(80) DEFAULT NULL,
  `device_info` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_employee_audit` (`company_id`, `employee_code`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hr_settings` (
  `company_id` VARCHAR(50) PRIMARY KEY,
  `setting_json` LONGTEXT NOT NULL,
  `updated_by` VARCHAR(120) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Products Catalog Table
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `sku` VARCHAR(100) NOT NULL UNIQUE,
  `barcode` VARCHAR(100) DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `brand` VARCHAR(100) DEFAULT NULL,
  `category` VARCHAR(100) NOT NULL,
  `packaging` VARCHAR(100) NOT NULL,
  `packaging_type` VARCHAR(40) DEFAULT NULL,
  `inventory_unit` VARCHAR(40) DEFAULT NULL,
  `packs_per_pallet` INT NOT NULL DEFAULT 0,
  `invoice_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `rebate_base_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `minimum_selling_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `crate_deposit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_crates` INT NOT NULL DEFAULT 0,
  `stock_bottles` INT NOT NULL DEFAULT 0,
  `units_per_crate` INT NOT NULL DEFAULT 24,
  `min_stock` INT NOT NULL DEFAULT 20,
  `batch_number` VARCHAR(100) DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `image` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product master is loaded from app/Config/beverage_products.php so internal SKUs stay in one place.

-- 3. Customers Ledger Table
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(40) DEFAULT NULL,
  `customer_class` VARCHAR(80) NOT NULL DEFAULT 'Retail',
  `distributor_code` VARCHAR(80) DEFAULT NULL,
  `wallet_balance` DECIMAL(12,2) DEFAULT 0.00,
  `credit_limit` DECIMAL(12,2) DEFAULT 0.00,
  `current_debt` DECIMAL(12,2) DEFAULT 0.00,
  `crates_held` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Real customers should be added from the POS customer capture flow.

-- 4. Sales Orders Table
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `receipt_no` VARCHAR(100) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `customer_id` INT DEFAULT 1,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_class` VARCHAR(80) NOT NULL DEFAULT 'Retail',
  `distributor_code` VARCHAR(80) DEFAULT NULL,
  `sales_channel` VARCHAR(30) NOT NULL DEFAULT 'depot_sale',
  `employee_code` VARCHAR(50) DEFAULT NULL,
  `employee_role` VARCHAR(80) DEFAULT NULL,
  `pos_terminal` VARCHAR(120) DEFAULT NULL,
  `pos_login_time` DATETIME DEFAULT NULL,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `crate_deposit_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `change_due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cashier_name` VARCHAR(100) NOT NULL,
  `items_json` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4b. Dynamic Quantity Pricing Tiers
CREATE TABLE IF NOT EXISTS `pricing_tiers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tier_id` VARCHAR(50) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `product_sku` VARCHAR(100) NOT NULL,
  `tier_name` VARCHAR(150) NOT NULL,
  `min_qty` INT NOT NULL,
  `max_qty` INT DEFAULT NULL,
  `selling_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sales_channel` VARCHAR(30) NOT NULL DEFAULT 'all',
  `customer_class` VARCHAR(100) DEFAULT NULL,
  `location` VARCHAR(120) DEFAULT NULL,
  `min_selling_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `updated_by` VARCHAR(100) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_pricing_tier_lookup` (`company_id`, `product_sku`, `min_qty`, `max_qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4c. Hierarchical Rebate Rules
CREATE TABLE IF NOT EXISTS `rebate_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rule_id` VARCHAR(50) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `level` VARCHAR(30) NOT NULL,
  `target_key` VARCHAR(150) NOT NULL,
  `rebate_pct` DECIMAL(7,3) NOT NULL DEFAULT 0.000,
  `rebate_base_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `adjustment_factor` DECIMAL(7,3) NOT NULL DEFAULT 100.000,
  `formula_type` VARCHAR(50) NOT NULL DEFAULT 'standard_pct',
  `description` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `updated_by` VARCHAR(100) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_rebate_lookup` (`company_id`, `level`, `target_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promotion_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `promotion_id` VARCHAR(50) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `promotion_name` VARCHAR(160) NOT NULL,
  `promotion_code` VARCHAR(80) NOT NULL,
  `supplier` VARCHAR(150) DEFAULT NULL,
  `eligible_sku` VARCHAR(120) NOT NULL DEFAULT 'ALL',
  `qualifying_qty` INT NOT NULL DEFAULT 1,
  `reward_qty` INT NOT NULL DEFAULT 0,
  `reward_sku` VARCHAR(120) DEFAULT NULL,
  `benefit_type` VARCHAR(30) NOT NULL DEFAULT 'amount',
  `benefit_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `adjustment_factor` DECIMAL(7,3) NOT NULL DEFAULT 100.000,
  `allocation_method` VARCHAR(40) NOT NULL DEFAULT 'per_unit',
  `sales_channel` VARCHAR(30) NOT NULL DEFAULT 'all',
  `customer_class` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `approval_status` VARCHAR(30) NOT NULL DEFAULT 'approved',
  `updated_by` VARCHAR(100) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_promotion_lookup` (`company_id`, `eligible_sku`, `sales_channel`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4d. Pricing, Rebate, and Cost Audit Log
CREATE TABLE IF NOT EXISTS `pricing_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `log_id` VARCHAR(50) NOT NULL UNIQUE,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` VARCHAR(100) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `user_name` VARCHAR(100) NOT NULL,
  `old_value_json` TEXT,
  `new_value_json` TEXT,
  `created_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pricing_change_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id` VARCHAR(160) NOT NULL,
  `action_name` VARCHAR(120) NOT NULL,
  `old_value_json` LONGTEXT DEFAULT NULL,
  `new_value_json` LONGTEXT DEFAULT NULL,
  `payload_json` LONGTEXT NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `requested_by` VARCHAR(160) DEFAULT NULL,
  `requested_at` DATETIME NOT NULL,
  `approved_by` VARCHAR(160) DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `rejected_by` VARCHAR(160) DEFAULT NULL,
  `rejected_at` DATETIME DEFAULT NULL,
  INDEX `idx_price_approvals_status` (`company_id`, `status`, `requested_at`),
  INDEX `idx_price_approvals_entity` (`company_id`, `entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4e. Truck/Pallet Load Settings
CREATE TABLE IF NOT EXISTS `truck_load_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `setting_key` VARCHAR(80) NOT NULL DEFAULT 'default',
  `setting_value` TEXT NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uniq_truck_load_setting` (`company_id`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `truck_loads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `load_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `sales_channel` VARCHAR(30) NOT NULL DEFAULT 'diversion_sale',
  `customer_name` VARCHAR(160) DEFAULT NULL,
  `load_json` LONGTEXT NOT NULL,
  `created_by` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_truck_loads_company_channel` (`company_id`, `sales_channel`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_warehouse_stock` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `sku` VARCHAR(100) NOT NULL,
  `warehouse` VARCHAR(120) NOT NULL,
  `stock_crates` INT NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uniq_product_warehouse_stock` (`company_id`, `sku`, `warehouse`),
  INDEX `idx_product_warehouse_stock_lookup` (`company_id`, `warehouse`, `sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `movement_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `reference` VARCHAR(120) DEFAULT NULL,
  `user_name` VARCHAR(160) DEFAULT NULL,
  `action` VARCHAR(160) NOT NULL,
  `item_name` VARCHAR(180) DEFAULT NULL,
  `sku` VARCHAR(100) DEFAULT NULL,
  `quantity_label` VARCHAR(120) DEFAULT NULL,
  `previous_balance` VARCHAR(180) DEFAULT NULL,
  `new_balance` VARCHAR(180) DEFAULT NULL,
  `location` VARCHAR(180) DEFAULT NULL,
  `metadata_json` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_inventory_movements_company_date` (`company_id`, `created_at`),
  INDEX `idx_inventory_movements_sku` (`company_id`, `sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_transfers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transfer_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `from_location` VARCHAR(120) NOT NULL,
  `to_location` VARCHAR(120) NOT NULL,
  `sku` VARCHAR(100) NOT NULL,
  `item_name` VARCHAR(180) DEFAULT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `available_before` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Completed',
  `message` TEXT DEFAULT NULL,
  `transfer_json` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_stock_transfers_company_date` (`company_id`, `created_at`),
  INDEX `idx_stock_transfers_sku` (`company_id`, `sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_stock_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `report_date` DATE NOT NULL,
  `warehouse` VARCHAR(120) NOT NULL,
  `opening_snapshot` LONGTEXT NOT NULL,
  `closing_snapshot` LONGTEXT NULL,
  `opening_captured_at` DATETIME NULL,
  `closing_captured_at` DATETIME NULL,
  `created_by` VARCHAR(120) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uniq_daily_stock_report` (`company_id`, `report_date`, `warehouse`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beverage_grn_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `grn_number` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `po_number` VARCHAR(100) DEFAULT NULL,
  `supplier` VARCHAR(150) DEFAULT NULL,
  `sku` VARCHAR(100) DEFAULT NULL,
  `warehouse` VARCHAR(120) DEFAULT NULL,
  `accepted_qty` INT NOT NULL DEFAULT 0,
  `invoice_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `rebate_base_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `expected_rebate_pct` DECIMAL(7,3) NOT NULL DEFAULT 0.000,
  `expected_rebate_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `effective_landing_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `grn_json` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_beverage_grn_lookup` (`company_id`, `sku`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rebate_settlements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `settlement_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `supplier` VARCHAR(150) DEFAULT NULL,
  `sku` VARCHAR(100) NOT NULL DEFAULT 'ALL',
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `expected_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `confirmed_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `received_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Confirmed',
  `reference` VARCHAR(150) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `updated_by` VARCHAR(120) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  INDEX `idx_rebate_settlements_lookup` (`company_id`, `sku`, `period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Payment Reconciliations Table
CREATE TABLE IF NOT EXISTS `payment_reconciliations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recon_code` VARCHAR(100) NOT NULL UNIQUE,
  `type` VARCHAR(50) NOT NULL,
  `ref_no` VARCHAR(100) NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `channel` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Reconciled ✅',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `alert_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `source` VARCHAR(150) NOT NULL,
  `bank_name` VARCHAR(150) NOT NULL,
  `sender_name` VARCHAR(150) NOT NULL,
  `transaction_ref` VARCHAR(150) NOT NULL UNIQUE,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `alert_date` VARCHAR(80) DEFAULT NULL,
  `matched_to` VARCHAR(150) DEFAULT NULL,
  `status` VARCHAR(80) NOT NULL DEFAULT 'Unmatched Bank Alert',
  `confidence_score` VARCHAR(40) DEFAULT NULL,
  `reviewed_by` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_payment_alerts_company_created` (`company_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_mail_connector` (
  `company_id` VARCHAR(50) PRIMARY KEY,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `bank_name` VARCHAR(150) NOT NULL,
  `mailbox_email` VARCHAR(180) NOT NULL,
  `imap_host` VARCHAR(150) NOT NULL DEFAULT 'imap.gmail.com',
  `imap_port` INT NOT NULL DEFAULT 993,
  `imap_encryption` VARCHAR(20) NOT NULL DEFAULT 'ssl',
  `bank_sender` VARCHAR(180) NOT NULL,
  `last_sync` VARCHAR(80) NOT NULL DEFAULT 'Never',
  `status` VARCHAR(150) NOT NULL DEFAULT 'Not Connected',
  `has_secret` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `app_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `notification_id` VARCHAR(80) NOT NULL UNIQUE,
  `company_id` VARCHAR(50) NOT NULL DEFAULT 'beverage',
  `type` VARCHAR(40) NOT NULL DEFAULT 'info',
  `title` VARCHAR(180) NOT NULL,
  `message` TEXT NOT NULL,
  `url` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `read_at` DATETIME DEFAULT NULL,
  INDEX `idx_app_notifications_company_created` (`company_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key` VARCHAR(120) PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `is_secret` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_by` VARCHAR(120) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `app_settings_audit` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(120) NOT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `updated_by` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_app_settings_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. General Ledger Journal Entries Table
CREATE TABLE IF NOT EXISTS `gl_journal_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `journal_code` VARCHAR(100) NOT NULL UNIQUE,
  `entry_date` DATE NOT NULL,
  `description` TEXT NOT NULL,
  `debit_account` VARCHAR(100) NOT NULL,
  `credit_account` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
