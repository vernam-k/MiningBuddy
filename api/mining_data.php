<?php
/**
 * Mining Buddy - Mining Data API
 * 
 * Returns mining data for all participants in an operation
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
    if ($operation['status'] !== 'active' && $operation['status'] !== 'ending') {
        echo json_encode([
            'success' => false,
            'message' => 'Operation is not active'
        ]);
        exit;
    }
    
    // Get active participants
    $stmt = $db->prepare("
        SELECT user_id
        FROM operation_participants
        WHERE operation_id = ? AND status = 'active'
    ");
    $stmt->execute([$operationId]);
    $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Refresh token for current user if needed
    refreshUserTokenIfNeeded($user['user_id']);
    
    // Update mining data for current user
    takeMiningLedgerSnapshot($operationId, $user['user_id'], $user['access_token'], 'update');
    
    // Get initial snapshots
    $stmt = $db->prepare("
        SELECT user_id, type_id, quantity
        FROM mining_ledger_snapshots
        WHERE operation_id = ? AND snapshot_type = 'start'
    ");
    $stmt->execute([$operationId]);
    $initialSnapshots = $stmt->fetchAll();
    
    // Build initial quantities by user and type
    $initialQuantities = [];
    foreach ($initialSnapshots as $snapshot) {
        $userId = $snapshot['user_id'];
        $typeId = $snapshot['type_id'];
        
        if (!isset($initialQuantities[$userId])) {
            $initialQuantities[$userId] = [];
        }
        
        $initialQuantities[$userId][$typeId] = $snapshot['quantity'];
    }
    
    // Get latest snapshots
    $stmt = $db->prepare("
        SELECT mls.user_id, mls.type_id, mls.quantity, mls.snapshot_time,
               ot.name as ore_name, ot.icon_url as ore_icon, mp.jita_best_buy
        FROM mining_ledger_snapshots mls
        JOIN (
            SELECT user_id, type_id, MAX(snapshot_id) as latest_id
            FROM mining_ledger_snapshots
            WHERE operation_id = ? AND snapshot_type IN ('update', 'end')
            GROUP BY user_id, type_id
        ) latest ON mls.user_id = latest.user_id AND mls.type_id = latest.type_id AND mls.snapshot_id = latest.latest_id
        LEFT JOIN ore_types ot ON mls.type_id = ot.type_id
        LEFT JOIN market_prices mp ON mls.type_id = mp.type_id
        WHERE mls.operation_id = ?
    ");
    $stmt->execute([$operationId, $operationId]);
    $latestSnapshots = $stmt->fetchAll();
    
    // Calculate differences and prepare mining data
    $miningData = [];
    foreach ($latestSnapshots as $snapshot) {
        $userId = $snapshot['user_id'];
        $typeId = $snapshot['type_id'];
        
        // Skip if user is not an active participant
        if (!in_array($userId, $participants)) {
            continue;
        }
        
        // Get initial quantity (if exists)
        $initialQuantity = isset($initialQuantities[$userId][$typeId]) ? $initialQuantities[$userId][$typeId] : 0;
        
        // Calculate difference
        $minedQuantity = $snapshot['quantity'] - $initialQuantity;
        
        // Only include positive quantities
        if ($minedQuantity <= 0) {
            continue;
        }
        
        // Calculate value
        $value = $minedQuantity * ($snapshot['jita_best_buy'] ?? 0);
        
        // Add to mining data
        if (!isset($miningData[$userId])) {
            $miningData[$userId] = [
                'ores' => [],
                'total_value' => 0,
                'last_update' => null
            ];
        }
        
        $miningData[$userId]['ores'][] = [
            'type_id' => $typeId,
            'name' => $snapshot['ore_name'] ?? "Type #$typeId",
            'quantity' => $minedQuantity,
            'value' => $value,
            'icon_url' => $snapshot['ore_icon']
        ];
        
        $miningData[$userId]['total_value'] += $value;
        
        // Track last update time
        $snapshotTime = $snapshot['snapshot_time'] ?? null;
        $lastUpdate = $miningData[$userId]['last_update'] ?? null;
        
        if (!$lastUpdate || 
            ($snapshotTime && $lastUpdate && strtotime($snapshotTime) > strtotime($lastUpdate))
        ) {
            $miningData[$userId]['last_update'] = $snapshotTime ?? date('Y-m-d H:i:s');
        }
    }
    
    // Get latest market prices
    $stmt = $db->prepare("
        SELECT mp.type_id, mp.jita_best_buy, ot.name
        FROM market_prices mp
        JOIN ore_types ot ON mp.type_id = ot.type_id
    ");
    $stmt->execute();
    $marketPrices = $stmt->fetchAll();
    // Check if operation was created very recently (within 2 minutes)
    // If so, explicitly set mining data to zero to prevent showing historical data
    $operationCreatedTime = strtotime($operation['created_at']);
    $currentTime = time();
    $operationAgeInSeconds = $currentTime - $operationCreatedTime;

    if ($operationAgeInSeconds < 120) { // Less than 2 minutes old
        // Check if there are update snapshots after the initial snapshots
        $stmt = $db->prepare("
            SELECT COUNT(*) as update_count
            FROM mining_ledger_snapshots
            WHERE operation_id = ? AND snapshot_type = 'update' AND snapshot_time > ?
        ");
        $stmt->execute([$operationId, $operation['created_at']]);
        $updateCount = $stmt->fetchColumn();
        
        // If there are no meaningful update snapshots yet, zero out the mining data
        // This prevents showing historical data before the first substantial update
        if ($updateCount < 2) { // Need at least 2 updates to show meaningful progress
            logMessage("New operation detected - zeroing mining data for operation #$operationId", 'info');
            $miningData = [];
        }
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'mining_data' => $miningData,
        'market_prices' => $marketPrices,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    
    // Log the error
    logMessage('Mining data API error: ' . $e->getMessage(), 'error');
}