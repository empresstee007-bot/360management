<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll;

use App\Core\Database;

class GeoAttendanceService
{
    private static array $branchesGeoMock = [
        'beverage' => ['name' => 'Jacroxx Warehouse (Main)', 'lat' => 6.4281, 'lng' => 3.4219, 'radius' => 300],
        'jacroxx' => ['name' => 'Jacroxx Warehouse (Main)', 'lat' => 6.4281, 'lng' => 3.4219, 'radius' => 300],
        'ijaba' => ['name' => 'Ijaba Warehouse', 'lat' => 6.4420, 'lng' => 3.4110, 'radius' => 300],
    ];

    private static array $attendanceLogsMock = [];

    public static function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return round($earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a))), 2);
    }

    public static function getAttendanceLogs(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                HrPayrollService::ensureTables($pdo);
                $rows = $pdo->query('SELECT record_json FROM attendance_records WHERE company_id = "beverage" ORDER BY attendance_date DESC, id DESC LIMIT 300')->fetchAll() ?: [];
                if ($rows) {
                    $logs = array_values(array_filter(array_map(static fn(array $row): ?array => json_decode((string)$row['record_json'], true) ?: null, $rows)));
                    $_SESSION['attendance_logs'] = $logs;
                    return $logs;
                }
            } catch (\Throwable $t) {
            }
        }

        return $_SESSION['attendance_logs'] ?? self::$attendanceLogsMock;
    }

    public static function clockIn(array $data, string $companyId = 'beverage'): array
    {
        return self::recordAttendance($data + ['attendance_action' => 'clock_in'], $companyId);
    }

    public static function clockOut(array $data, string $companyId = 'beverage'): array
    {
        return self::recordAttendance($data + ['attendance_action' => 'clock_out'], $companyId);
    }

    public static function recordAttendance(array $data, string $companyId = 'beverage'): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $companyId = 'beverage';
        $action = strtolower(trim((string)($data['attendance_action'] ?? 'clock_in')));
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $employee = $employeeCode !== '' ? HrPayrollService::getEmployeeByCode($employeeCode) : null;
        $employeeName = trim((string)($data['employee_name'] ?? $employee['name'] ?? 'Staff Member'));
        $method = trim((string)($data['attendance_method'] ?? 'pin'));
        $userLat = (float)($data['lat'] ?? 6.4281);
        $userLng = (float)($data['lng'] ?? 3.4219);
        $deviceId = trim((string)($data['device_id'] ?? ('DEV-' . rand(1000, 9999))));
        $branchKey = strtolower(trim((string)($data['branch_key'] ?? 'jacroxx')));
        $branchGeo = self::$branchesGeoMock[$branchKey] ?? self::$branchesGeoMock['jacroxx'];
        $distanceMeters = self::haversineMeters($userLat, $userLng, $branchGeo['lat'], $branchGeo['lng']);
        $isInRadius = $distanceMeters <= (float)$branchGeo['radius'];
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $settings = HrPayrollService::getSettings();
        $openingTime = (string)($settings['opening_time'] ?? '08:00');
        $graceMinutes = (int)($settings['grace_minutes'] ?? 15);
        $dayClass = HrPayrollService::classifyWorkDate($today);
        $lateness = self::latenessStatus($today, $openingTime, $graceMinutes, $now);

        $existing = self::findTodayRecord($employeeCode, $today);
        if ($action === 'clock_out' && $existing) {
            $record = $existing;
            $record['clock_out'] = $now;
            $record['status'] = 'Clocked Out';
            $record['duration_minutes'] = self::durationMinutes((string)$record['clock_in'], $now);
            $record['updated_at'] = $now;
        } else {
            $record = [
                'id' => time(),
                'attendance_id' => 'ATT-' . date('YmdHis') . '-' . rand(10, 99),
                'employee_name' => $employeeName,
                'employee_code' => $employeeCode !== '' ? $employeeCode : 'UNASSIGNED',
                'department' => $employee['department'] ?? trim((string)($data['department'] ?? '')),
                'employee_role' => $employee['employee_role'] ?? trim((string)($data['employee_role'] ?? '')),
                'branch_name' => $branchGeo['name'],
                'attendance_date' => $today,
                'calendar_day_type' => $dayClass['day_type'] ?? 'Working Day',
                'calendar_day_description' => $dayClass['description'] ?? '',
                'clock_in' => $now,
                'clock_out' => null,
                'lat' => $userLat,
                'lng' => $userLng,
                'distance_meters' => $distanceMeters,
                'device_id' => $deviceId,
                'attendance_method' => $method,
                'status' => empty($dayClass['is_working_day']) ? 'Working - Non-working day' : ($isInRadius ? 'Working' : "Out of Geo-Fence Review"),
                'geo_status' => $isInRadius ? 'In Radius (Verified GPS)' : "Out of Geo-Fence Flag ({$distanceMeters}m away from {$branchGeo['name']} - Max {$branchGeo['radius']}m)",
                'lateness' => $lateness,
                'duration_minutes' => 0,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'updated_at' => $now,
            ];
        }

        self::persistRecord($record);
        self::upsertSessionRecord($record);
        self::logAttendanceAudit($record, $action === 'clock_out' ? 'Clock Out' : 'Clock In');
        return $record;
    }

    public static function markDuty(array $data): array
    {
        $employeeCode = strtoupper(trim((string)($data['employee_code'] ?? '')));
        $employee = HrPayrollService::getEmployeeByCode($employeeCode);
        $today = trim((string)($data['attendance_date'] ?? date('Y-m-d')));
        $dayClass = HrPayrollService::classifyWorkDate($today);
        $record = [
            'id' => time(),
            'attendance_id' => 'DUTY-' . date('YmdHis') . '-' . rand(10, 99),
            'employee_name' => $employee['name'] ?? trim((string)($data['employee_name'] ?? 'Staff Member')),
            'employee_code' => $employeeCode,
            'department' => $employee['department'] ?? '',
            'employee_role' => $employee['employee_role'] ?? '',
            'branch_name' => trim((string)($data['branch_name'] ?? $employee['branch'] ?? 'Field')),
            'attendance_date' => $today,
            'calendar_day_type' => $dayClass['day_type'] ?? 'Working Day',
            'calendar_day_description' => $dayClass['description'] ?? '',
            'clock_in' => null,
            'clock_out' => null,
            'distance_meters' => 0,
            'device_id' => trim((string)($data['device_id'] ?? 'Manual HR')),
            'attendance_method' => 'supervisor_mark',
            'status' => trim((string)($data['duty_status'] ?? 'Official Duty')),
            'geo_status' => 'Supervisor Approved',
            'lateness' => 'Excused',
            'duration_minutes' => 0,
            'reason' => trim((string)($data['reason'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        self::persistRecord($record);
        self::upsertSessionRecord($record);
        self::logAttendanceAudit($record, 'Mark Duty / Approved Absence');
        return $record;
    }

    private static function latenessStatus(string $date, string $openingTime, int $graceMinutes, string $now): string
    {
        $open = strtotime($date . ' ' . $openingTime);
        $clock = strtotime($now);
        if ($open === false || $clock === false) {
            return 'On Time';
        }
        if ($clock <= $open) {
            return 'On Time';
        }
        if ($clock <= strtotime("+{$graceMinutes} minutes", $open)) {
            return 'On Time / Grace Period';
        }
        return 'Late Arrival';
    }

    private static function durationMinutes(string $clockIn, string $clockOut): int
    {
        $in = strtotime($clockIn);
        $out = strtotime($clockOut);
        if ($in === false || $out === false || $out < $in) {
            return 0;
        }
        return (int)floor(($out - $in) / 60);
    }

    private static function findTodayRecord(string $employeeCode, string $date): ?array
    {
        foreach (self::getAttendanceLogs() as $record) {
            if ((string)($record['employee_code'] ?? '') === $employeeCode && (string)($record['attendance_date'] ?? substr((string)($record['clock_in'] ?? ''), 0, 10)) === $date) {
                return $record;
            }
        }
        return null;
    }

    private static function persistRecord(array $record): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            HrPayrollService::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO attendance_records (attendance_id, company_id, employee_code, employee_name, attendance_date, clock_in, clock_out, branch_name, device_id, attendance_method, ip_address, status, lateness, duration_minutes, record_json, updated_at) VALUES (?, "beverage", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE clock_out=VALUES(clock_out), status=VALUES(status), lateness=VALUES(lateness), duration_minutes=VALUES(duration_minutes), record_json=VALUES(record_json), updated_at=VALUES(updated_at)');
            $stmt->execute([
                $record['attendance_id'], $record['employee_code'], $record['employee_name'], $record['attendance_date'], $record['clock_in'], $record['clock_out'], $record['branch_name'], $record['device_id'], $record['attendance_method'], $record['ip_address'] ?? '', $record['status'], $record['lateness'], (int)$record['duration_minutes'], json_encode($record, JSON_UNESCAPED_SLASHES), $record['updated_at'],
            ]);
        } catch (\Throwable $t) {
        }
    }

    private static function upsertSessionRecord(array $record): void
    {
        $_SESSION['attendance_logs'] = $_SESSION['attendance_logs'] ?? [];
        $updated = false;
        foreach ($_SESSION['attendance_logs'] as &$candidate) {
            if ((string)($candidate['employee_code'] ?? '') === (string)$record['employee_code'] && (string)($candidate['attendance_date'] ?? '') === (string)$record['attendance_date']) {
                $candidate = $record;
                $updated = true;
                break;
            }
        }
        unset($candidate);
        if (!$updated) {
            array_unshift($_SESSION['attendance_logs'], $record);
        }
    }

    private static function logAttendanceAudit(array $record, string $action): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return;
        }
        try {
            HrPayrollService::ensureTables($pdo);
            $stmt = $pdo->prepare('INSERT INTO employee_audit_logs (company_id, user_name, employee_code, module, action, entity_type, entity_id, old_value_json, new_value_json, ip_address, device_info, created_at) VALUES ("beverage", ?, ?, "Attendance", ?, "attendance_record", ?, NULL, ?, ?, ?, ?)');
            $stmt->execute([\current_user()['name'] ?? 'Attendance Terminal', $record['employee_code'], $action, $record['attendance_id'], json_encode($record, JSON_UNESCAPED_SLASHES), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', date('Y-m-d H:i:s')]);
        } catch (\Throwable $t) {
        }
    }
}
