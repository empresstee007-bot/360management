<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll;

use App\Core\Database;

class HrPayrollService
{
    private const STATUSES = ['Applicant', 'Onboarding', 'Active', 'Suspended', 'On Leave', 'Resigned', 'Terminated', 'Inactive'];
    private const SENSITIVE_KEYS = ['nin', 'bvn', 'account_number'];

    private static array $employeesMock = [];

    public static function getEmployees(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $rows = $pdo->query('SELECT * FROM employees ORDER BY id DESC')->fetchAll() ?: [];
                $employees = array_map([self::class, 'hydrateEmployee'], $rows);
                $_SESSION['employees'] = $employees;
                return $employees;
            } catch (\Throwable $t) {
            }
        }

        return $_SESSION['employees'] ?? self::$employeesMock;
    }

    public static function getEmployeeByCode(string $employeeCode): ?array
    {
        $employeeCode = strtoupper(trim($employeeCode));
        foreach (self::getEmployees() as $employee) {
            if (strtoupper((string)($employee['employee_code'] ?? '')) === $employeeCode) {
                return $employee;
            }
        }
        return null;
    }

    public static function getDrivers(): array
    {
        return array_values(array_filter(self::getEmployees(), static function (array $employee): bool {
            $role = strtolower((string)($employee['employee_role'] ?? $employee['designation'] ?? ''));
            return str_contains($role, 'driver') || trim((string)($employee['driver_license_number'] ?? '')) !== '';
        }));
    }

    public static function addEmployee(array $data): array
    {
        return self::saveEmployee($data, null);
    }

    public static function saveEmployee(array $data, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $employees = self::getEmployees();
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $existing = null;
        foreach ($employees as $candidate) {
            if ($employeeCode !== '' && strtoupper((string)($candidate['employee_code'] ?? '')) === $employeeCode) {
                $existing = $candidate;
                break;
            }
        }

        $isNew = $existing === null;
        $employeeCode = $employeeCode !== '' ? $employeeCode : self::nextEmployeeCode($data);
        $status = trim((string)($data['status'] ?? ($existing['status'] ?? 'Applicant')));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'Applicant';
        }

        $hasAccess = !empty($data['has_dashboard_access']) || !empty($existing['has_dashboard_access']);
        $role = trim((string)($data['employee_role'] ?? $data['role'] ?? $existing['employee_role'] ?? 'Staff'));
        $dashInfo = self::dashboardForRole($role, (string)($data['assigned_dashboard'] ?? $existing['assigned_dashboard'] ?? 'beverage_warehouse'), $hasAccess);
        $permissions = self::permissionsForRole($role);

        $employee = [
            'id' => (int)($existing['id'] ?? count($employees) + 1),
            'employee_code' => $employeeCode,
            'name' => trim((string)($data['name'] ?? $data['full_name'] ?? $existing['name'] ?? 'New Staff Member')),
            'full_name' => trim((string)($data['name'] ?? $data['full_name'] ?? $existing['full_name'] ?? 'New Staff Member')),
            'photo' => trim((string)($data['photo'] ?? $existing['photo'] ?? '')),
            'gender' => trim((string)($data['gender'] ?? $existing['gender'] ?? '')),
            'date_of_birth' => trim((string)($data['date_of_birth'] ?? $existing['date_of_birth'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? $existing['phone'] ?? '')),
            'email' => trim((string)($data['email'] ?? $existing['email'] ?? 'staff@360management.com')),
            'address' => trim((string)($data['address'] ?? $existing['address'] ?? '')),
            'state_of_origin' => trim((string)($data['state_of_origin'] ?? $existing['state_of_origin'] ?? '')),
            'lga' => trim((string)($data['lga'] ?? $existing['lga'] ?? '')),
            'emergency_contact' => trim((string)($data['emergency_contact'] ?? $existing['emergency_contact'] ?? '')),
            'next_of_kin' => trim((string)($data['next_of_kin'] ?? $existing['next_of_kin'] ?? '')),
            'employment_date' => trim((string)($data['employment_date'] ?? $existing['employment_date'] ?? date('Y-m-d'))),
            'job_title' => trim((string)($data['job_title'] ?? $data['designation'] ?? $existing['job_title'] ?? 'Staff Officer')),
            'designation' => trim((string)($data['designation'] ?? $data['job_title'] ?? $existing['designation'] ?? 'Staff Officer')),
            'department' => trim((string)($data['department'] ?? $existing['department'] ?? 'General Operations')),
            'branch' => trim((string)($data['branch'] ?? $data['assigned_location'] ?? $existing['branch'] ?? 'Jacroxx Warehouse')),
            'assigned_location' => trim((string)($data['assigned_location'] ?? $data['branch'] ?? $existing['assigned_location'] ?? 'Jacroxx Warehouse')),
            'status' => $status,
            'employee_role' => $role,
            'permissions' => $permissions,
            'has_dashboard_access' => $hasAccess,
            'assigned_dashboard' => $dashInfo['key'],
            'dashboard_label' => $dashInfo['label'],
            'dashboard_url' => $dashInfo['url'],
            'base_salary' => (float)($data['base_salary'] ?? $existing['base_salary'] ?? 0),
            'allowances' => (float)($data['allowances'] ?? $existing['allowances'] ?? 0),
            'bonus' => (float)($data['bonus'] ?? $existing['bonus'] ?? 0),
            'commission' => (float)($data['commission'] ?? $existing['commission'] ?? 0),
            'salary_advance' => (float)($data['salary_advance'] ?? $existing['salary_advance'] ?? 0),
            'loan_deduction' => (float)($data['loan_deduction'] ?? $existing['loan_deduction'] ?? 0),
            'driver_license_number' => trim((string)($data['driver_license_number'] ?? $existing['driver_license_number'] ?? '')),
            'driver_license_expiry' => trim((string)($data['driver_license_expiry'] ?? $existing['driver_license_expiry'] ?? '')),
            'vehicle_assignment' => trim((string)($data['vehicle_assignment'] ?? $existing['vehicle_assignment'] ?? '')),
            'driver_notes' => trim((string)($data['driver_notes'] ?? $existing['driver_notes'] ?? '')),
            'bank_name' => trim((string)($data['bank_name'] ?? $existing['bank_name'] ?? '')),
            'account_number' => trim((string)($data['account_number'] ?? $existing['account_number'] ?? '')),
            'account_name' => trim((string)($data['account_name'] ?? $existing['account_name'] ?? '')),
            'nin' => trim((string)($data['nin'] ?? $existing['nin'] ?? '')),
            'bvn' => trim((string)($data['bvn'] ?? $existing['bvn'] ?? '')),
            'kyc_status' => trim((string)($data['kyc_status'] ?? $existing['kyc_status'] ?? 'Not Submitted')),
            'onboarding_stage' => self::resolveOnboardingStage($data, $existing),
            'created_at' => (string)($existing['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $employee['onboarding_progress'] = self::calculateOnboardingProgress($employee);

        self::persistEmployee($employee);
        self::upsertSessionEmployee($employee);
        self::logAudit('employee', $employeeCode, $isNew ? 'Create Employee' : 'Update Employee', self::redact($existing ?? []), self::redact($employee), $user ?? (\current_user()['name'] ?? 'HR Admin'));

        return $employee;
    }

    public static function toggleStaffAccess(int $empId): array
    {
        $employees = self::getEmployees();
        foreach ($employees as $employee) {
            if ((int)$employee['id'] !== $empId) {
                continue;
            }
            $employee['has_dashboard_access'] = empty($employee['has_dashboard_access']);
            $dashInfo = self::dashboardForRole((string)($employee['employee_role'] ?? 'Staff'), (string)($employee['assigned_dashboard'] ?? 'beverage_warehouse'), (bool)$employee['has_dashboard_access']);
            $employee['assigned_dashboard'] = $dashInfo['key'];
            $employee['dashboard_label'] = $dashInfo['label'];
            $employee['dashboard_url'] = $dashInfo['url'];
            $employee['updated_at'] = date('Y-m-d H:i:s');
            self::persistEmployee($employee);
            self::upsertSessionEmployee($employee);
            self::logAudit('employee_access', (string)$employee['employee_code'], 'Toggle Dashboard Access', [], ['has_dashboard_access' => $employee['has_dashboard_access']], \current_user()['name'] ?? 'HR Admin');
            return $employee;
        }
        return [];
    }

    public static function saveKyc(array $data, ?string $user = null): array
    {
        $settings = self::getSettings();
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $provider = trim((string)($data['provider'] ?? $settings['kyc_provider'] ?? 'Provn'));
        $providerStatus = self::kycProviderStatus($provider);
        $record = [
            'kyc_id' => 'KYC-' . date('YmdHis') . '-' . rand(10, 99),
            'employee_code' => $employeeCode,
            'nin' => trim((string)($data['nin'] ?? '')),
            'bvn' => trim((string)($data['bvn'] ?? '')),
            'phone_status' => trim((string)($data['phone_status'] ?? 'Pending')),
            'bank_status' => trim((string)($data['bank_status'] ?? 'Pending')),
            'id_status' => trim((string)($data['id_status'] ?? 'Pending')),
            'address_status' => trim((string)($data['address_status'] ?? 'Pending')),
            'provider' => $provider,
            'provider_key' => $providerStatus['key'],
            'provider_mode' => $providerStatus['mode'],
            'provider_message' => $providerStatus['message'],
            'reference' => trim((string)($data['reference'] ?? '')),
            'status' => trim((string)($data['status'] ?? 'Pending')),
            'notes' => trim((string)($data['notes'] ?? '')),
            'updated_by' => $user ?? (\current_user()['name'] ?? 'HR Admin'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->prepare('INSERT INTO employee_kyc (kyc_id, company_id, employee_code, nin_masked, bvn_masked, provider, reference, status, verification_json, updated_by, updated_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nin_masked=VALUES(nin_masked), bvn_masked=VALUES(bvn_masked), provider=VALUES(provider), reference=VALUES(reference), status=VALUES(status), verification_json=VALUES(verification_json), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)');
                $stmt->execute([$record['kyc_id'], $employeeCode, self::maskSensitive($record['nin']), self::maskSensitive($record['bvn']), $record['provider'], $record['reference'], $record['status'], json_encode(self::redact($record), JSON_UNESCAPED_SLASHES), $record['updated_by'], $record['updated_at']]);
            } catch (\Throwable $t) {
            }
        }

        $_SESSION['employee_kyc_records'] = $_SESSION['employee_kyc_records'] ?? [];
        array_unshift($_SESSION['employee_kyc_records'], self::redact($record));
        self::logAudit('employee_kyc', $employeeCode, 'Update KYC', [], self::redact($record), $record['updated_by']);
        self::updateEmployeeKycStatus($employeeCode, $record['status']);
        return self::redact($record);
    }

    public static function getKycRecords(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $rows = $pdo->query('SELECT * FROM employee_kyc WHERE company_id = "beverage" ORDER BY id DESC LIMIT 200')->fetchAll() ?: [];
                return array_map(static fn(array $row): array => [
                    'employee_code' => $row['employee_code'],
                    'nin' => $row['nin_masked'],
                    'bvn' => $row['bvn_masked'],
                    'provider' => $row['provider'],
                    'reference' => $row['reference'],
                    'status' => $row['status'],
                    'updated_by' => $row['updated_by'],
                    'updated_at' => $row['updated_at'],
                ], $rows);
            } catch (\Throwable $t) {
            }
        }
        return $_SESSION['employee_kyc_records'] ?? [];
    }

    public static function saveEmployeeDocument(array $data, array $files = [], ?string $user = null): array
    {
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $documentType = trim((string)($data['document_type'] ?? 'Other HR Document'));
        $documentId = 'DOC-' . date('YmdHis') . '-' . rand(10, 99);
        $storageRef = trim((string)($data['storage_ref'] ?? ''));

        $file = $files['employee_document'] ?? null;
        if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $safeExt = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            $safeExt = preg_replace('/[^a-z0-9]/', '', $safeExt) ?: 'dat';
            $dir = dirname(__DIR__, 3) . '/storage/hr_documents';
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            $target = $dir . '/' . $documentId . '.' . $safeExt;
            if (@move_uploaded_file((string)$file['tmp_name'], $target)) {
                $storageRef = $target;
            }
        }

        $record = [
            'document_id' => $documentId,
            'employee_code' => $employeeCode,
            'document_type' => $documentType,
            'storage_ref' => $storageRef,
            'status' => trim((string)($data['status'] ?? 'Pending')),
            'uploaded_by' => $user ?? (\current_user()['name'] ?? 'HR Admin'),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $_SESSION['employee_documents'] = $_SESSION['employee_documents'] ?? [];
        array_unshift($_SESSION['employee_documents'], $record);

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->prepare('INSERT INTO employee_documents (document_id, company_id, employee_code, document_type, storage_ref, status, uploaded_by, uploaded_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$record['document_id'], $record['employee_code'], $record['document_type'], $record['storage_ref'], $record['status'], $record['uploaded_by'], $record['uploaded_at']]);
            } catch (\Throwable $t) {
            }
        }

        self::logAudit('employee_document', $documentId, 'Add Employee Document', [], ['employee_code' => $employeeCode, 'document_type' => $documentType, 'status' => $record['status']], $record['uploaded_by']);
        return $record;
    }

    public static function getEmployeeDocuments(string $employeeCode = ''): array
    {
        $employeeCode = strtoupper(trim($employeeCode));
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                if ($employeeCode !== '') {
                    $stmt = $pdo->prepare('SELECT document_id, employee_code, document_type, status, uploaded_by, uploaded_at FROM employee_documents WHERE company_id = "beverage" AND employee_code = ? ORDER BY id DESC LIMIT 100');
                    $stmt->execute([$employeeCode]);
                } else {
                    $stmt = $pdo->query('SELECT document_id, employee_code, document_type, status, uploaded_by, uploaded_at FROM employee_documents WHERE company_id = "beverage" ORDER BY id DESC LIMIT 100');
                }
                return $stmt ? $stmt->fetchAll() : [];
            } catch (\Throwable $t) {
            }
        }
        $records = $_SESSION['employee_documents'] ?? [];
        if ($employeeCode === '') {
            return $records;
        }
        return array_values(array_filter($records, static fn(array $row): bool => strtoupper((string)($row['employee_code'] ?? '')) === $employeeCode));
    }

    public static function getSettings(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $defaults = [
            'employee_number_prefix' => 'EMP',
            'opening_time' => '08:00',
            'grace_minutes' => 15,
            'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            'payroll_cycle' => 'monthly',
            'approval_workflow' => ['Draft', 'Calculated', 'Reviewed', 'Approved', 'Payment Processing', 'Paid', 'Closed'],
            'kyc_provider' => 'Provn',
            'attendance_methods' => ['pin', 'qr', 'card'],
            'payment_provider' => 'Manual Bank Transfer',
            'pos_employee_required' => true,
        ];

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->prepare('SELECT setting_json FROM hr_settings WHERE company_id = "beverage" LIMIT 1');
                $stmt->execute();
                $stored = $stmt->fetchColumn();
                if ($stored) {
                    return array_replace_recursive($defaults, json_decode((string)$stored, true) ?: []);
                }
            } catch (\Throwable $t) {
            }
        }

        return array_replace_recursive($defaults, $_SESSION['hr_settings'] ?? []);
    }

    public static function saveSettings(array $data, ?string $user = null): array
    {
        $old = self::getSettings();
        $workingDays = array_values((array)($data['working_days'] ?? $old['working_days']));
        $workingDays = array_values(array_intersect(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], $workingDays));
        if (empty($workingDays)) {
            $workingDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        }
        $settings = [
            'employee_number_prefix' => strtoupper(trim((string)($data['employee_number_prefix'] ?? $old['employee_number_prefix']))),
            'opening_time' => trim((string)($data['opening_time'] ?? $old['opening_time'])),
            'grace_minutes' => max(0, (int)($data['grace_minutes'] ?? $old['grace_minutes'])),
            'working_days' => $workingDays,
            'payroll_cycle' => trim((string)($data['payroll_cycle'] ?? $old['payroll_cycle'])),
            'kyc_provider' => self::normalizeKycProvider((string)($data['kyc_provider'] ?? $old['kyc_provider'] ?? 'Provn')),
            'attendance_methods' => array_values((array)($data['attendance_methods'] ?? $old['attendance_methods'])),
            'payment_provider' => trim((string)($data['payment_provider'] ?? $old['payment_provider'])),
            'pos_employee_required' => !empty($data['pos_employee_required']),
            'approval_workflow' => $old['approval_workflow'],
        ];
        $_SESSION['hr_settings'] = $settings;

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->prepare('INSERT INTO hr_settings (company_id, setting_json, updated_by, updated_at) VALUES ("beverage", ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_json=VALUES(setting_json), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)');
                $stmt->execute([json_encode($settings, JSON_UNESCAPED_SLASHES), $user ?? (\current_user()['name'] ?? 'HR Admin')]);
            } catch (\Throwable $t) {
            }
        }
        self::logAudit('hr_settings', 'beverage', 'Update HR Settings', $old, $settings, $user ?? (\current_user()['name'] ?? 'HR Admin'));
        return $settings;
    }

    public static function permissionCatalog(): array
    {
        return [
            'employee.view' => 'View Employees',
            'employee.create' => 'Create Employees',
            'employee.edit' => 'Edit Employees',
            'employee.suspend' => 'Suspend / Discipline Employees',
            'employee.kyc.view' => 'View KYC',
            'employee.kyc.verify' => 'Verify KYC',
            'attendance.clock' => 'Clock Attendance',
            'attendance.manage' => 'Manage Attendance',
            'payroll.view' => 'View Payroll',
            'payroll.process' => 'Process Payroll',
            'payroll.approve' => 'Approve Payroll',
            'payroll.pay' => 'Pay Payroll',
            'damage.create' => 'Create Damage Report',
            'damage.approve' => 'Approve Damage Liability',
            'pos.login' => 'POS Login',
            'pos.refund' => 'POS Refund',
            'pos.discount' => 'POS Discount',
            'pos.void' => 'POS Void',
            'cashdrawer.open' => 'Open Cash Drawer',
            'cashdrawer.close' => 'Close Cash Drawer',
            'reports.employee' => 'Employee Reports',
            'hr.settings.manage' => 'Manage HR Settings',
        ];
    }

    public static function getRoles(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $roles = self::defaultRoleDefinitions();
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $rows = $pdo->query('SELECT role_key, role_name, permissions_json, is_active, updated_at FROM employee_roles WHERE company_id = "beverage" ORDER BY role_name ASC')->fetchAll() ?: [];
                foreach ($rows as $row) {
                    $key = self::roleKey((string)$row['role_key']);
                    $roles[$key] = [
                        'role_key' => $key,
                        'role_name' => (string)($row['role_name'] ?? $key),
                        'permissions' => self::sanitizePermissions(json_decode((string)($row['permissions_json'] ?? '[]'), true) ?: []),
                        'is_active' => !empty($row['is_active']),
                        'updated_at' => (string)($row['updated_at'] ?? ''),
                        'is_system' => isset(self::defaultRoleDefinitions()[$key]),
                    ];
                }
                $_SESSION['employee_roles'] = $roles;
                return array_values($roles);
            } catch (\Throwable $t) {
            }
        }

        foreach (($_SESSION['employee_roles'] ?? []) as $role) {
            $key = self::roleKey((string)($role['role_key'] ?? $role['role_name'] ?? ''));
            if ($key !== '') {
                $roles[$key] = [
                    'role_key' => $key,
                    'role_name' => (string)($role['role_name'] ?? $key),
                    'permissions' => self::sanitizePermissions((array)($role['permissions'] ?? [])),
                    'is_active' => !empty($role['is_active']),
                    'updated_at' => (string)($role['updated_at'] ?? ''),
                    'is_system' => isset(self::defaultRoleDefinitions()[$key]),
                ];
            }
        }

        return array_values($roles);
    }

    public static function saveRole(array $data, ?string $user = null): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $roleName = trim((string)($data['role_name'] ?? ''));
        $roleKey = self::roleKey((string)($data['role_key'] ?? $roleName));
        if ($roleName === '') {
            $roleName = ucwords(str_replace('_', ' ', $roleKey));
        }
        if ($roleKey === '') {
            $roleKey = 'custom_role_' . date('His');
        }

        $old = self::roleByKey($roleKey) ?? [];
        $role = [
            'role_key' => $roleKey,
            'role_name' => $roleName,
            'permissions' => self::sanitizePermissions((array)($data['permissions'] ?? [])),
            'is_active' => !empty($data['is_active']),
            'updated_at' => date('Y-m-d H:i:s'),
            'is_system' => isset(self::defaultRoleDefinitions()[$roleKey]),
        ];

        $_SESSION['employee_roles'] = $_SESSION['employee_roles'] ?? [];
        $_SESSION['employee_roles'][$roleKey] = $role;

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->prepare('INSERT INTO employee_roles (role_key, company_id, role_name, permissions_json, is_active, updated_at) VALUES (?, "beverage", ?, ?, ?, ?) ON DUPLICATE KEY UPDATE role_name=VALUES(role_name), permissions_json=VALUES(permissions_json), is_active=VALUES(is_active), updated_at=VALUES(updated_at)');
                $stmt->execute([$role['role_key'], $role['role_name'], json_encode($role['permissions'], JSON_UNESCAPED_SLASHES), $role['is_active'] ? 1 : 0, $role['updated_at']]);
            } catch (\Throwable $t) {
            }
        }

        self::logAudit('employee_role', $roleKey, empty($old) ? 'Create Employee Role' : 'Update Employee Role', $old, $role, $user ?? (\current_user()['name'] ?? 'HR Admin'));
        return $role;
    }

    public static function saveWorkCalendarDay(array $data, ?string $user = null): array
    {
        $date = trim((string)($data['calendar_date'] ?? ''));
        if ($date === '' || strtotime($date) === false) {
            $date = date('Y-m-d');
        }
        $allowedTypes = ['Working Day', 'Weekend', 'Public Holiday', 'Company Holiday', 'Special Closure'];
        $dayType = trim((string)($data['day_type'] ?? 'Working Day'));
        if (!in_array($dayType, $allowedTypes, true)) {
            $dayType = 'Working Day';
        }

        $record = [
            'company_id' => 'beverage',
            'calendar_date' => date('Y-m-d', strtotime($date)),
            'day_type' => $dayType,
            'description' => trim((string)($data['description'] ?? '')),
            'created_by' => $user ?? (\current_user()['name'] ?? 'HR Admin'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        self::persistWorkCalendarDay($record);
        self::logAudit('work_calendar', $record['calendar_date'], 'Update Work Calendar', [], $record, $record['created_by']);
        return $record;
    }

    public static function getWorkCalendar(?string $from = null, ?string $to = null): array
    {
        $from = $from && strtotime($from) !== false ? date('Y-m-d', strtotime($from)) : date('Y-m-01');
        $to = $to && strtotime($to) !== false ? date('Y-m-d', strtotime($to)) : date('Y-m-t');

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->prepare('SELECT company_id, calendar_date, day_type, description, created_by, created_at FROM work_calendars WHERE company_id = "beverage" AND calendar_date BETWEEN ? AND ? ORDER BY calendar_date ASC');
                $stmt->execute([$from, $to]);
                return $stmt->fetchAll() ?: [];
            } catch (\Throwable $t) {
            }
        }

        return array_values(array_filter($_SESSION['work_calendar_days'] ?? [], static function (array $record) use ($from, $to): bool {
            $date = (string)($record['calendar_date'] ?? '');
            return $date >= $from && $date <= $to;
        }));
    }

    public static function classifyWorkDate(?string $date = null): array
    {
        $date = $date && strtotime($date) !== false ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
        foreach (self::getWorkCalendar($date, $date) as $record) {
            if ((string)($record['calendar_date'] ?? '') === $date) {
                return [
                    'date' => $date,
                    'day_type' => (string)($record['day_type'] ?? 'Working Day'),
                    'description' => (string)($record['description'] ?? ''),
                    'is_working_day' => (string)($record['day_type'] ?? 'Working Day') === 'Working Day',
                    'source' => 'calendar_override',
                ];
            }
        }

        $settings = self::getSettings();
        $weekday = date('D', strtotime($date));
        $isWorking = in_array($weekday, (array)($settings['working_days'] ?? []), true);
        return [
            'date' => $date,
            'day_type' => $isWorking ? 'Working Day' : 'Weekend',
            'description' => $isWorking ? 'Configured working day' : 'Configured non-working day',
            'is_working_day' => $isWorking,
            'source' => 'working_days_setting',
        ];
    }

    public static function getKycProviders(): array
    {
        return [
            'provn' => [
                'name' => 'Provn',
                'mode' => 'configurable_api',
                'supports' => ['NIN', 'BVN', 'Phone', 'Bank Account', 'ID Document'],
                'status' => 'Ready for credentials',
            ],
            'manual_review' => [
                'name' => 'Manual Review',
                'mode' => 'manual',
                'supports' => ['Document Review', 'HR Approval'],
                'status' => 'Available',
            ],
        ];
    }

    public static function kycProviderStatus(string $provider = ''): array
    {
        $provider = self::normalizeKycProvider($provider !== '' ? $provider : (string)(self::getSettings()['kyc_provider'] ?? 'Provn'));
        $key = strtolower(str_replace([' ', '-'], '_', $provider));
        $providers = self::getKycProviders();
        $known = $providers[$key] ?? $providers['provn'];
        $credentialsSaved = false;
        if ($key === 'provn' && class_exists('App\Core\AppSettingsService')) {
            $credentialsSaved = \App\Core\AppSettingsService::secretIsSaved('HR_KYC_PROVN_API_KEY')
                && \App\Core\AppSettingsService::secretIsSaved('HR_KYC_PROVN_API_SECRET');
        }

        return [
            'key' => $key,
            'name' => $known['name'],
            'mode' => $known['mode'],
            'status' => $key === 'provn' && $credentialsSaved ? 'Credentials saved' : $known['status'],
            'credentials_saved' => $credentialsSaved,
            'message' => $key === 'provn'
                ? ($credentialsSaved
                    ? 'Provn is selected for KYC and credentials are saved for live verification.'
                    : 'Provn is selected for KYC. Add the live API credentials in settings before automatic verification calls are enabled.')
                : 'Manual HR review is selected for KYC.',
        ];
    }

    private static function normalizeKycProvider(string $provider): string
    {
        $provider = trim($provider);
        if ($provider === '') {
            return 'Provn';
        }
        $key = strtolower(str_replace([' ', '-'], '_', $provider));
        return $key === 'manual_review' ? 'Manual Review' : 'Provn';
    }

    public static function processMonthlyPayroll(string $month = ''): array
    {
        $month = $month !== '' ? $month : date('Y-m');
        $employees = array_values(array_filter(self::getEmployees(), static fn(array $emp): bool => in_array((string)($emp['status'] ?? 'Active'), ['Active', 'On Leave'], true)));
        $damageDeductions = self::approvedLiabilityDeductions($month);
        $records = [];
        $totalGross = 0.0;
        $totalNet = 0.0;
        $totalTax = 0.0;
        $totalPension = 0.0;
        $totalDeductionsAll = 0.0;

        foreach ($employees as $emp) {
            $base = (float)($emp['base_salary'] ?? 0);
            $allowances = (float)($emp['allowances'] ?? 0);
            $bonus = (float)($emp['bonus'] ?? 0);
            $commission = (float)($emp['commission'] ?? 0);
            $gross = $base + $allowances + $bonus + $commission;
            $taxPAYE = round($gross * 0.10, 2);
            $pension = round($gross * 0.08, 2);
            $attendanceDeduction = self::attendanceDeductionForEmployee((string)$emp['employee_code'], $month, $base);
            $damageDeduction = $damageDeductions[$emp['employee_code']] ?? 0.0;
            $otherDeductions = (float)($emp['salary_advance'] ?? 0) + (float)($emp['loan_deduction'] ?? 0);
            $totalDeductions = $taxPAYE + $pension + $attendanceDeduction + $damageDeduction + $otherDeductions;
            $netPay = max(0.0, $gross - $totalDeductions);

            $record = [
                'employee_code' => $emp['employee_code'],
                'name' => $emp['name'],
                'designation' => $emp['designation'],
                'department' => $emp['department'],
                'branch' => $emp['branch'],
                'has_dashboard_access' => $emp['has_dashboard_access'] ?? true,
                'assigned_dashboard' => $emp['assigned_dashboard'] ?? 'beverage_warehouse',
                'dashboard_label' => $emp['dashboard_label'] ?? 'Beverage ERP',
                'dashboard_url' => $emp['dashboard_url'] ?? 'beverage_warehouse.php',
                'base_salary' => $base,
                'allowances' => $allowances,
                'bonus' => $bonus,
                'commission' => $commission,
                'gross_pay' => $gross,
                'tax_paye' => $taxPAYE,
                'pension' => $pension,
                'attendance_deductions' => $attendanceDeduction,
                'damage_deductions' => $damageDeduction,
                'other_deductions' => $otherDeductions,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
                'payment_status' => 'Draft',
                'payslip_code' => 'PAY-' . $month . '-' . $emp['employee_code'],
            ];
            $records[] = $record;
            $totalGross += $gross;
            $totalNet += $netPay;
            $totalTax += $taxPAYE;
            $totalPension += $pension;
            $totalDeductionsAll += $totalDeductions;
        }

        $period = [
            'period_id' => 'PAYROLL-' . $month,
            'month' => $month,
            'status' => 'Calculated',
            'records' => $records,
            'employee_count' => count($records),
            'total_gross_payroll' => $totalGross,
            'total_net_payroll' => $totalNet,
            'total_tax_paye' => $totalTax,
            'total_pension' => $totalPension,
            'total_deductions' => $totalDeductionsAll,
            'created_by' => \current_user()['name'] ?? 'HR Admin',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        self::persistPayrollPeriod($period);
        self::logAudit('payroll', $period['period_id'], 'Calculate Payroll', [], ['month' => $month, 'employee_count' => count($records), 'net' => $totalNet], $period['created_by']);
        return $period;
    }

    public static function getPayrollPeriods(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $rows = $pdo->query('SELECT payroll_json FROM payroll_periods WHERE company_id = "beverage" ORDER BY id DESC LIMIT 24')->fetchAll() ?: [];
                if ($rows) {
                    $periods = [];
                    foreach ($rows as $row) {
                        $period = json_decode((string)$row['payroll_json'], true) ?: null;
                        $periodId = (string)($period['period_id'] ?? '');
                        if ($periodId !== '' && !isset($periods[$periodId])) {
                            $periods[$periodId] = $period;
                        }
                    }
                    return array_values($periods);
                }
            } catch (\Throwable $t) {
            }
        }
        return $_SESSION['payroll_periods'] ?? [];
    }

    public static function getPayrollPeriod(string $periodId): ?array
    {
        $periodId = trim($periodId);
        if ($periodId === '') {
            return null;
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->prepare('SELECT payroll_json FROM payroll_periods WHERE company_id = "beverage" AND period_id = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([$periodId]);
                $period = json_decode((string)($stmt->fetchColumn() ?: ''), true) ?: null;
                if ($period) {
                    return $period;
                }
            } catch (\Throwable $t) {
            }
        }

        foreach (self::getPayrollPeriods() as $period) {
            if ((string)($period['period_id'] ?? '') === $periodId) {
                return $period;
            }
        }

        return null;
    }

    public static function updatePayrollStatus(string $periodId, string $status, ?string $user = null): array
    {
        $allowed = ['Draft', 'Calculated', 'Reviewed', 'Approved', 'Payment Processing', 'Paid', 'Closed'];
        if (!in_array($status, $allowed, true)) {
            return ['success' => false, 'message' => 'Invalid payroll status.'];
        }

        $period = self::getPayrollPeriod($periodId);
        if (!$period) {
            return ['success' => false, 'message' => 'Payroll period was not found.'];
        }

        $old = $period;
        $period['status'] = $status;
        $period['updated_by'] = $user ?? (\current_user()['name'] ?? 'HR Admin');
        $period['updated_at'] = date('Y-m-d H:i:s');
        if (!empty($period['records']) && is_array($period['records'])) {
            foreach ($period['records'] as $recordIndex => $record) {
                $period['records'][$recordIndex]['payment_status'] = $status;
                $period['records'][$recordIndex]['payment_updated_at'] = $period['updated_at'];
                $period['records'][$recordIndex]['payment_updated_by'] = $period['updated_by'];
            }
        }

        self::persistPayrollPeriod($period);
        self::replaceSessionPayrollPeriod($period);
        self::logAudit('payroll', $periodId, 'Update Payroll Status', ['status' => $old['status'] ?? 'Draft'], ['status' => $status], $period['updated_by']);

        return ['success' => true, 'message' => "Payroll moved to {$status}.", 'period' => $period];
    }

    public static function getPayslip(string $periodId, string $employeeCode): ?array
    {
        $period = self::getPayrollPeriod($periodId);
        if (!$period) {
            return null;
        }

        $employeeCode = strtoupper(trim($employeeCode));
        foreach ($period['records'] ?? [] as $record) {
            if (strtoupper((string)($record['employee_code'] ?? '')) === $employeeCode) {
                return [
                    'company_name' => 'EMPRESS TEE BEVERAGE DEPOT',
                    'period' => $period,
                    'record' => $record,
                    'employee' => self::getEmployeeByCode($employeeCode) ?? [],
                ];
            }
        }

        return null;
    }

    public static function createDamageReport(array $data, ?string $user = null): array
    {
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $loss = max(0.0, (float)($data['estimated_loss'] ?? 0));
        $liability = max(0.0, (float)($data['approved_liability'] ?? 0));
        $status = trim((string)($data['status'] ?? 'Reported'));
        $incident = [
            'incident_number' => 'DMG-' . date('YmdHis') . '-' . rand(10, 99),
            'employee_code' => $employeeCode,
            'incident_at' => trim((string)($data['incident_at'] ?? date('Y-m-d H:i:s'))),
            'location' => trim((string)($data['location'] ?? '')),
            'department' => trim((string)($data['department'] ?? '')),
            'asset' => trim((string)($data['asset'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'estimated_loss' => $loss,
            'witness' => trim((string)($data['witness'] ?? '')),
            'supervisor' => trim((string)($data['supervisor'] ?? '')),
            'investigation_status' => $status,
            'employee_response' => trim((string)($data['employee_response'] ?? '')),
            'approved_liability' => $liability,
            'deduction_amount' => max(0.0, (float)($data['deduction_amount'] ?? $liability)),
            'deduction_method' => trim((string)($data['deduction_method'] ?? 'Pending Approval')),
            'installments' => max(1, (int)($data['installments'] ?? 1)),
            'created_by' => $user ?? (\current_user()['name'] ?? 'HR Admin'),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        self::persistDamageReport($incident);
        self::logAudit('employee_damage', $incident['incident_number'], 'Create Damage Report', [], $incident, $incident['created_by']);
        return $incident;
    }

    public static function getDamageReports(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $rows = $pdo->query('SELECT report_json FROM damage_reports WHERE company_id = "beverage" ORDER BY id DESC LIMIT 200')->fetchAll() ?: [];
                if ($rows) {
                    return array_values(array_filter(array_map(static fn(array $row): ?array => json_decode((string)$row['report_json'], true) ?: null, $rows)));
                }
            } catch (\Throwable $t) {
            }
        }
        return $_SESSION['damage_reports'] ?? [];
    }

    public static function updateDamageStatus(string $incidentNumber, string $status, array $data = [], ?string $user = null): array
    {
        $incidentNumber = trim($incidentNumber);
        $allowed = ['Reported', 'Under Investigation', 'Employee Response', 'Reviewed', 'Approved', 'Rejected', 'Deduction Scheduled', 'Closed'];
        if ($incidentNumber === '' || !in_array($status, $allowed, true)) {
            return ['success' => false, 'message' => 'Invalid damage report update.'];
        }

        foreach (self::getDamageReports() as $report) {
            if ((string)($report['incident_number'] ?? '') !== $incidentNumber) {
                continue;
            }

            $old = $report;
            $report['investigation_status'] = $status;
            $report['employee_response'] = trim((string)($data['employee_response'] ?? $report['employee_response'] ?? ''));
            $report['approved_liability'] = max(0.0, (float)($data['approved_liability'] ?? $report['approved_liability'] ?? 0));
            $report['deduction_amount'] = max(0.0, (float)($data['deduction_amount'] ?? $report['deduction_amount'] ?? $report['approved_liability']));
            $report['deduction_method'] = trim((string)($data['deduction_method'] ?? $report['deduction_method'] ?? 'Pending Approval'));
            $report['installments'] = max(1, (int)($data['installments'] ?? $report['installments'] ?? 1));
            $report['updated_by'] = $user ?? (\current_user()['name'] ?? 'HR Admin');
            $report['updated_at'] = date('Y-m-d H:i:s');

            self::persistDamageReport($report, true);
            self::logAudit('employee_damage', $incidentNumber, 'Update Damage Status', self::redact($old), self::redact($report), $report['updated_by']);
            return ['success' => true, 'message' => "Damage report {$incidentNumber} moved to {$status}.", 'report' => $report];
        }

        return ['success' => false, 'message' => 'Damage report was not found.'];
    }

    public static function createLeaveRecord(array $data, ?string $user = null): array
    {
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $record = [
            'leave_id' => 'LV-' . date('YmdHis') . '-' . rand(10, 99),
            'employee_code' => $employeeCode,
            'leave_type' => trim((string)($data['leave_type'] ?? 'Annual Leave')),
            'start_date' => trim((string)($data['start_date'] ?? date('Y-m-d'))),
            'end_date' => trim((string)($data['end_date'] ?? date('Y-m-d'))),
            'status' => trim((string)($data['status'] ?? 'Pending')),
            'reason' => trim((string)($data['reason'] ?? '')),
            'approved_by' => trim((string)($data['approved_by'] ?? '')),
            'created_by' => $user ?? (\current_user()['name'] ?? 'HR Admin'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        self::persistLeaveRecord($record);
        self::logAudit('employee_leave', $record['leave_id'], 'Create Leave Record', [], $record, $record['created_by']);
        return $record;
    }

    public static function getLeaveRecords(?string $employeeCode = null): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                if ($employeeCode) {
                    $stmt = $pdo->prepare('SELECT record_json FROM employee_leave WHERE company_id = "beverage" AND employee_code = ? ORDER BY id DESC LIMIT 100');
                    $stmt->execute([strtoupper($employeeCode)]);
                    $rows = $stmt->fetchAll() ?: [];
                } else {
                    $rows = $pdo->query('SELECT record_json FROM employee_leave WHERE company_id = "beverage" ORDER BY id DESC LIMIT 150')->fetchAll() ?: [];
                }
                if ($rows) {
                    return array_values(array_filter(array_map(static fn(array $row): ?array => json_decode((string)$row['record_json'], true) ?: null, $rows)));
                }
            } catch (\Throwable $t) {
            }
        }

        $records = $_SESSION['employee_leave_records'] ?? [];
        if ($employeeCode) {
            $target = strtoupper($employeeCode);
            $records = array_values(array_filter($records, static fn(array $record): bool => strtoupper((string)($record['employee_code'] ?? '')) === $target));
        }
        return $records;
    }

    public static function createPerformanceReview(array $data, ?string $user = null): array
    {
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $review = [
            'performance_id' => 'PERF-' . date('YmdHis') . '-' . rand(10, 99),
            'employee_code' => $employeeCode,
            'period_label' => trim((string)($data['period_label'] ?? date('Y-m'))),
            'score' => max(0.0, min(100.0, (float)($data['score'] ?? 0))),
            'attendance_score' => max(0.0, min(100.0, (float)($data['attendance_score'] ?? 0))),
            'punctuality_score' => max(0.0, min(100.0, (float)($data['punctuality_score'] ?? 0))),
            'operations_score' => max(0.0, min(100.0, (float)($data['operations_score'] ?? 0))),
            'commendations' => trim((string)($data['commendations'] ?? '')),
            'review_notes' => trim((string)($data['review_notes'] ?? '')),
            'reviewed_by' => $user ?? (\current_user()['name'] ?? 'HR Admin'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        self::persistPerformanceReview($review);
        self::logAudit('employee_performance', $review['performance_id'], 'Create Performance Review', [], $review, $review['reviewed_by']);
        return $review;
    }

    public static function getPerformanceReviews(?string $employeeCode = null): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                if ($employeeCode) {
                    $stmt = $pdo->prepare('SELECT review_json FROM employee_performance WHERE company_id = "beverage" AND employee_code = ? ORDER BY id DESC LIMIT 100');
                    $stmt->execute([strtoupper($employeeCode)]);
                    $rows = $stmt->fetchAll() ?: [];
                } else {
                    $rows = $pdo->query('SELECT review_json FROM employee_performance WHERE company_id = "beverage" ORDER BY id DESC LIMIT 150')->fetchAll() ?: [];
                }
                if ($rows) {
                    return array_values(array_filter(array_map(static fn(array $row): ?array => json_decode((string)$row['review_json'], true) ?: null, $rows)));
                }
            } catch (\Throwable $t) {
            }
        }

        $records = $_SESSION['employee_performance_records'] ?? [];
        if ($employeeCode) {
            $target = strtoupper($employeeCode);
            $records = array_values(array_filter($records, static fn(array $record): bool => strtoupper((string)($record['employee_code'] ?? '')) === $target));
        }
        return $records;
    }

    public static function createDisciplinaryRecord(array $data, ?string $user = null): array
    {
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $record = [
            'disciplinary_id' => 'DISC-' . date('YmdHis') . '-' . rand(10, 99),
            'employee_code' => $employeeCode,
            'action_type' => trim((string)($data['action_type'] ?? 'Verbal Warning')),
            'reason' => trim((string)($data['reason'] ?? '')),
            'status' => trim((string)($data['status'] ?? 'Created')),
            'reviewed_by' => trim((string)($data['reviewed_by'] ?? '')),
            'approved_by' => trim((string)($data['approved_by'] ?? '')),
            'evidence_ref' => trim((string)($data['evidence_ref'] ?? '')),
            'created_by' => $user ?? (\current_user()['name'] ?? 'HR Admin'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        self::persistDisciplinaryRecord($record);
        self::logAudit('employee_disciplinary', $record['disciplinary_id'], 'Create Disciplinary Record', [], $record, $record['created_by']);
        return $record;
    }

    public static function getDisciplinaryRecords(?string $employeeCode = null): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                if ($employeeCode) {
                    $stmt = $pdo->prepare('SELECT record_json FROM employee_disciplinary_records WHERE company_id = "beverage" AND employee_code = ? ORDER BY id DESC LIMIT 100');
                    $stmt->execute([strtoupper($employeeCode)]);
                    $rows = $stmt->fetchAll() ?: [];
                } else {
                    $rows = $pdo->query('SELECT record_json FROM employee_disciplinary_records WHERE company_id = "beverage" ORDER BY id DESC LIMIT 150')->fetchAll() ?: [];
                }
                if ($rows) {
                    return array_values(array_filter(array_map(static fn(array $row): ?array => json_decode((string)$row['record_json'], true) ?: null, $rows)));
                }
            } catch (\Throwable $t) {
            }
        }

        $records = $_SESSION['employee_disciplinary_records'] ?? [];
        if ($employeeCode) {
            $target = strtoupper($employeeCode);
            $records = array_values(array_filter($records, static fn(array $record): bool => strtoupper((string)($record['employee_code'] ?? '')) === $target));
        }
        return $records;
    }

    public static function employeeForUser(array $user): ?array
    {
        $email = strtolower(trim((string)($user['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        foreach (self::getEmployees() as $employee) {
            if (strtolower((string)($employee['email'] ?? '')) === $email) {
                return $employee;
            }
        }

        return null;
    }

    public static function openCashierSession(array $data, array $user): array
    {
        $employee = self::employeeForUser($user);
        $employeeCode = (string)($employee['employee_code'] ?? strtoupper(str_replace(['@', '.', '+'], '-', (string)($user['email'] ?? 'POS'))));
        $terminal = trim((string)($data['terminal_name'] ?? 'Main POS')) ?: 'Main POS';
        $existing = self::getCurrentCashierSession($employeeCode, $terminal);
        if ($existing) {
            return ['success' => true, 'message' => 'Cash drawer session is already open.', 'session' => $existing];
        }

        $session = [
            'drawer_id' => 'DRAWER-' . date('YmdHis') . '-' . rand(10, 99),
            'employee_code' => $employeeCode,
            'employee_name' => (string)($employee['name'] ?? $user['name'] ?? 'POS Cashier'),
            'terminal_name' => $terminal,
            'opening_cash' => max(0.0, (float)($data['opening_cash'] ?? 0)),
            'cash_sales' => 0.0,
            'expected_cash' => max(0.0, (float)($data['opening_cash'] ?? 0)),
            'counted_cash' => 0.0,
            'variance' => 0.0,
            'variance_reason' => '',
            'status' => 'Open',
            'opened_by' => (string)($user['name'] ?? 'POS Cashier'),
            'opened_at' => date('Y-m-d H:i:s'),
            'closed_by' => '',
            'closed_at' => null,
        ];

        self::persistCashierSession($session, false);
        self::logAudit('cashier_session', $session['drawer_id'], 'Open Cash Drawer', [], $session, $session['opened_by']);
        return ['success' => true, 'message' => 'Cash drawer opened.', 'session' => $session];
    }

    public static function closeCashierSession(array $data, array $shiftSummary, array $user): array
    {
        $employee = self::employeeForUser($user);
        $employeeCode = (string)($employee['employee_code'] ?? strtoupper(str_replace(['@', '.', '+'], '-', (string)($user['email'] ?? 'POS'))));
        $terminal = trim((string)($data['terminal_name'] ?? 'Main POS')) ?: 'Main POS';
        $session = self::getCurrentCashierSession($employeeCode, $terminal) ?? [
            'drawer_id' => 'DRAWER-' . date('YmdHis') . '-' . rand(10, 99),
            'employee_code' => $employeeCode,
            'employee_name' => (string)($employee['name'] ?? $user['name'] ?? 'POS Cashier'),
            'terminal_name' => $terminal,
            'opening_cash' => max(0.0, (float)($data['opening_cash'] ?? 0)),
            'opened_by' => (string)($user['name'] ?? 'POS Cashier'),
            'opened_at' => date('Y-m-d H:i:s'),
        ];

        $openingCash = max(0.0, (float)($data['opening_cash'] ?? $session['opening_cash'] ?? 0));
        $cashSales = (float)($shiftSummary['total_cash'] ?? 0);
        $expectedCash = $openingCash + $cashSales;
        $countedCash = max(0.0, (float)($data['physical_cash'] ?? 0));
        $variance = round($countedCash - $expectedCash, 2);
        $old = $session;

        $session = array_replace($session, [
            'opening_cash' => $openingCash,
            'cash_sales' => $cashSales,
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'variance' => $variance,
            'variance_reason' => trim((string)($data['variance_reason'] ?? '')),
            'status' => 'Closed',
            'closed_by' => (string)($user['name'] ?? 'POS Cashier'),
            'closed_at' => date('Y-m-d H:i:s'),
        ]);

        self::persistCashierSession($session, true);
        self::logAudit('cashier_session', $session['drawer_id'], 'Close Cash Drawer', self::redact($old), self::redact($session), $session['closed_by']);
        return ['success' => true, 'message' => 'Cash drawer closed.', 'session' => $session];
    }

    public static function getCurrentCashierSession(?string $employeeCode = null, ?string $terminal = null): ?array
    {
        foreach (self::getCashierSessions('Open') as $session) {
            if ($employeeCode && strtoupper((string)($session['employee_code'] ?? '')) !== strtoupper($employeeCode)) {
                continue;
            }
            if ($terminal && strcasecmp((string)($session['terminal_name'] ?? ''), $terminal) !== 0) {
                continue;
            }
            return $session;
        }
        return null;
    }

    public static function getCashierSessions(?string $status = null): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                if ($status) {
                    $stmt = $pdo->prepare('SELECT session_json FROM cashier_sessions WHERE company_id = "beverage" AND status = ? ORDER BY id DESC LIMIT 150');
                    $stmt->execute([$status]);
                    $rows = $stmt->fetchAll() ?: [];
                } else {
                    $rows = $pdo->query('SELECT session_json FROM cashier_sessions WHERE company_id = "beverage" ORDER BY id DESC LIMIT 150')->fetchAll() ?: [];
                }
                if ($rows) {
                    return array_values(array_filter(array_map(static fn(array $row): ?array => json_decode((string)$row['session_json'], true) ?: null, $rows)));
                }
            } catch (\Throwable $t) {
            }
        }

        $sessions = $_SESSION['cashier_sessions'] ?? [];
        if ($status) {
            $sessions = array_values(array_filter($sessions, static fn(array $session): bool => (string)($session['status'] ?? '') === $status));
        }
        return $sessions;
    }

    public static function exportReportCsv(string $reportType): void
    {
        $reportType = strtolower(trim($reportType));
        $rows = self::reportRows($reportType);
        $headers = self::reportHeaders($reportType);
        $filename = 'hr-' . preg_replace('/[^a-z0-9_-]+/', '-', $reportType ?: 'report') . '-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if (!$out) {
            exit;
        }
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn(string $key): string => (string)($row[$key] ?? ''), $headers));
        }
        fclose($out);
        exit;
    }

    public static function getDashboardMetrics(): array
    {
        $employees = self::getEmployees();
        $attendance = GeoAttendanceService::getAttendanceLogs();
        $today = date('Y-m-d');
        $todayLogs = array_values(array_filter($attendance, static fn(array $log): bool => str_starts_with((string)($log['clock_in'] ?? ''), $today)));
        $active = count(array_filter($employees, static fn(array $e): bool => in_array((string)($e['status'] ?? ''), ['Active', 'On Leave'], true)));
        $kycPending = count(array_filter($employees, static fn(array $e): bool => !in_array((string)($e['kyc_status'] ?? ''), ['Verified'], true)));
        $damagePending = count(array_filter(self::getDamageReports(), static fn(array $r): bool => !in_array((string)($r['investigation_status'] ?? ''), ['Closed', 'Rejected'], true)));
        $dayClass = self::classifyWorkDate($today);
        $absentToday = !empty($dayClass['is_working_day']) ? max(0, $active - count($todayLogs)) : 0;

        return [
            'total_employees' => count($employees),
            'present_today' => count($todayLogs),
            'late_today' => count(array_filter($todayLogs, static fn(array $log): bool => stripos((string)($log['lateness'] ?? $log['status'] ?? ''), 'late') !== false)),
            'absent_today' => $absentToday,
            'calendar_day_type' => $dayClass['day_type'],
            'calendar_day_description' => $dayClass['description'],
            'official_duty' => count(array_filter($todayLogs, static fn(array $log): bool => stripos((string)($log['status'] ?? ''), 'official') !== false || stripos((string)($log['status'] ?? ''), 'field') !== false)),
            'pending_kyc' => $kycPending,
            'payroll_due' => date('d') >= 25 ? 1 : 0,
            'pending_damage_investigations' => $damagePending,
            'pending_payroll_approvals' => count(array_filter(self::getPayrollPeriods(), static fn(array $p): bool => !in_array((string)($p['status'] ?? ''), ['Paid', 'Closed'], true))),
        ];
    }

    public static function getAuditLogs(int $limit = 100): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                self::ensureTables($pdo);
                $stmt = $pdo->query('SELECT * FROM employee_audit_logs WHERE company_id = "beverage" ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
                return $stmt ? $stmt->fetchAll() : [];
            } catch (\Throwable $t) {
            }
        }
        return array_slice($_SESSION['employee_audit_logs'] ?? [], 0, $limit);
    }

    public static function reportRows(string $reportType): array
    {
        return match ($reportType) {
            'attendance' => array_map(static fn(array $row): array => [
                'employee_code' => (string)($row['employee_code'] ?? ''),
                'employee_name' => (string)($row['employee_name'] ?? ''),
                'department' => (string)($row['department'] ?? ''),
                'date' => (string)($row['attendance_date'] ?? ''),
                'clock_in' => (string)($row['clock_in'] ?? ''),
                'clock_out' => (string)($row['clock_out'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'lateness' => (string)($row['lateness'] ?? ''),
                'branch' => (string)($row['branch_name'] ?? ''),
                'method' => (string)($row['attendance_method'] ?? ''),
            ], GeoAttendanceService::getAttendanceLogs()),
            'payroll' => self::flattenPayrollRows(),
            'damage' => self::getDamageReports(),
            'kyc' => self::getKycRecords(),
            'cashier' => self::getCashierSessions(),
            'leave' => self::getLeaveRecords(),
            'calendar' => self::getWorkCalendar(date('Y-01-01'), date('Y-12-31')),
            'drivers' => self::getDriverAccountabilityRows(),
            'performance' => self::getPerformanceReviews(),
            'disciplinary' => self::getDisciplinaryRecords(),
            'audit' => self::getAuditLogs(500),
            default => array_map(static fn(array $employee): array => self::redact($employee), self::getEmployees()),
        };
    }

    private static function reportHeaders(string $reportType): array
    {
        return match ($reportType) {
            'attendance' => ['employee_code', 'employee_name', 'department', 'date', 'clock_in', 'clock_out', 'status', 'lateness', 'branch', 'method'],
            'payroll' => ['period_id', 'month', 'status', 'employee_code', 'name', 'department', 'designation', 'branch', 'gross_pay', 'total_deductions', 'net_pay', 'payment_status'],
            'damage' => ['incident_number', 'employee_code', 'incident_at', 'location', 'department', 'asset', 'estimated_loss', 'investigation_status', 'approved_liability', 'deduction_amount', 'deduction_method', 'installments'],
            'kyc' => ['employee_code', 'provider', 'reference', 'status', 'nin', 'bvn', 'updated_by', 'updated_at'],
            'cashier' => ['drawer_id', 'employee_code', 'employee_name', 'terminal_name', 'opening_cash', 'cash_sales', 'expected_cash', 'counted_cash', 'variance', 'variance_reason', 'status', 'opened_at', 'closed_at'],
            'leave' => ['leave_id', 'employee_code', 'leave_type', 'start_date', 'end_date', 'status', 'reason', 'approved_by', 'created_by', 'created_at'],
            'calendar' => ['calendar_date', 'day_type', 'description', 'created_by', 'created_at'],
            'drivers' => ['employee_code', 'name', 'phone', 'license_number', 'license_expiry', 'vehicle_assignment', 'deliveries', 'pending_pod', 'damage_cases', 'notes'],
            'performance' => ['performance_id', 'employee_code', 'period_label', 'score', 'attendance_score', 'punctuality_score', 'operations_score', 'commendations', 'review_notes', 'reviewed_by', 'created_at'],
            'disciplinary' => ['disciplinary_id', 'employee_code', 'action_type', 'reason', 'status', 'reviewed_by', 'approved_by', 'evidence_ref', 'created_by', 'created_at'],
            'audit' => ['user_name', 'employee_code', 'module', 'action', 'entity_type', 'entity_id', 'old_value_json', 'new_value_json', 'ip_address', 'created_at'],
            default => ['employee_code', 'name', 'email', 'phone', 'department', 'designation', 'branch', 'status', 'employee_role', 'kyc_status', 'base_salary', 'allowances', 'dashboard_label', 'updated_at'],
        };
    }

    private static function flattenPayrollRows(): array
    {
        $rows = [];
        foreach (self::getPayrollPeriods() as $period) {
            foreach ($period['records'] ?? [] as $record) {
                $rows[] = [
                    'period_id' => (string)($period['period_id'] ?? ''),
                    'month' => (string)($period['month'] ?? ''),
                    'status' => (string)($period['status'] ?? ''),
                    'employee_code' => (string)($record['employee_code'] ?? ''),
                    'name' => (string)($record['name'] ?? ''),
                    'department' => (string)($record['department'] ?? ''),
                    'designation' => (string)($record['designation'] ?? ''),
                    'branch' => (string)($record['branch'] ?? ''),
                    'gross_pay' => (string)($record['gross_pay'] ?? 0),
                    'total_deductions' => (string)($record['total_deductions'] ?? 0),
                    'net_pay' => (string)($record['net_pay'] ?? 0),
                    'payment_status' => (string)($record['payment_status'] ?? ''),
                ];
            }
        }
        return $rows;
    }

    public static function getDriverAccountabilityRows(): array
    {
        $deliveries = class_exists('App\Modules\Logistics\LogisticsService')
            ? \App\Modules\Logistics\LogisticsService::getDeliveries('beverage')
            : ($_SESSION['logistics_deliveries'] ?? []);
        $damageReports = self::getDamageReports();

        return array_map(static function (array $driver) use ($deliveries, $damageReports): array {
            $code = strtoupper((string)($driver['employee_code'] ?? ''));
            $name = strtolower((string)($driver['name'] ?? ''));
            $driverDeliveries = array_values(array_filter($deliveries, static function (array $delivery) use ($code, $name): bool {
                $deliveryCode = strtoupper((string)($delivery['driver_employee_code'] ?? ''));
                $deliveryDriver = strtolower((string)($delivery['driver_name'] ?? ''));
                return ($code !== '' && $deliveryCode === $code) || ($name !== '' && $deliveryDriver === $name);
            }));
            $driverDamage = array_values(array_filter($damageReports, static fn(array $report): bool => strtoupper((string)($report['employee_code'] ?? '')) === $code));

            return [
                'employee_code' => $code,
                'name' => (string)($driver['name'] ?? ''),
                'phone' => (string)($driver['phone'] ?? ''),
                'license_number' => (string)($driver['driver_license_number'] ?? ''),
                'license_expiry' => (string)($driver['driver_license_expiry'] ?? ''),
                'vehicle_assignment' => (string)($driver['vehicle_assignment'] ?? ''),
                'deliveries' => (string)count($driverDeliveries),
                'pending_pod' => (string)count(array_filter($driverDeliveries, static fn(array $delivery): bool => empty($delivery['pod_acknowledged']))),
                'damage_cases' => (string)count($driverDamage),
                'notes' => (string)($driver['driver_notes'] ?? ''),
            ];
        }, self::getDrivers());
    }

    public static function maskSensitive(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return str_repeat('*', max(0, strlen($value) - 4)) . substr($value, -4);
    }

    private static function nextEmployeeCode(array $data = []): string
    {
        $settings = self::getSettings();
        $prefix = strtoupper(trim((string)($data['employee_number_prefix'] ?? $settings['employee_number_prefix'] ?? 'EMP'))) ?: 'EMP';
        $max = 0;
        foreach (self::getEmployees() as $employee) {
            if (preg_match('/(\d+)$/', (string)($employee['employee_code'] ?? ''), $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
        return $prefix . '-' . sprintf('%04d', $max + 1);
    }

    private static function hydrateEmployee(array $row): array
    {
        $json = json_decode((string)($row['profile_json'] ?? ''), true) ?: [];
        return array_replace($json, [
            'id' => (int)$row['id'],
            'employee_code' => (string)$row['employee_code'],
            'name' => (string)$row['full_name'],
            'full_name' => (string)$row['full_name'],
            'email' => (string)($row['email'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'department' => (string)($row['department'] ?? ''),
            'designation' => (string)($row['job_title'] ?? ''),
            'job_title' => (string)($row['job_title'] ?? ''),
            'branch' => (string)($row['assigned_location'] ?? ''),
            'assigned_location' => (string)($row['assigned_location'] ?? ''),
            'status' => (string)($row['employment_status'] ?? 'Applicant'),
            'employee_role' => (string)($row['employee_role'] ?? 'Staff'),
            'kyc_status' => (string)($row['kyc_status'] ?? 'Not Submitted'),
            'base_salary' => (float)($row['base_salary'] ?? 0),
            'allowances' => (float)($row['allowances'] ?? 0),
            'has_dashboard_access' => (bool)($json['has_dashboard_access'] ?? false),
            'dashboard_label' => (string)($json['dashboard_label'] ?? 'No Dashboard Access'),
            'dashboard_url' => (string)($json['dashboard_url'] ?? '#'),
        ]);
    }

    private static function persistEmployee(array $employee): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO employees (employee_code, company_id, user_id, full_name, photo_path, gender, date_of_birth, phone, email, address, state_of_origin, lga, emergency_contact, next_of_kin, employment_date, job_title, department, assigned_location, employment_status, employee_role, kyc_status, base_salary, allowances, bank_name, account_number_masked, account_name, profile_json, created_at, updated_at) VALUES (?, "beverage", NULL, ?, ?, ?, NULLIF(?, ""), ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), photo_path=VALUES(photo_path), gender=VALUES(gender), date_of_birth=VALUES(date_of_birth), phone=VALUES(phone), email=VALUES(email), address=VALUES(address), state_of_origin=VALUES(state_of_origin), lga=VALUES(lga), emergency_contact=VALUES(emergency_contact), next_of_kin=VALUES(next_of_kin), employment_date=VALUES(employment_date), job_title=VALUES(job_title), department=VALUES(department), assigned_location=VALUES(assigned_location), employment_status=VALUES(employment_status), employee_role=VALUES(employee_role), kyc_status=VALUES(kyc_status), base_salary=VALUES(base_salary), allowances=VALUES(allowances), bank_name=VALUES(bank_name), account_number_masked=VALUES(account_number_masked), account_name=VALUES(account_name), profile_json=VALUES(profile_json), updated_at=VALUES(updated_at)');
            $stmt->execute([
                $employee['employee_code'], $employee['full_name'], $employee['photo'], $employee['gender'], $employee['date_of_birth'], $employee['phone'], $employee['email'], $employee['address'], $employee['state_of_origin'], $employee['lga'], $employee['emergency_contact'], $employee['next_of_kin'], $employee['employment_date'], $employee['job_title'], $employee['department'], $employee['assigned_location'], $employee['status'], $employee['employee_role'], $employee['kyc_status'], $employee['base_salary'], $employee['allowances'], $employee['bank_name'], self::maskSensitive((string)$employee['account_number']), $employee['account_name'], json_encode(self::redact($employee), JSON_UNESCAPED_SLASHES), $employee['created_at'], $employee['updated_at'],
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function upsertSessionEmployee(array $employee): void
    {
        $_SESSION['employees'] = $_SESSION['employees'] ?? [];
        $updated = false;
        foreach ($_SESSION['employees'] as &$candidate) {
            if ((string)($candidate['employee_code'] ?? '') === (string)$employee['employee_code']) {
                $candidate = $employee;
                $updated = true;
                break;
            }
        }
        unset($candidate);
        if (!$updated) {
            array_unshift($_SESSION['employees'], $employee);
        }
    }

    private static function dashboardForRole(string $role, string $requested, bool $hasAccess): array
    {
        $dashMap = [
            'beverage_warehouse' => ['label' => 'Beverage Depot ERP', 'url' => 'beverage_warehouse.php'],
            'pos_beverage' => ['label' => 'Beverage POS Terminal', 'url' => 'beverage_pos.php?tab=pos'],
            'finance' => ['label' => 'Finance & Accounting', 'url' => 'finance.php'],
            'logistics' => ['label' => 'Logistics & Delivery POD', 'url' => 'dashboard.php?tab=tab-logistics&view=dispatch'],
            'hr' => ['label' => 'HR & Workforce', 'url' => 'dashboard.php?tab=tab-hr-payroll&view=dashboard'],
            'employee_self_service' => ['label' => 'Employee Self-Service', 'url' => 'employee_self_service.php'],
        ];
        if (!$hasAccess) {
            return ['key' => 'none', 'label' => 'No Dashboard Access', 'url' => '#'];
        }
        $lower = strtolower($role);
        if (str_contains($lower, 'cashier') || str_contains($lower, 'pos')) {
            $requested = 'pos_beverage';
        } elseif (str_contains($lower, 'hr')) {
            $requested = 'hr';
        } elseif (in_array($roleKey = str_replace([' ', '-'], '_', $lower), ['driver', 'loader', 'carry_boy', 'manual_labourer', 'security', 'staff'], true)) {
            $requested = 'employee_self_service';
        }
        return ['key' => $requested] + ($dashMap[$requested] ?? $dashMap['beverage_warehouse']);
    }

    public static function permissionsForUser(array $user): array
    {
        $roles = [];
        $permissions = [];
        $rawRole = trim((string)($user['role'] ?? ''));
        if ($rawRole !== '') {
            $roles[] = $rawRole;
        }

        if (function_exists('canonical_role')) {
            $canonicalRole = (string)\canonical_role($rawRole);
            if ($canonicalRole !== '' && $canonicalRole !== $rawRole) {
                $roles[] = $canonicalRole;
            }
        }

        $email = strtolower(trim((string)($user['email'] ?? '')));
        if ($email !== '') {
            foreach (self::getEmployees() as $employee) {
                if (strtolower((string)($employee['email'] ?? '')) === $email) {
                    $roles[] = (string)($employee['employee_role'] ?? '');
                    foreach ((array)($employee['permissions'] ?? []) as $permission) {
                        $permissions[] = (string)$permission;
                    }
                    break;
                }
            }
        }

        foreach (array_filter($roles) as $role) {
            $permissions = array_merge($permissions, self::permissionsForRole($role));
        }

        return array_values(array_unique($permissions));
    }

    public static function userHasPermission(array $user, string $permission): bool
    {
        $permissions = self::permissionsForUser($user);
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function permissionForDashboardAction(string $action, array $payload = []): ?string
    {
        if ($action === 'update_payroll_status') {
            $status = trim((string)($payload['payroll_status'] ?? ''));
            return in_array($status, ['Payment Processing', 'Paid'], true) ? 'payroll.pay' : 'payroll.approve';
        }

        $map = [
            'geo_clock_in' => 'attendance.clock',
            'geo_clock_out' => 'attendance.clock',
            'mark_hr_duty' => 'attendance.manage',
            'add_employee' => 'employee.create',
            'save_employee_kyc' => 'employee.kyc.verify',
            'upload_employee_document' => 'employee.edit',
            'create_damage_report' => 'damage.create',
            'update_damage_status' => 'damage.approve',
            'create_leave_record' => 'attendance.manage',
            'create_performance_review' => 'employee.edit',
            'create_disciplinary_record' => 'employee.suspend',
            'save_hr_settings' => 'hr.settings.manage',
            'save_employee_role' => 'hr.settings.manage',
            'save_work_calendar_day' => 'attendance.manage',
            'toggle_staff_access' => 'employee.edit',
            'process_payroll' => 'payroll.process',
            'export_hr_report' => 'reports.employee',
        ];

        return $map[$action] ?? null;
    }

    public static function recordAccessDenied(string $action, array $user): void
    {
        self::logAudit('access_control', $action, 'Access Denied', [], [
            'action' => $action,
            'user_email' => $user['email'] ?? '',
            'role' => $user['role'] ?? '',
        ], (string)($user['name'] ?? $user['email'] ?? 'Unknown User'));
    }

    private static function permissionsForRole(string $role): array
    {
        $roleKey = self::roleKey($role);
        foreach (self::getRoles() as $definition) {
            if ((string)($definition['role_key'] ?? '') === $roleKey && !empty($definition['is_active'])) {
                return (array)($definition['permissions'] ?? []);
            }
        }
        return ['attendance.clock'];
    }

    private static function roleByKey(string $roleKey): ?array
    {
        $roleKey = self::roleKey($roleKey);
        foreach (self::getRoles() as $role) {
            if ((string)($role['role_key'] ?? '') === $roleKey) {
                return $role;
            }
        }
        return null;
    }

    private static function roleKey(string $role): string
    {
        $role = strtolower(trim($role));
        $role = preg_replace('/[^a-z0-9]+/', '_', $role) ?: '';
        return trim($role, '_');
    }

    private static function sanitizePermissions(array $permissions): array
    {
        $allowed = array_keys(self::permissionCatalog());
        $clean = [];
        foreach ($permissions as $permission) {
            $permission = trim((string)$permission);
            if ($permission === '*' || in_array($permission, $allowed, true)) {
                $clean[] = $permission;
            }
        }
        return array_values(array_unique($clean));
    }

    private static function defaultRoleDefinitions(): array
    {
        $definitions = [
            'super_admin' => ['name' => 'Super Admin', 'permissions' => ['*']],
            'admin' => ['name' => 'Admin', 'permissions' => ['employee.view', 'employee.create', 'employee.edit', 'employee.suspend', 'employee.kyc.view', 'employee.kyc.verify', 'attendance.clock', 'attendance.manage', 'payroll.view', 'payroll.process', 'payroll.approve', 'payroll.pay', 'damage.create', 'damage.approve', 'reports.employee', 'hr.settings.manage', 'finance.manage', 'payment_recon.manage']],
            'manager' => ['name' => 'Manager', 'permissions' => ['employee.view', 'employee.kyc.view', 'attendance.clock', 'attendance.manage', 'payroll.view', 'payroll.approve', 'damage.create', 'damage.approve', 'reports.employee']],
            'hr_manager' => ['name' => 'HR Manager', 'permissions' => ['employee.view', 'employee.create', 'employee.edit', 'employee.suspend', 'employee.kyc.view', 'employee.kyc.verify', 'attendance.clock', 'attendance.manage', 'payroll.view', 'payroll.process', 'payroll.approve', 'damage.create', 'damage.approve', 'reports.employee', 'hr.settings.manage']],
            'accountant' => ['name' => 'Accountant', 'permissions' => ['attendance.clock', 'payroll.view', 'payroll.process', 'payroll.pay', 'reports.employee', 'finance.manage', 'payment_recon.manage']],
            'cashier' => ['name' => 'Cashier', 'permissions' => ['pos.login', 'attendance.clock', 'cashdrawer.open', 'cashdrawer.close']],
            'pos_operator' => ['name' => 'POS Operator', 'permissions' => ['pos.login', 'attendance.clock', 'cashdrawer.open', 'cashdrawer.close']],
            'warehouse_manager' => ['name' => 'Warehouse Manager', 'permissions' => ['attendance.clock', 'attendance.manage', 'damage.create', 'damage.approve', 'reports.employee']],
            'storekeeper' => ['name' => 'Storekeeper', 'permissions' => ['attendance.clock', 'damage.create']],
            'driver' => ['name' => 'Driver', 'permissions' => ['attendance.clock', 'damage.create']],
            'loader' => ['name' => 'Loader', 'permissions' => ['attendance.clock']],
            'carry_boy' => ['name' => 'Carry Boy', 'permissions' => ['attendance.clock']],
            'manual_labourer' => ['name' => 'Manual Labourer', 'permissions' => ['attendance.clock']],
            'procurement_officer' => ['name' => 'Procurement Officer', 'permissions' => ['attendance.clock', 'reports.employee']],
            'salesperson' => ['name' => 'Salesperson', 'permissions' => ['attendance.clock', 'reports.employee']],
            'security' => ['name' => 'Security', 'permissions' => ['attendance.clock', 'attendance.manage']],
            'supervisor' => ['name' => 'Supervisor', 'permissions' => ['employee.view', 'attendance.clock', 'attendance.manage', 'damage.create', 'damage.approve', 'reports.employee']],
        ];

        $roles = [];
        foreach ($definitions as $key => $definition) {
            $roles[$key] = [
                'role_key' => $key,
                'role_name' => $definition['name'],
                'permissions' => $definition['permissions'],
                'is_active' => true,
                'updated_at' => '',
                'is_system' => true,
            ];
        }
        return $roles;
    }

    private static function calculateOnboardingProgress(array $employee): array
    {
        $profileFields = ['full_name', 'phone', 'email', 'department', 'job_title', 'assigned_location'];
        $profileDone = count(array_filter($profileFields, static fn(string $key): bool => trim((string)($employee[$key] ?? '')) !== ''));
        $profilePct = (int)round(($profileDone / count($profileFields)) * 100);
        $payrollDone = ((float)($employee['base_salary'] ?? 0) > 0 && trim((string)($employee['bank_name'] ?? '')) !== '') ? 100 : 50;
        return [
            'profile' => $profilePct,
            'documents' => (int)($employee['documents_progress'] ?? 0),
            'kyc' => (string)($employee['kyc_status'] ?? 'Not Submitted'),
            'payroll' => $payrollDone,
            'role_assigned' => trim((string)($employee['employee_role'] ?? '')) !== '',
        ];
    }

    private static function resolveOnboardingStage(array $data, ?array $existing): string
    {
        $stage = trim((string)($data['onboarding_stage'] ?? $existing['onboarding_stage'] ?? 'Profile'));
        return $stage !== '' ? $stage : 'Profile';
    }

    private static function updateEmployeeKycStatus(string $employeeCode, string $status): void
    {
        $employee = self::getEmployeeByCode($employeeCode);
        if (!$employee) {
            return;
        }
        $employee['kyc_status'] = $status;
        $employee['onboarding_progress'] = self::calculateOnboardingProgress($employee);
        self::persistEmployee($employee);
        self::upsertSessionEmployee($employee);
    }

    private static function attendanceDeductionForEmployee(string $employeeCode, string $month, float $baseSalary): float
    {
        $logs = GeoAttendanceService::getAttendanceLogs();
        $lateCount = count(array_filter($logs, static fn(array $log): bool => (string)($log['employee_code'] ?? '') === $employeeCode && str_starts_with((string)($log['clock_in'] ?? ''), $month) && stripos((string)($log['lateness'] ?? ''), 'late') !== false));
        return round(($baseSalary / 26) * 0.10 * $lateCount, 2);
    }

    private static function approvedLiabilityDeductions(string $month): array
    {
        $deductions = [];
        foreach (self::getDamageReports() as $report) {
            if (!in_array((string)($report['investigation_status'] ?? ''), ['Approved', 'Deduction Scheduled'], true)) {
                continue;
            }
            $employeeCode = (string)($report['employee_code'] ?? '');
            $installments = max(1, (int)($report['installments'] ?? 1));
            $amount = ((float)($report['deduction_amount'] ?? 0)) / $installments;
            $deductions[$employeeCode] = ($deductions[$employeeCode] ?? 0) + $amount;
        }
        return $deductions;
    }

    private static function persistPayrollPeriod(array $period): void
    {
        self::replaceSessionPayrollPeriod($period);
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            $pdo->prepare('DELETE FROM payroll_periods WHERE company_id = "beverage" AND period_id = ?')->execute([$period['period_id']]);
            $stmt = $pdo->prepare('INSERT INTO payroll_periods (period_id, company_id, payroll_month, status, total_gross, total_deductions, total_net, payroll_json, created_by, created_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$period['period_id'], $period['month'], $period['status'], $period['total_gross_payroll'], $period['total_deductions'], $period['total_net_payroll'], json_encode($period, JSON_UNESCAPED_SLASHES), $period['created_by'], $period['created_at']]);
        } catch (\Throwable $t) {
        }
    }

    private static function replaceSessionPayrollPeriod(array $period): void
    {
        $_SESSION['payroll_periods'] = $_SESSION['payroll_periods'] ?? [];
        $updated = false;
        foreach ($_SESSION['payroll_periods'] as &$candidate) {
            if ((string)($candidate['period_id'] ?? '') === (string)($period['period_id'] ?? '')) {
                $candidate = $period;
                $updated = true;
                break;
            }
        }
        unset($candidate);
        if (!$updated) {
            array_unshift($_SESSION['payroll_periods'], $period);
        }
    }

    private static function persistDamageReport(array $incident, bool $replaceExisting = false): void
    {
        $_SESSION['damage_reports'] = $_SESSION['damage_reports'] ?? [];
        if ($replaceExisting) {
            $_SESSION['damage_reports'] = array_values(array_filter($_SESSION['damage_reports'], static fn(array $report): bool => (string)($report['incident_number'] ?? '') !== (string)($incident['incident_number'] ?? '')));
        }
        array_unshift($_SESSION['damage_reports'], $incident);
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            if ($replaceExisting) {
                $pdo->prepare('DELETE FROM damage_reports WHERE company_id = "beverage" AND incident_number = ?')->execute([$incident['incident_number']]);
            }
            $stmt = $pdo->prepare('INSERT INTO damage_reports (incident_number, company_id, employee_code, incident_at, location, department, asset, description, estimated_loss, investigation_status, approved_liability, deduction_amount, deduction_method, report_json, created_by, created_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$incident['incident_number'], $incident['employee_code'], $incident['incident_at'], $incident['location'], $incident['department'], $incident['asset'], $incident['description'], $incident['estimated_loss'], $incident['investigation_status'], $incident['approved_liability'], $incident['deduction_amount'], $incident['deduction_method'], json_encode($incident, JSON_UNESCAPED_SLASHES), $incident['created_by'], $incident['created_at']]);
        } catch (\Throwable $t) {
        }
    }

    private static function persistLeaveRecord(array $record): void
    {
        $_SESSION['employee_leave_records'] = $_SESSION['employee_leave_records'] ?? [];
        array_unshift($_SESSION['employee_leave_records'], $record);

        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO employee_leave (leave_id, company_id, employee_code, leave_type, start_date, end_date, status, approved_by, record_json, created_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$record['leave_id'], $record['employee_code'], $record['leave_type'], $record['start_date'], $record['end_date'], $record['status'], $record['approved_by'], json_encode($record, JSON_UNESCAPED_SLASHES), $record['created_at']]);
        } catch (\Throwable $t) {
        }
    }

    private static function persistPerformanceReview(array $review): void
    {
        $_SESSION['employee_performance_records'] = $_SESSION['employee_performance_records'] ?? [];
        array_unshift($_SESSION['employee_performance_records'], $review);

        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO employee_performance (performance_id, company_id, employee_code, period_label, score, review_json, created_by, created_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$review['performance_id'], $review['employee_code'], $review['period_label'], $review['score'], json_encode($review, JSON_UNESCAPED_SLASHES), $review['reviewed_by'], $review['created_at']]);
        } catch (\Throwable $t) {
        }
    }

    private static function persistDisciplinaryRecord(array $record): void
    {
        $_SESSION['employee_disciplinary_records'] = $_SESSION['employee_disciplinary_records'] ?? [];
        array_unshift($_SESSION['employee_disciplinary_records'], $record);

        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO employee_disciplinary_records (disciplinary_id, company_id, employee_code, action_type, reason, status, reviewed_by, approved_by, evidence_ref, record_json, created_by, created_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$record['disciplinary_id'], $record['employee_code'], $record['action_type'], $record['reason'], $record['status'], $record['reviewed_by'], $record['approved_by'], $record['evidence_ref'], json_encode($record, JSON_UNESCAPED_SLASHES), $record['created_by'], $record['created_at']]);
        } catch (\Throwable $t) {
        }
    }

    private static function persistCashierSession(array $session, bool $replaceExisting = false): void
    {
        $_SESSION['cashier_sessions'] = $_SESSION['cashier_sessions'] ?? [];
        if ($replaceExisting) {
            $_SESSION['cashier_sessions'] = array_values(array_filter($_SESSION['cashier_sessions'], static fn(array $candidate): bool => (string)($candidate['drawer_id'] ?? '') !== (string)($session['drawer_id'] ?? '')));
        }
        array_unshift($_SESSION['cashier_sessions'], $session);

        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            if ($replaceExisting) {
                $pdo->prepare('DELETE FROM cashier_sessions WHERE company_id = "beverage" AND drawer_id = ?')->execute([$session['drawer_id']]);
            }
            $stmt = $pdo->prepare('INSERT INTO cashier_sessions (drawer_id, company_id, employee_code, terminal_name, opening_cash, cash_sales, expected_cash, counted_cash, variance, variance_reason, status, session_json, opened_at, closed_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $session['drawer_id'],
                $session['employee_code'],
                $session['terminal_name'],
                $session['opening_cash'],
                $session['cash_sales'],
                $session['expected_cash'],
                $session['counted_cash'],
                $session['variance'],
                $session['variance_reason'],
                $session['status'],
                json_encode($session, JSON_UNESCAPED_SLASHES),
                $session['opened_at'],
                $session['closed_at'],
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function persistWorkCalendarDay(array $record): void
    {
        $_SESSION['work_calendar_days'] = array_values(array_filter($_SESSION['work_calendar_days'] ?? [], static fn(array $day): bool => (string)($day['calendar_date'] ?? '') !== (string)($record['calendar_date'] ?? '')));
        array_unshift($_SESSION['work_calendar_days'], $record);

        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO work_calendars (company_id, calendar_date, day_type, description, created_by, created_at) VALUES ("beverage", ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE day_type=VALUES(day_type), description=VALUES(description), created_by=VALUES(created_by), created_at=VALUES(created_at)');
            $stmt->execute([$record['calendar_date'], $record['day_type'], $record['description'], $record['created_by'], $record['created_at']]);
        } catch (\Throwable $t) {
        }
    }

    private static function logAudit(string $entityType, string $entityId, string $action, array $old, array $new, string $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $entry = [
            'company_id' => 'beverage',
            'user_name' => $user,
            'employee_code' => $new['employee_code'] ?? $old['employee_code'] ?? null,
            'module' => 'HR',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value_json' => json_encode($old, JSON_UNESCAPED_SLASHES),
            'new_value_json' => json_encode($new, JSON_UNESCAPED_SLASHES),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $_SESSION['employee_audit_logs'] = $_SESSION['employee_audit_logs'] ?? [];
        array_unshift($_SESSION['employee_audit_logs'], $entry);

        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            self::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO employee_audit_logs (company_id, user_name, employee_code, module, action, entity_type, entity_id, old_value_json, new_value_json, ip_address, device_info, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute(array_values($entry));
        } catch (\Throwable $t) {
        }
    }

    private static function redact(array $data): array
    {
        foreach (self::SENSITIVE_KEYS as $key) {
            if (isset($data[$key])) {
                $data[$key] = self::maskSensitive((string)$data[$key]);
            }
        }
        return $data;
    }

    public static function ensureTables(?\PDO $pdo = null): void
    {
        $pdo = $pdo ?: Database::getConnection();
        if (!$pdo) {
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_code VARCHAR(50) NOT NULL UNIQUE,
            company_id VARCHAR(50) NOT NULL DEFAULT "beverage",
            user_id INT DEFAULT NULL,
            full_name VARCHAR(160) NOT NULL,
            photo_path TEXT DEFAULT NULL,
            gender VARCHAR(30) DEFAULT NULL,
            date_of_birth DATE DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(160) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            state_of_origin VARCHAR(100) DEFAULT NULL,
            lga VARCHAR(100) DEFAULT NULL,
            emergency_contact VARCHAR(160) DEFAULT NULL,
            next_of_kin VARCHAR(160) DEFAULT NULL,
            employment_date DATE DEFAULT NULL,
            job_title VARCHAR(120) DEFAULT NULL,
            department VARCHAR(120) DEFAULT NULL,
            assigned_location VARCHAR(120) DEFAULT NULL,
            employment_status VARCHAR(40) NOT NULL DEFAULT "Applicant",
            employee_role VARCHAR(80) NOT NULL DEFAULT "Staff",
            kyc_status VARCHAR(50) NOT NULL DEFAULT "Not Submitted",
            base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            allowances DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            bank_name VARCHAR(120) DEFAULT NULL,
            account_number_masked VARCHAR(40) DEFAULT NULL,
            account_name VARCHAR(160) DEFAULT NULL,
            profile_json LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_employees_company_status (company_id, employment_status),
            INDEX idx_employees_dept_role (company_id, department, employee_role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        foreach ([
            'employee_documents (id INT AUTO_INCREMENT PRIMARY KEY, document_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, document_type VARCHAR(100) NOT NULL, storage_ref TEXT DEFAULT NULL, status VARCHAR(50) NOT NULL DEFAULT "Pending", uploaded_by VARCHAR(120) DEFAULT NULL, uploaded_at DATETIME NOT NULL, INDEX idx_employee_documents (company_id, employee_code))',
            'employee_kyc (id INT AUTO_INCREMENT PRIMARY KEY, kyc_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, nin_masked VARCHAR(40) DEFAULT NULL, bvn_masked VARCHAR(40) DEFAULT NULL, provider VARCHAR(120) DEFAULT NULL, reference VARCHAR(160) DEFAULT NULL, status VARCHAR(50) NOT NULL DEFAULT "Pending", verification_json LONGTEXT DEFAULT NULL, updated_by VARCHAR(120) DEFAULT NULL, updated_at DATETIME NOT NULL, INDEX idx_employee_kyc (company_id, employee_code, status))',
            'employee_roles (id INT AUTO_INCREMENT PRIMARY KEY, role_key VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", role_name VARCHAR(120) NOT NULL, permissions_json LONGTEXT NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL)',
            'attendance_records (id INT AUTO_INCREMENT PRIMARY KEY, attendance_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, employee_name VARCHAR(160) NOT NULL, attendance_date DATE NOT NULL, clock_in DATETIME DEFAULT NULL, clock_out DATETIME DEFAULT NULL, branch_name VARCHAR(160) DEFAULT NULL, device_id VARCHAR(120) DEFAULT NULL, attendance_method VARCHAR(50) DEFAULT NULL, ip_address VARCHAR(80) DEFAULT NULL, status VARCHAR(80) DEFAULT NULL, lateness VARCHAR(80) DEFAULT NULL, duration_minutes INT NOT NULL DEFAULT 0, record_json LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL, UNIQUE KEY uniq_attendance_day (company_id, employee_code, attendance_date), INDEX idx_attendance_date (company_id, attendance_date, status))',
            'work_calendars (id INT AUTO_INCREMENT PRIMARY KEY, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", calendar_date DATE NOT NULL, day_type VARCHAR(50) NOT NULL DEFAULT "Working Day", description VARCHAR(180) DEFAULT NULL, created_by VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE KEY uniq_work_calendar (company_id, calendar_date))',
            'employee_leave (id INT AUTO_INCREMENT PRIMARY KEY, leave_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, leave_type VARCHAR(80) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(50) NOT NULL DEFAULT "Pending", approved_by VARCHAR(120) DEFAULT NULL, record_json LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_employee_leave (company_id, employee_code, status))',
            'payroll_periods (id INT AUTO_INCREMENT PRIMARY KEY, period_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", payroll_month VARCHAR(7) NOT NULL, status VARCHAR(60) NOT NULL DEFAULT "Draft", total_gross DECIMAL(12,2) NOT NULL DEFAULT 0.00, total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00, total_net DECIMAL(12,2) NOT NULL DEFAULT 0.00, payroll_json LONGTEXT NOT NULL, created_by VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_payroll_period (company_id, payroll_month, status))',
            'payroll_deductions (id INT AUTO_INCREMENT PRIMARY KEY, deduction_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, source_type VARCHAR(80) DEFAULT NULL, source_ref VARCHAR(100) DEFAULT NULL, amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, balance DECIMAL(12,2) NOT NULL DEFAULT 0.00, status VARCHAR(50) NOT NULL DEFAULT "Scheduled", created_at DATETIME NOT NULL, INDEX idx_payroll_deductions (company_id, employee_code, status))',
            'damage_reports (id INT AUTO_INCREMENT PRIMARY KEY, incident_number VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, incident_at DATETIME NOT NULL, location VARCHAR(160) DEFAULT NULL, department VARCHAR(120) DEFAULT NULL, asset VARCHAR(160) DEFAULT NULL, description TEXT DEFAULT NULL, estimated_loss DECIMAL(12,2) NOT NULL DEFAULT 0.00, investigation_status VARCHAR(80) NOT NULL DEFAULT "Reported", approved_liability DECIMAL(12,2) NOT NULL DEFAULT 0.00, deduction_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, deduction_method VARCHAR(80) DEFAULT NULL, report_json LONGTEXT DEFAULT NULL, created_by VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_damage_reports (company_id, employee_code, investigation_status))',
            'employee_performance (id INT AUTO_INCREMENT PRIMARY KEY, performance_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, period_label VARCHAR(40) NOT NULL, score DECIMAL(6,2) NOT NULL DEFAULT 0.00, review_json LONGTEXT DEFAULT NULL, created_by VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_employee_performance (company_id, employee_code, period_label))',
            'employee_disciplinary_records (id INT AUTO_INCREMENT PRIMARY KEY, disciplinary_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, action_type VARCHAR(80) NOT NULL, reason TEXT DEFAULT NULL, status VARCHAR(60) NOT NULL DEFAULT "Created", reviewed_by VARCHAR(120) DEFAULT NULL, approved_by VARCHAR(120) DEFAULT NULL, evidence_ref TEXT DEFAULT NULL, record_json LONGTEXT DEFAULT NULL, created_by VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_employee_disciplinary (company_id, employee_code, status))',
            'employee_pos_sessions (id INT AUTO_INCREMENT PRIMARY KEY, session_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, terminal_name VARCHAR(120) DEFAULT NULL, login_at DATETIME NOT NULL, logout_at DATETIME DEFAULT NULL, session_json LONGTEXT DEFAULT NULL, INDEX idx_employee_pos_sessions (company_id, employee_code, login_at))',
            'cashier_sessions (id INT AUTO_INCREMENT PRIMARY KEY, drawer_id VARCHAR(80) NOT NULL UNIQUE, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", employee_code VARCHAR(50) NOT NULL, terminal_name VARCHAR(120) DEFAULT NULL, opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00, cash_sales DECIMAL(12,2) NOT NULL DEFAULT 0.00, expected_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00, counted_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00, variance DECIMAL(12,2) NOT NULL DEFAULT 0.00, variance_reason TEXT DEFAULT NULL, status VARCHAR(50) NOT NULL DEFAULT "Open", session_json LONGTEXT DEFAULT NULL, opened_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, INDEX idx_cashier_sessions (company_id, employee_code, status))',
            'employee_audit_logs (id INT AUTO_INCREMENT PRIMARY KEY, company_id VARCHAR(50) NOT NULL DEFAULT "beverage", user_name VARCHAR(120) DEFAULT NULL, employee_code VARCHAR(50) DEFAULT NULL, module VARCHAR(80) NOT NULL, action VARCHAR(160) NOT NULL, entity_type VARCHAR(80) DEFAULT NULL, entity_id VARCHAR(120) DEFAULT NULL, old_value_json LONGTEXT DEFAULT NULL, new_value_json LONGTEXT DEFAULT NULL, ip_address VARCHAR(80) DEFAULT NULL, device_info TEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_employee_audit (company_id, employee_code, created_at))',
            'hr_settings (company_id VARCHAR(50) PRIMARY KEY, setting_json LONGTEXT NOT NULL, updated_by VARCHAR(120) DEFAULT NULL, updated_at DATETIME NOT NULL)',
        ] as $tableSql) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $tableSql . ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }
}
