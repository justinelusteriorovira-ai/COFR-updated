<?php
/**
 * Centralized Session Management
 * Include this file at the top of every admin-facing page.
 * Handles: session start, timeout check, CSRF token init.
 */

// Session configuration
$SESSION_TIMEOUT = 30 * 60; // 30 minutes in seconds

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Skip timeout check for login page
$current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($current_script !== 'login.php' && isset($_SESSION['admin_id'])) {
    // Check for session timeout
    if (isset($_SESSION['last_activity'])) {
        $idle_time = time() - $_SESSION['last_activity'];
        if ($idle_time > $SESSION_TIMEOUT) {
            // Session expired
            session_unset();
            session_destroy();
            // Determine redirect path
            $login_path = '../auth/login.php';
            if (file_exists('auth/login.php')) {
                $login_path = 'auth/login.php';
            }
            header("Location: $login_path?timeout=1");
            exit;
        }
    }
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

// Include CSRF helper (always available after session starts)
$csrf_path = __DIR__ . '/csrf.php';
if (file_exists($csrf_path)) {
    require_once($csrf_path);
}
?>
