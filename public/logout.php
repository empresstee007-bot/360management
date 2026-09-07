<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

// Clear all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Start fresh session for flash message
session_start();
flash("👋 You have been successfully signed out. Please enter your credentials to log back in.");

// Redirect directly to Sign In page
redirect('login.php');
