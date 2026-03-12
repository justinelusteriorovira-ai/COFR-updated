<?php
/**
 * CSRF Token Protection Helper
 * Include this file anywhere you need CSRF protection.
 */

/**
 * Generate a CSRF token and store it in the session.
 * Returns the token string for embedding in forms.
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input field containing the CSRF token.
 * Call this inside any <form> tag.
 */
function csrfField() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate a submitted CSRF token against the session token.
 * Regenerates the token after validation for one-time use.
 *
 * @param string $token The submitted token from $_POST['csrf_token']
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if ($valid) {
        // Regenerate token after successful validation
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $valid;
}

/**
 * Validate CSRF or die with 403.
 * Convenience wrapper for POST handlers.
 */
function requireCSRF() {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        http_response_code(403);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}
?>
