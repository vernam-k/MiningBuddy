<?php
/**
 * Mining Buddy - EVE SSO Callback
 * 
 * Handles the callback from EVE Online SSO authentication
 */

// Include functions file
require_once 'includes/functions.php';

// Initialize the application
initApp();

// Check for errors
if (isset($_GET['error'])) {
    setFlashMessage('Authentication error: ' . htmlspecialchars($_GET['error']), 'error');
    redirect('index.php');
    exit;
}

// Verify state to prevent CSRF attacks
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    setFlashMessage('Invalid state token. Please try again.', 'error');
    redirect('index.php');
    exit;
}

// Clear the state token
unset($_SESSION['oauth_state']);

// Check for authorization code
if (!isset($_GET['code'])) {
    setFlashMessage('No authorization code received. Please try again.', 'error');
    redirect('index.php');
    exit;
}

try {
    // Exchange authorization code for access token
    $tokenData = requestEveAccessToken($_GET['code']);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception("No access token received");
    }
    
    // Calculate token expiration time
    $expiresIn = $tokenData['expires_in'] ?? 1200; // Default to 20 minutes
    $tokenExpires = date('Y-m-d H:i:s', time() + $expiresIn);
    
    // Verify token and get character information
    $verifyData = verifyEveAccessToken($tokenData['access_token']);
    
    if (!isset($verifyData['CharacterID'])) {
        throw new Exception("No character ID received");
    }
    
    $characterId = $verifyData['CharacterID'];
    $characterName = $verifyData['CharacterName'];
    
    // Get character portrait
    $characterPortrait = getCharacterPortrait($characterId, $tokenData['access_token']);
    
    // Get corporation history to find current corporation
    $corporationHistory = getCharacterCorporationHistory($characterId, $tokenData['access_token']);
    
    // The first entry in the history is the current corporation
    if (empty($corporationHistory)) {
        throw new Exception("Failed to get corporation history");
    }
    
    $corporationId = $corporationHistory[0]['corporation_id'];
    
    // Get corporation information
    $corporationInfo = getCorporationInfo($corporationId, $tokenData['access_token']);
    $corporationName = $corporationInfo['name'] ?? 'Unknown Corporation';
    
    // Get corporation logo
    $corporationLogo = getCorporationLogo($corporationId, $tokenData['access_token']);
    
    // Prepare user data
    $userData = [
        'character_id' => $characterId,
        'character_name' => $characterName,
        'avatar_url' => $characterPortrait,
        'corporation_id' => $corporationId,
        'corporation_name' => $corporationName,
        'corporation_logo_url' => $corporationLogo,
        'access_token' => $tokenData['access_token'],
        'refresh_token' => $tokenData['refresh_token'],
        'token_expires' => $tokenExpires
    ];
    
    // Create or update user in database
    $userId = createOrUpdateUser($userData);
    
    // Set session data
    $_SESSION['user_id'] = $userId;
    $_SESSION['character_id'] = $characterId;
    $_SESSION['character_name'] = $characterName;
    $_SESSION['login_time'] = time();
    
    // Check if user has an active operation
    $user = getUserById($userId);
    if ($user && !empty($user['active_operation_id'])) {
        // Redirect to operation page
        setFlashMessage('Welcome back! You have been redirected to your active mining operation.', 'success');
        redirect('operation.php');
    } else {
        // Redirect to dashboard
        setFlashMessage('Successfully logged in as ' . $characterName, 'success');
        
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
    
} catch (Exception $e) {
    // Log the error
    logMessage('Authentication error: ' . $e->getMessage(), 'error');
    
    // Show error message
    setFlashMessage('Authentication error: ' . $e->getMessage(), 'error');
    redirect('index.php');
}