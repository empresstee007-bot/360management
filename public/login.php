<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/layout.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/UserSeeder.php';

use App\Core\Database;
use App\Core\UserSeeder;

if (!app_is_live()) {
    UserSeeder::seedAdminUser();
}

$sessionUser = current_user();
if ($sessionUser) {
    redirect(default_dashboard_path($sessionUser));
}

$error = null;
$authenticatedUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $companyName = 'Empress Tee - Beverage Depot ERP';

    $pdo = Database::getConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $dbUser = $stmt->fetch();
            if ($dbUser && password_verify($password, $dbUser['password'])) {
                $authenticatedUser = [
                    'id' => $dbUser['id'],
                    'name' => $dbUser['name'],
                    'email' => $dbUser['email'],
                    'role' => $dbUser['role'],
                ];
            }
        } catch (\Throwable $t) {
            if (app_requires_database()) {
                throw $t;
            }
        }
    } else {
        $error = 'Database is not connected. Please contact the system administrator.';
    }

    if ($authenticatedUser) {
        session_regenerate_id(true);
        $authenticatedUser['company_id'] = 'beverage';
        $authenticatedUser['company_name'] = $companyName;

        $_SESSION['user_id'] = $authenticatedUser['id'];
        $_SESSION['user'] = $authenticatedUser;
        flash("Welcome back, {$authenticatedUser['name']}! Logged into {$authenticatedUser['company_name']}.");
        redirect(default_dashboard_path($authenticatedUser));
    } elseif ($error === null) {
        $error = 'Invalid email address or password.';
    }
}

render_header('Administrator & Staff Login', null);
?>

<div class="login-page">
    <!-- Visual Left Column -->
    <div class="login-visual-col">
        <div class="login-visual-brand">
            <div class="brand-icon logo-brand-icon">
                <img class="app-logo-img" src="<?= e(url('assets/images/empress_tee_logo.svg')) ?>" alt="Empress Tee Beverage Depot">
            </div>
            <strong>EMPRESS <span>TEE</span></strong>
        </div>

        <div class="login-visual-content">
            <h1>Enterprise Admin Login</h1>
            <p>
                Access administrative control over active Beverage Depot operations.
            </p>

        </div>

        <div style="font-size:0.8rem;color:var(--text-light)">
            © <?= date('Y') ?> Empress Tee Beverage Depot ERP — Encrypted MySQL PDO Authentication.
        </div>
    </div>

    <!-- Form Right Column -->
    <div class="login-form-col">
        <div class="login-card">
            <div class="login-card-head">
                <h2>Admin &amp; Staff Login</h2>
                <p>Enter your credentials to access the 360Management Control Center</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= url('login.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="form-group">
                    <label for="loginEmail">Admin Email Address</label>
                    <input id="loginEmail" name="email" type="email" class="form-control" required value="" placeholder="name@company.com" autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="loginPassword" style="display:flex;justify-content:space-between">
                        <span>Password</span>
                    </label>
                    <div style="position:relative">
                        <input id="loginPassword" name="password" type="password" class="form-control" required value="" placeholder="Enter password" autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem;font-weight:800;font-size:1rem;">
                    Sign In as Administrator →
                </button>
            </form>

            </div>
        </div>
</div>

<?php render_footer(false); ?>
