<?php
/**
 * Mining Buddy - Token Refresh API
 * 
 * Handles refreshing EVE API tokens
 */

// Set content type to JSON
header('Content-Type: application/json');

// Include functions file
require_once '../includes/functions.php';

// Initialize the application
initApp();

// Require user to be logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Get current user
$user = getCurrentUser();

// Check if this is just a status check
$isCheck = isset($_GET['check']) && $_GET['check'] == 1;

try {
    if ($isCheck) {
        // Just check token status
        if (!$user) {
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'message' => 'User not found'
            ]);
            exit;
        }
        
        // Calculate seconds until token expires
        $expiresIn = strtotime($user['token_expires']) - time();
        
        if ($expiresIn < 0) {
            // Token has already expired
            echo json_encode([
                'success' => true,
                'status' => 'error',
                'message' => 'Token has expired',
                'expires_in' => $expiresIn
            ]);
        } else if ($expiresIn < 300) {
            // Token will expire soon (less than 5 minutes)
            echo json_encode([
                'success' => true,
                'status' => 'refreshing',
                'message' => 'Token will expire soon',
                'expires_in' => $expiresIn
            ]);
        } else {
            // Token is valid
            echo json_encode([
                'success' => true,
                'status' => 'valid',
                'message' => 'Token is valid',
                'expires_in' => $expiresIn
            ]);
        }
        
        exit;
    } else {
        // Perform token refresh
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : $user['user_id'];
        
        // Only allow refreshing other users' tokens if in same operation
        if ($userId != $user['user_id']) {
            $targetUser = getUserById($userId);
            
            if (!$targetUser || $targetUser['active_operation_id'] != $user['active_operation_id']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You cannot refresh tokens for users not in your operation'
                ]);
                exit;
            }
        }
        
        // Refresh token
        $success = refreshUserTokenIfNeeded($userId);
        
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Token refreshed successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to refresh token'
            ]);
        }
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    
    // Log the error
    logMessage('Token refresh API error: ' . $e->getMessage(), 'error');
}