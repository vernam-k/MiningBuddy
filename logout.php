<?php
/**
 * Mining Buddy - Logout
 * 
 * Handles user logout by destroying the session
 */

// Include functions file
require_once 'includes/functions.php';

// Initialize the application
initApp();

// Only process logout if user is logged in
if (isLoggedIn()) {
    // Get user ID before destroying session
    $userId = $_SESSION['user_id'];
    
    // Check if user is in an active operation
    $user = getUserById($userId);
    
    if ($user && !empty($user['active_operation_id'])) {
        // Leave any active operation
        try {
            $db = getDbConnection();
            
            // Update participant status to 'left'
            $stmt = $db->prepare("
                UPDATE operation_participants
                SET status = 'left', leave_time = NOW()
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);
            
            // Clear user's active operation
            $stmt = $db->prepare("
                UPDATE users
                SET active_operation_id = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
        } catch (Exception $e) {
            // Log the error but continue with logout
            logMessage('Error leaving operation during logout: ' . $e->getMessage(), 'error');
        }
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Set success message for next page
    setFlashMessage('You have been successfully logged out.', 'success');
}

// Redirect to homepage
redirect('index.php');