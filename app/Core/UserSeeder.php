<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

class UserSeeder
{
    public static function seedAdminUser(): array
    {
        $adminPassword = \env_value('SEED_ADMIN_PASSWORD');
        $cashierPassword = \env_value('SEED_CASHIER_PASSWORD');

        if (!$adminPassword || !$cashierPassword) {
            return [];
        }

        $usersToSeed = [
            [
                'id' => 2,
                'name' => 'Beverage Depot Admin',
                'email' => 'beverage_admin@360management.com',
                'password' => $adminPassword,
                'role' => 'admin',
            ],
            [
                'id' => 8,
                'name' => 'Beverage POS Cashier',
                'email' => 'beverage_cashier@360management.com',
                'password' => $cashierPassword,
                'role' => 'pos',
            ],
            [
                'id' => 5,
                'name' => 'Operations Manager',
                'email' => 'manager@360management.com',
                'password' => $adminPassword,
                'role' => 'manager',
            ],
            [
                'id' => 6,
                'name' => 'Finance Director',
                'email' => 'finance@360management.com',
                'password' => $adminPassword,
                'role' => 'finance_manager',
            ],
        ];

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                Database::runMigrations();

                $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password, role, company_id, company_name) VALUES (?, ?, ?, ?, ?, 'beverage', 'Empress Tee - Beverage Depot ERP') ON DUPLICATE KEY UPDATE password=VALUES(password)");
                foreach ($usersToSeed as $u) {
                    $hashedPassword = password_hash($u['password'], PASSWORD_DEFAULT);
                    $stmt->execute([
                        $u['id'],
                        $u['name'],
                        $u['email'],
                        $hashedPassword,
                        $u['role'],
                    ]);
                }
            } catch (\Throwable $t) {}
        }

        return $usersToSeed[0] ?? [];
    }
}
