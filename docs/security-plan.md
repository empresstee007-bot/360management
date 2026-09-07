# Security Plan & Compliance Framework — 360Management ERP

## 1. System Architecture & Authentication
- **Architecture**: Modern Native PHP 8.x with a custom modular OOP architecture and PDO database persistence engine.
- **Authentication**: Session-based user authentication ([`app/bootstrap.php`](file:///Applications/XAMPP/xamppfiles/htdocs/360Management/app/bootstrap.php)) with bcrypt password hashing via standard PHP `password_hash()` and `password_verify()`.
- **Role-Based Routing (RBAC)**: Role validation ([`canonical_role()`](file:///Applications/XAMPP/xamppfiles/htdocs/360Management/app/bootstrap.php#L98)) enforcing permissions across Administrative, Operations/Warehouse, Finance, and POS Cashier roles.

## 2. Database Protection & Transaction Integrity
- **SQL Injection Prevention**: Prepared statements with parameterized bindings via `App\Core\Database::getConnection()` using standard PDO exception handling and disabled emulated prepares (`PDO::ATTR_EMULATE_PREPARES => false`).
- **Atomic ERP Transactions**: Multi-table operations (POS Sales, Stock Deductions, Customer Ledger Updates, Payment Reconciliations, and General Ledger Entries) executed inside atomic PDO database transactions (`$pdo->beginTransaction()`, `$pdo->commit()`, `$pdo->rollBack()`).

## 3. Threat Protection & Input Sanitization
- **XSS Mitigation**: Mandatory HTML output escaping using the helper `e()` ([`htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`](file:///Applications/XAMPP/xamppfiles/htdocs/360Management/app/bootstrap.php#L52)) across all dynamic templates.
- **CSRF Defense**: Cryptographic token generation via `csrf_token()` ([`bin2hex(random_bytes(32))`](file:///Applications/XAMPP/xamppfiles/htdocs/360Management/app/bootstrap.php#L57)) and verification via `verify_csrf()` using timing-attack resistant `hash_equals()`.
- **File Upload Security**: Strict MIME-type checking (`mime_content_type()`), filename sanitization (`preg_replace('/[^a-zA-Z0-9_-]/', '_')`), and random hash prefixing for uploaded assets.

## 4. Audit Trail & Real-Time Sync
- Centralized audit tracking via `UnifiedDataEngine::syncInventoryAudit()` recording action type, user, timestamp, previous balance, and new balance.
- Dual-layer data engine providing high-speed in-memory session caching backed by persistent MySQL (`360management_db`) relational tables.
