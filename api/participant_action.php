<?php
/**
 * Mining Buddy - Participant Action API
 * 
 * Handles actions for operation participants (kick, ban, promote, leave, end)
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

// Get operation ID and action from request
$operationId = isset($_POST['operation_id']) ? intval($_POST['operation_id']) : null;
$action = isset($_POST['action']) ? trim($_POST['action']) : null;
$targetUserId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

// Verify required parameters
if (!$operationId) {
    echo json_encode([
        'success' => false,
        'message' => 'Operation ID is required'
    ]);
    exit;
}

if (!$action) {
    echo json_encode([
        'success' => false,
        'message' => 'Action is required'
    ]);
    exit;
}

// Verify user is in this operation
if ($user['active_operation_id'] != $operationId) {
    echo json_encode([
        'success' => false,
        'message' => 'You are not in this operation'
    ]);
    exit;
}

try {
    $db = getDbConnection();
    
    // Get operation details
    $stmt = $db->prepare("SELECT * FROM mining_operations WHERE operation_id = ?");
    $stmt->execute([$operationId]);
    $operation = $stmt->fetch();
    
    if (!$operation) {
        echo json_encode([
            'success' => false,
            'message' => 'Operation not found'
        ]);
        exit;
    }
    
    // Check if operation is active
    if ($operation['status'] !== 'active') {
        echo json_encode([
            'success' => false,
            'message' => 'Operation is not active'
        ]);
        exit;
    }
    
    // Get current user's participant record
    $stmt = $db->prepare("
        SELECT * FROM operation_participants
        WHERE operation_id = ? AND user_id = ?
    ");
    $stmt->execute([$operationId, $user['user_id']]);
    $participant = $stmt->fetch();
    
    if (!$participant || $participant['status'] !== 'active') {
        echo json_encode([
            'success' => false,
            'message' => 'You are not an active participant in this operation'
        ]);
        exit;
    }
    
    // Check if user is admin for admin actions
    $isAdmin = $participant['is_admin'] == 1;
    $isDirector = $operation['director_id'] == $user['user_id'];
    
    // Handle different actions
    switch ($action) {
        case 'kick':
            // Kick a participant (admin only)
            if (!$isAdmin) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to kick participants'
                ]);
                exit;
            }
            
            if (!$targetUserId) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Target user ID is required'
                ]);
                exit;
            }
            
            // Get target user's participant record
            $stmt = $db->prepare("
                SELECT * FROM operation_participants
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operationId, $targetUserId]);
            $targetParticipant = $stmt->fetch();
            
            if (!$targetParticipant || $targetParticipant['status'] !== 'active') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Target user is not an active participant'
                ]);
                exit;
            }
            
            // Can't kick another admin unless you're the director
            if ($targetParticipant['is_admin'] && !$isDirector) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You cannot kick another admin'
                ]);
                exit;
            }
            
            // Can't kick yourself
            if ($targetUserId == $user['user_id']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You cannot kick yourself'
                ]);
                exit;
            }
            
            $db->beginTransaction();
            
            // Update participant status
            $stmt = $db->prepare("
                UPDATE operation_participants
                SET status = 'kicked', leave_time = NOW()
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operationId, $targetUserId]);
            
            // Clear user's active operation
            $stmt = $db->prepare("
                UPDATE users
                SET active_operation_id = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$targetUserId]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Participant was kicked from the operation'
            ]);
            
            break;
            
        case 'ban':
            // Ban a participant (admin only)
            if (!$isAdmin) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to ban participants'
                ]);
                exit;
            }
            
            if (!$targetUserId) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Target user ID is required'
                ]);
                exit;
            }
            
            // Get target user's participant record
            $stmt = $db->prepare("
                SELECT * FROM operation_participants
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operationId, $targetUserId]);
            $targetParticipant = $stmt->fetch();
            
            if (!$targetParticipant) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Target user is not a participant'
                ]);
                exit;
            }
            
            // Can't ban another admin unless you're the director
            if ($targetParticipant['is_admin'] && !$isDirector) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You cannot ban another admin'
                ]);
                exit;
            }
            
            // Can't ban yourself
            if ($targetUserId == $user['user_id']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You cannot ban yourself'
                ]);
                exit;
            }
            
            $db->beginTransaction();
            
            // Update participant status
            $stmt = $db->prepare("
                UPDATE operation_participants
                SET status = 'banned', leave_time = NOW()
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operationId, $targetUserId]);
            
            // Clear user's active operation
            $stmt = $db->prepare("
                UPDATE users
                SET active_operation_id = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$targetUserId]);
            
            // Add to banned users
            $stmt = $db->prepare("
                INSERT INTO banned_users (operation_id, user_id, banned_by, banned_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE banned_by = ?, banned_at = NOW()
            ");
            $stmt->execute([$operationId, $targetUserId, $user['user_id'], $user['user_id']]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Participant was banned from the operation'
            ]);
            
            break;
            
        case 'promote':
            // Promote a participant to director (director only)
            if (!$isDirector) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Only the Mining Director can promote participants'
                ]);
                exit;
            }
            
            if (!$targetUserId) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Target user ID is required'
                ]);
                exit;
            }
            
            // Get target user's participant record
            $stmt = $db->prepare("
                SELECT * FROM operation_participants
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operationId, $targetUserId]);
            $targetParticipant = $stmt->fetch();
            
            if (!$targetParticipant || $targetParticipant['status'] !== 'active') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Target user is not an active participant'
                ]);
                exit;
            }
            
            // Can't promote yourself
            if ($targetUserId == $user['user_id']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You are already the director'
                ]);
                exit;
            }
            
            $db->beginTransaction();
            
            // Update operation director
            $stmt = $db->prepare("
                UPDATE mining_operations
                SET director_id = ?
                WHERE operation_id = ?
            ");
            $stmt->execute([$targetUserId, $operationId]);
            
            // Make target user an admin
            $stmt = $db->prepare("
                UPDATE operation_participants
                SET is_admin = 1
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operationId, $targetUserId]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Participant was promoted to Mining Director'
            ]);
            
            break;
            
        case 'leave':
            // Leave the operation (any participant)
            $db->beginTransaction();
            
            // Update participant status
            $stmt = $db->prepare("
                UPDATE operation_participants
                SET status = 'left', leave_time = NOW()
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operationId, $user['user_id']]);
            
            // Clear user's active operation
            $stmt = $db->prepare("
                UPDATE users
                SET active_operation_id = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$user['user_id']]);
            
            // If user was the director, assign a new director
            if ($isDirector) {
                // Find another admin
                $stmt = $db->prepare("
                    SELECT user_id
                    FROM operation_participants
                    WHERE operation_id = ? AND user_id != ? AND is_admin = 1 AND status = 'active'
                    ORDER BY join_time ASC
                    LIMIT 1
                ");
                $stmt->execute([$operationId, $user['user_id']]);
                $newDirector = $stmt->fetch();
                
                if ($newDirector) {
                    // Update operation director
                    $stmt = $db->prepare("
                        UPDATE mining_operations
                        SET director_id = ?
                        WHERE operation_id = ?
                    ");
                    $stmt->execute([$newDirector['user_id'], $operationId]);
                } else {
                    // Find any active participant
                    $stmt = $db->prepare("
                        SELECT user_id
                        FROM operation_participants
                        WHERE operation_id = ? AND user_id != ? AND status = 'active'
                        ORDER BY join_time ASC
                        LIMIT 1
                    ");
                    $stmt->execute([$operationId, $user['user_id']]);
                    $newDirector = $stmt->fetch();
                    
                    if ($newDirector) {
                        // Make them admin and director
                        $stmt = $db->prepare("
                            UPDATE operation_participants
                            SET is_admin = 1
                            WHERE operation_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$operationId, $newDirector['user_id']]);
                        
                        // Update operation director
                        $stmt = $db->prepare("
                            UPDATE mining_operations
                            SET director_id = ?
                            WHERE operation_id = ?
                        ");
                        $stmt->execute([$newDirector['user_id'], $operationId]);
                    } else {
                        // No other participants, end the operation
                        $stmt = $db->prepare("
                            UPDATE mining_operations
                            SET status = 'ended', ended_at = NOW(), termination_type = 'manual'
                            WHERE operation_id = ?
                        ");
                        $stmt->execute([$operationId]);
                    }
                }
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'You have left the operation'
            ]);
            
            break;
            
        case 'end':
            // End the operation (director only)
            if (!$isDirector) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Only the Mining Director can end the operation'
                ]);
                exit;
            }
            
            $db->beginTransaction();
            
            // Update operation status to ending
            $stmt = $db->prepare("
                UPDATE mining_operations
                SET status = 'ending', ended_at = DATE_ADD(NOW(), INTERVAL 5 SECOND)
                WHERE operation_id = ?
            ");
            $stmt->execute([$operationId]);
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Operation is ending'
            ]);
            
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    
    // Log the error
    logMessage('Participant action API error: ' . $e->getMessage(), 'error');
}