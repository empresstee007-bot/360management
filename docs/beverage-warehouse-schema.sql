-- ============================================================================
-- 360Management ERP: Beverage Warehouse Management Module Database Schema
-- ============================================================================

CREATE TABLE IF NOT EXISTS `beverage_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beverage_brands` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `company_manufacturer` VARCHAR(150) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beverage_products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(60) NOT NULL UNIQUE,
    `barcode_qr` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `brand_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `flavour` VARCHAR(80) DEFAULT 'Original',
    `size_volume` VARCHAR(50) NOT NULL COMMENT 'e.g., 50cl, 75cl, 33cl, 1.5L',
    `packaging_type` ENUM('PET Plastic', 'Glass Bottle', 'Can', 'Tetra Pak') DEFAULT 'PET Plastic',
    `carton_crate_config` ENUM('Crate 24', 'Crate 12', 'Pack 12', 'Pack 24', 'Carton 24', 'Case 6') DEFAULT 'Crate 24',
    `units_per_crate` INT UNSIGNED DEFAULT 24,
    `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `selling_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `wholesale_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `retail_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `distributor_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `supplier_id` INT UNSIGNED NULL,
    `reorder_level` INT UNSIGNED DEFAULT 50,
    `min_stock` INT UNSIGNED DEFAULT 20,
    `max_stock` INT UNSIGNED DEFAULT 500,
    `safety_stock` INT UNSIGNED DEFAULT 15,
    `lead_time_days` INT UNSIGNED DEFAULT 3,
    `image_path` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `warehouse_zones` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `zone_name` VARCHAR(80) NOT NULL,
    `zone_type` ENUM('Soft Drinks', 'Water', 'Malt', 'Juice', 'Energy Drinks', 'Full Crates', 'Empty Crates', 'Returns', 'Damaged Goods', 'Expired Goods') NOT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `warehouse_locations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `zone_id` INT UNSIGNED NOT NULL,
    `rack_code` VARCHAR(50) NOT NULL,
    `bin_code` VARCHAR(50) NOT NULL,
    `capacity_crates` INT UNSIGNED DEFAULT 1000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beverage_batches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_number` VARCHAR(80) NOT NULL,
    `manufacturing_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `quality_status` ENUM('Approved', 'Quarantined', 'Expired', 'Rejected') DEFAULT 'Approved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beverage_stock_balances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `branch_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `zone_id` INT UNSIGNED NOT NULL,
    `location_id` INT UNSIGNED NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_id` INT UNSIGNED NOT NULL,
    `quantity_crates` INT UNSIGNED DEFAULT 0,
    `quantity_loose_bottles` INT UNSIGNED DEFAULT 0,
    `status` ENUM('Available', 'Reserved', 'In Transit', 'Damaged', 'Quarantined', 'Returned', 'Promotional', 'Expired', 'Expiring Soon') DEFAULT 'Available',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crate_inventories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `brand_id` INT UNSIGNED NOT NULL,
    `full_crates` INT DEFAULT 0,
    `empty_crates` INT DEFAULT 0,
    `loose_bottles` INT DEFAULT 0,
    `empty_bottles` INT DEFAULT 0,
    `customer_issued_crates` INT DEFAULT 0,
    `customer_returned_crates` INT DEFAULT 0,
    `damaged_crates` INT DEFAULT 0,
    `missing_crates` INT DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `customer_crate_deposits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `brand_id` INT UNSIGNED NOT NULL,
    `crates_issued` INT NOT NULL DEFAULT 0,
    `crates_returned` INT NOT NULL DEFAULT 0,
    `outstanding_crates` INT AS (`crates_issued` - `crates_returned`) STORED,
    `deposit_amount_naira` DECIMAL(12,2) DEFAULT 0.00,
    `last_activity_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `procurement_grn_receivings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `grn_number` VARCHAR(60) NOT NULL UNIQUE,
    `po_number` VARCHAR(60) NOT NULL,
    `supplier_name` VARCHAR(150) NOT NULL,
    `received_by_user_id` INT UNSIGNED NOT NULL,
    `received_date` DATE NOT NULL,
    `ordered_qty_crates` INT UNSIGNED NOT NULL,
    `delivered_qty_crates` INT UNSIGNED NOT NULL,
    `accepted_qty_crates` INT UNSIGNED NOT NULL,
    `rejected_qty_crates` INT UNSIGNED DEFAULT 0,
    `damaged_qty_crates` INT UNSIGNED DEFAULT 0,
    `short_delivery_crates` INT UNSIGNED DEFAULT 0,
    `excess_delivery_crates` INT UNSIGNED DEFAULT 0,
    `free_promo_crates` INT UNSIGNED DEFAULT 0,
    `inspection_status` ENUM('Pending Inspection', 'Passed', 'Failed Variance', 'Approved') DEFAULT 'Approved',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sales_stock_reservations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `sales_order_number` VARCHAR(60) NOT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `batch_id` INT UNSIGNED NOT NULL,
    `reserved_qty_crates` INT UNSIGNED NOT NULL,
    `status` ENUM('Reserved', 'Picked', 'Loaded', 'Dispatched', 'Cancelled') DEFAULT 'Reserved',
    `reserved_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vehicle_trip_reconciliations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `trip_code` VARCHAR(60) NOT NULL UNIQUE,
    `vehicle_reg` VARCHAR(40) NOT NULL,
    `driver_name` VARCHAR(120) NOT NULL,
    `trip_date` DATE NOT NULL,
    `opening_crates` INT NOT NULL DEFAULT 0,
    `loaded_crates` INT NOT NULL DEFAULT 0,
    `sold_crates` INT NOT NULL DEFAULT 0,
    `damaged_crates` INT NOT NULL DEFAULT 0,
    `returned_crates` INT NOT NULL DEFAULT 0,
    `expected_closing_crates` INT AS (`opening_crates` + `loaded_crates` - `sold_crates` - `damaged_crates` - `returned_crates`) STORED,
    `driver_physical_crates` INT NOT NULL DEFAULT 0,
    `variance_crates` INT AS (`driver_physical_crates` - (`opening_crates` + `loaded_crates` - `sold_crates` - `damaged_crates` - `returned_crates`)) STORED,
    `reconciliation_status` ENUM('Balanced', 'Variance Flagged', 'Manager Approved') DEFAULT 'Balanced',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beverage_dispatches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `dispatch_code` VARCHAR(60) NOT NULL UNIQUE,
    `sales_order_number` VARCHAR(60) NOT NULL,
    `vehicle_reg` VARCHAR(40) NOT NULL,
    `driver_name` VARCHAR(120) NOT NULL,
    `route_destination` VARCHAR(150) NOT NULL,
    `status` ENUM('Pending', 'Picking', 'Loaded', 'Dispatched', 'In Transit', 'Delivered', 'Reconciled') DEFAULT 'Pending',
    `empty_crates_collected` INT DEFAULT 0,
    `proof_of_delivery_signature` VARCHAR(255) NULL,
    `dispatched_at` TIMESTAMP NULL,
    `delivered_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beverage_breakage_damages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity_crates` INT UNSIGNED NOT NULL,
    `quantity_loose_bottles` INT UNSIGNED DEFAULT 0,
    `loss_type` ENUM('Broken Bottle', 'Leaking Bottle', 'Damaged Can', 'Crushed Carton', 'Expired Product', 'Missing Crate') NOT NULL,
    `recorded_by_user` VARCHAR(100) NOT NULL,
    `reason_notes` TEXT NOT NULL,
    `loss_value_naira` DECIMAL(12,2) NOT NULL,
    `manager_approval_required` TINYINT(1) DEFAULT 0,
    `approval_status` ENUM('Pending Approval', 'Approved', 'Rejected') DEFAULT 'Approved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `beverage_audit_trails` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `organisation_id` INT UNSIGNED NOT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `reference_no` VARCHAR(80) NULL,
    `previous_value` TEXT NULL,
    `new_value` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
