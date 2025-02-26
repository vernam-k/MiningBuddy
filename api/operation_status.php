<?php
/**
 * Mining Buddy - Operation Status API
 * 
 * Returns the current status of an operation and its participants
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

// Get operation ID from request
$operationId = isset($_GET['operation_id']) ? intval($_GET['operation_id']) : null;

// Verify operation ID
if (!$operationId) {
    echo json_encode([
        'success' => false,
        'message' => 'Operation ID is required'
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
    $stmt = $db->prepare("
        SELECT mo.*, u.character_name as director_name
        FROM mining_operations mo
        JOIN users u ON mo.director_id = u.user_id
        WHERE mo.operation_id = ?
    ");
    $stmt->execute([$operationId]);
    $operation = $stmt->fetch();
    
    if (!$operation) {
        echo json_encode([
            'success' => false,
            'message' => 'Operation not found'
        ]);
        exit;
    }
    
    // Get participants
    $stmt = $db->prepare("
        SELECT op.user_id, op.status, op.is_admin, op.join_time, op.leave_time,
               u.character_name, u.avatar_url, u.corporation_name, u.corporation_logo_url
        FROM operation_participants op
        JOIN users u ON op.user_id = u.user_id
        WHERE op.operation_id = ?
        ORDER BY op.is_admin DESC, op.status ASC, op.join_time ASC
    ");
    $stmt->execute([$operationId]);
    $participants = $stmt->fetchAll();
    
    // Get total ISK value
    $stmt = $db->prepare("
        SELECT SUM(mp.jita_best_buy * (mls_latest.quantity - IFNULL(mls_initial.quantity, 0))) as total_isk
        FROM (
            SELECT user_id, type_id, quantity
            FROM mining_ledger_snapshots
            WHERE operation_id = ? AND snapshot_type = 'start'
        ) as mls_initial
        RIGHT JOIN (
            SELECT mls.user_id, mls.type_id, mls.quantity
            FROM mining_ledger_snapshots mls
            JOIN (
                SELECT user_id, type_id, MAX(snapshot_id) as latest_id
                FROM mining_ledger_snapshots
                WHERE operation_id = ? AND snapshot_type IN ('update', 'end')
                GROUP BY user_id, type_id
            ) latest ON mls.user_id = latest.user_id AND mls.type_id = latest.type_id AND mls.snapshot_id = latest.latest_id
            WHERE mls.operation_id = ?
        ) as mls_latest ON mls_initial.user_id = mls_latest.user_id AND mls_initial.type_id = mls_latest.type_id
        JOIN market_prices mp ON mls_latest.type_id = mp.type_id
        WHERE (mls_latest.quantity - IFNULL(mls_initial.quantity, 0)) > 0
    ");
    $stmt->execute([$operationId, $operationId, $operationId]);
    $totalIskResult = $stmt->fetch();
    $totalIsk = $totalIskResult ? floatval($totalIskResult['total_isk']) : 0;
    
    // Check if operation is ending
    $countdown = null;
    if ($operation['status'] === 'ending') {
        // Calculate seconds remaining
        $endingTime = strtotime($operation['ended_at']) - 5; // 5 seconds countdown
        $now = time();
        $countdown = max(0, $endingTime - $now);
        
        // If countdown is zero, update operation status to ended
        if ($countdown === 0) {
            $updateStmt = $db->prepare("
                UPDATE mining_operations
                SET status = 'ended', termination_type = 'manual'
                WHERE operation_id = ? AND status = 'ending'
            ");
            $updateStmt->execute([$operationId]);
            
            // Update operation status in the current response
            $operation['status'] = 'ended';
        }
    } else if ($operation['status'] === 'syncing') {
        // Calculate seconds remaining in sync period
        $syncEndTime = strtotime($operation['ended_at']) + 600; // 10 minutes sync
        $now = time();
        $countdown = max(0, $syncEndTime - $now);
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'operation' => [
            'id' => $operation['operation_id'],
            'title' => $operation['title'],
            'director_name' => $operation['director_name'],
            'status' => $operation['status'],
            'created_at' => $operation['created_at'],
            'countdown' => $countdown
        ],
        'participants' => $participants,
        'total_isk' => $totalIsk
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    
    // Log the error
    logMessage('Operation status API error: ' . $e->getMessage(), 'error');
}