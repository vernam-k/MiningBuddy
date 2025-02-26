<?php
/**
 * Mining Buddy - Finalize Operations API
 * 
 * This endpoint is used to finalize operations that are in the "ending" state
 * without requiring a user to be in the operation.
 * 
 * It can be called by cron jobs or as a background task from JavaScript.
 */

// Set content type to JSON
header('Content-Type: application/json');

// Include functions file
require_once '../includes/functions.php';

// Initialize the application
initApp();

// Check if request has authorization
// This is a simple check, not full authentication
$authorized = true;

// For development/debug
if (DEBUG_MODE) {
    $authorized = true;
}

if (!$authorized) {
    echo json_encode([
        'success' => false,
        'message' => 'Authorization required'
    ]);
    exit;
}

try {
    $db = getDbConnection();
    
    // Get operations in 'ending' state where ended_at is in the past
    $stmt = $db->prepare("
        SELECT operation_id, title, ended_at
        FROM mining_operations
        WHERE status = 'ending'
        AND ended_at < NOW()
    ");
    $stmt->execute();
    $operations = $stmt->fetchAll();
    
    $finalized = [];
    
    if (empty($operations)) {
        echo json_encode([
            'success' => true,
            'message' => 'No operations need finalization',
            'finalized' => []
        ]);
        exit;
    }
    
    // Process each operation
    foreach ($operations as $operation) {
        $operationId = $operation['operation_id'];
        
        try {
            $db->beginTransaction();
            
            // Update operation status to ended
            $updateStmt = $db->prepare("
                UPDATE mining_operations
                SET status = 'ended', termination_type = 'manual'
                WHERE operation_id = ? AND status = 'ending'
            ");
            $updateStmt->execute([$operationId]);
            
            // Ensure all participants are properly handled
            // First, get all active participants
            $participantsStmt = $db->prepare("
                SELECT user_id 
                FROM operation_participants 
                WHERE operation_id = ? AND status = 'active'
            ");
            $participantsStmt->execute([$operationId]);
            $activeParticipants = $participantsStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Handle the operation_participants update carefully to avoid constraint issues
            // Check for participants who already have 'left' OR 'kicked' status in other operations
            foreach ($activeParticipants as $userId) {
                // Check for conflicts
                $checkStmt = $db->prepare("
                    SELECT GROUP_CONCAT(DISTINCT status) as existing_statuses
                    FROM operation_participants
                    WHERE user_id = ? AND operation_id != ? AND status IN ('left', 'kicked')
                ");
                $checkStmt->execute([$userId, $operationId]);
                $existingStatuses = $checkStmt->fetchColumn();
                
                if ($existingStatuses) {
                    $statuses = explode(',', $existingStatuses);
                    
                    if (in_array('left', $statuses) && in_array('kicked', $statuses)) {
                        // Delete the participant record if they already have both statuses
                        $deleteStmt = $db->prepare("
                            DELETE FROM operation_participants
                            WHERE operation_id = ? AND user_id = ? AND status = 'active'
                        ");
                        $deleteStmt->execute([$operationId, $userId]);
                        
                        logMessage("Deleted participant #$userId with both 'left' and 'kicked' conflicts for operation #$operationId during finalization", 'info');
                    } else if (in_array('left', $statuses)) {
                        // Use 'kicked' status
                        $updateStmt = $db->prepare("
                            UPDATE operation_participants
                            SET status = 'kicked', leave_time = NOW()
                            WHERE operation_id = ? AND user_id = ? AND status = 'active'
                        ");
                        $updateStmt->execute([$operationId, $userId]);
                        
                        logMessage("Updated participant #$userId with 'left' conflicts to 'kicked' status for operation #$operationId during finalization", 'info');
                    } else if (in_array('kicked', $statuses)) {
                        // Use 'banned' status
                        $updateStmt = $db->prepare("
                            UPDATE operation_participants
                            SET status = 'banned', leave_time = NOW()
                            WHERE operation_id = ? AND user_id = ? AND status = 'active'
                        ");
                        $updateStmt->execute([$operationId, $userId]);
                        
                        logMessage("Updated participant #$userId with 'kicked' conflicts to 'banned' status for operation #$operationId during finalization", 'info');
                    }
                } else {
                    // No conflicts, update as 'left'
                    $updateStmt = $db->prepare("
                        UPDATE operation_participants
                        SET status = 'left', leave_time = NOW()
                        WHERE operation_id = ? AND user_id = ? AND status = 'active'
                    ");
                    $updateStmt->execute([$operationId, $userId]);
                }
                
                // Make sure user's active_operation_id is cleared
                $clearStmt = $db->prepare("
                    UPDATE users
                    SET active_operation_id = NULL
                    WHERE user_id = ? AND active_operation_id = ?
                ");
                $clearStmt->execute([$userId, $operationId]);
            }
            
            $db->commit();
            
            // Add to finalized list
            $finalized[] = [
                'operation_id' => $operationId,
                'title' => $operation['title'],
                'ended_at' => $operation['ended_at']
            ];
            
            logMessage("Operation #$operationId finalized from 'ending' to 'ended' state by finalize_operations.php", 'info');
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            logMessage("Error finalizing operation #$operationId: " . $e->getMessage(), 'error');
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => count($finalized) . ' operations have been finalized',
        'finalized' => $finalized
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error finalizing operations: ' . $e->getMessage()
    ]);
    
    // Log the error
    logMessage('Operation finalization error: ' . $e->getMessage(), 'error');
}