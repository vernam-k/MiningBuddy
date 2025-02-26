<?php
/**
 * Mining Buddy - Login
 * 
 * Handles redirecting users to EVE Online SSO
 */

// Include functions file
require_once 'includes/functions.php';

// Initialize the application
initApp();

// Check if user is already logged in
if (isLoggedIn()) {
    // If a redirect URL is set, go there
    if (isset($_SESSION['login_redirect']) && !empty($_SESSION['login_redirect'])) {
        $redirect = $_SESSION['login_redirect'];
        unset($_SESSION['login_redirect']);
        redirect($redirect);
    } else {
        // Otherwise go to dashboard
        redirect('dashboard.php');
    }
}

// Generate a state token to prevent CSRF attacks
$state = generateToken();
$_SESSION['oauth_state'] = $state;

// Build authorization URL
$authUrl = EVE_SSO_AUTH_URL . '?' . http_build_query([
    'response_type' => 'code',
    'redirect_uri' => EVE_CALLBACK_URL,
    'client_id' => EVE_CLIENT_ID,
    'scope' => EVE_SCOPES,
    'state' => $state
]);

// Redirect to EVE SSO
redirect($authUrl);