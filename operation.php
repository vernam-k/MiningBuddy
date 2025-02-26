<?php
/**
 * Mining Buddy - Mining Operation Page
 * 
 * Main page for mining operations, showing participants and mining data
 */

// Set page title
$pageTitle = 'Mining Operation';

// Include header
require_once 'includes/header.php';

// Require user to be logged in
requireLogin();

// Get current user
$user = getCurrentUser();

// Check if user has an active operation
if (empty($user['active_operation_id'])) {
    setFlashMessage('You are not currently participating in any mining operation.', 'error');
    redirect('dashboard.php');
    exit;
}

// Refresh user token if needed
refreshUserTokenIfNeeded($user['user_id']);

// Get operation details
$db = getDbConnection();
$stmt = $db->prepare("
    SELECT mo.*, u.character_name as director_name, u.avatar_url as director_avatar,
           u.corporation_name as director_corporation, u.corporation_logo_url as corporation_logo
    FROM mining_operations mo
    JOIN users u ON mo.director_id = u.user_id
    WHERE mo.operation_id = ?
");
$stmt->execute([$user['active_operation_id']]);
$operation = $stmt->fetch();

if (!$operation) {
    setFlashMessage('The operation you were participating in no longer exists.', 'error');
    
    // Clear user's active operation
    $stmt = $db->prepare("UPDATE users SET active_operation_id = NULL WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    
    redirect('dashboard.php');
    exit;
}

// Check if user is participant
$stmt = $db->prepare("
    SELECT * FROM operation_participants
    WHERE operation_id = ? AND user_id = ?
");
$stmt->execute([$operation['operation_id'], $user['user_id']]);
$participant = $stmt->fetch();

if (!$participant || $participant['status'] !== 'active') {
    setFlashMessage('You are not an active participant in this operation.', 'error');
    
    // Clear user's active operation
    $stmt = $db->prepare("UPDATE users SET active_operation_id = NULL WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    
    redirect('dashboard.php');
    exit;
}

// Determine if user is admin or director
$isAdmin = $participant['is_admin'] == 1;
$isDirector = $operation['director_id'] == $user['user_id'];

// Handle admin actions via AJAX only
// AJAX endpoints are in api/participant_action.php

// Get all participants
$stmt = $db->prepare("
    SELECT op.*, u.character_name, u.avatar_url, u.corporation_name, u.corporation_logo_url
    FROM operation_participants op
    JOIN users u ON op.user_id = u.user_id
    WHERE op.operation_id = ?
    ORDER BY op.is_admin DESC, op.status ASC, op.join_time ASC
");
$stmt->execute([$operation['operation_id']]);
$participants = $stmt->fetchAll();

// Get mining data for all participants
function getMiningData($operationId) {
    $db = getDbConnection();
    
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
    
    // Calculate differences
    $miningData = [];
    foreach ($latestSnapshots as $snapshot) {
        $userId = $snapshot['user_id'];
        $typeId = $snapshot['type_id'];
        
        // Get initial quantity (if exists)
        $initialQuantity = $initialQuantities[$userId][$typeId] ?? 0;
        
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
    
    return $miningData;
}

// Calculate total ISK mined
function calculateTotalIsk($miningData) {
    $total = 0;
    foreach ($miningData as $userData) {
        $total += $userData['total_value'];
    }
    return $total;
}

// Get mining data
$miningData = getMiningData($operation['operation_id']);
$totalIsk = calculateTotalIsk($miningData);

// Add JavaScript variables and functions for this page
$extraScripts = '
<script>
// Set up mining data refresh interval
let miningDataLastUpdated = ' . json_encode(date('Y-m-d H:i:s')) . ';

// Set up operation details for JavaScript
document.addEventListener("DOMContentLoaded", function() {
    if (typeof MiningBuddy !== "undefined") {
        MiningBuddy.operation.lastMiningUpdate = miningDataLastUpdated;
    }
});
</script>
';
?>

<div class="container py-4 operation-container">
    <!-- Hidden inputs for JavaScript -->
    <input type="hidden" id="operation-id" value="<?= $operation['operation_id'] ?>">
    <input type="hidden" id="operation-start-time" value="<?= $operation['created_at'] ?>">
    <input type="hidden" id="is-admin" value="<?= $isAdmin ? '1' : '0' ?>">
    <input type="hidden" id="is-director" value="<?= $isDirector ? '1' : '0' ?>">
    <input type="hidden" id="current-user-id" value="<?= $user['user_id'] ?>">
    
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-0"><?= htmlspecialchars($operation['title']) ?></h1>
            <?php if (!empty($operation['description'])): ?>
                <p class="text-muted mt-2"><?= htmlspecialchars($operation['description']) ?></p>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4 text-md-end">
            <div class="operation-timer" id="operation-timer">00:00:00</div>
            <div class="text-muted small">Operation Duration</div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="mb-0">Mining Director</h5>
                            <div class="eve-character mt-2">
                                <img src="<?= htmlspecialchars($operation['director_avatar'] ?? '') ?>"
                                     class="eve-character-avatar" 
                                     alt="<?= htmlspecialchars($operation['director_name']) ?>">
                                <span><?= htmlspecialchars($operation['director_name']) ?></span>
                            </div>
                            
                            <div class="eve-corporation mt-2">
                                <img src="<?= htmlspecialchars($operation['corporation_logo'] ?? '') ?>"
                                     class="eve-corporation-logo" 
                                     alt="<?= htmlspecialchars($operation['director_corporation']) ?>">
                                <span><?= htmlspecialchars($operation['director_corporation']) ?></span>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <h5 class="mb-0">Join Code</h5>
                            <div class="join-code mt-2">
                                <?= htmlspecialchars($operation['join_code']) ?>
                            </div>
                            <div class="text-muted small">Share with others to invite</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Total ISK Mined</h5>
                        <div class="isk-value h4 mb-0" id="total-isk-value"><?= formatIsk($totalIsk) ?></div>
                    </div>
                    
                    <div class="update-notice">
                        <i class="fas fa-info-circle me-2"></i> 
                        Mining data updates every 10 minutes due to EVE API cache limitations.
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <small>Next mining data update in <span id="mining-data-countdown">--:--</span></small>
                            </div>
                            <div>
                                <small>Last updated: <span id="last-mining-update"><?= date('H:i:s') ?></span></small>
                                <div class="loading-spinner ms-2" id="mining-data-loading" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($isAdmin): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="admin-actions">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i> Admin Controls</h5>
                    
                    <div>
                        <?php if ($isDirector): ?>
                            <button id="end-operation-btn" class="btn btn-danger">
                                <i class="fas fa-stop-circle me-2"></i> End Operation
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-2 small text-muted">
                    <div class="d-flex align-items-center">
                        <span class="token-status token-status-valid"></span>
                        <span>API Token Status: <span id="token-status">Valid</span></span>
                        
                        <div class="ms-auto">
                            <small>Participant updates in <span id="participant-status-countdown">--s</span> | Last updated: <span id="last-participant-update"><?= date('H:i:s') ?></span></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row mb-4">
        <div class="col-12 text-end">
            <button id="leave-operation-btn" class="btn btn-outline-danger">
                <i class="fas fa-sign-out-alt me-2"></i> Leave Operation
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-users me-2"></i> Participants</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0" id="participant-table">
                            <thead>
                                <tr>
                                    <th>Character</th>
                                    <th>Corporation</th>
                                    <th>Ores Mined</th>
                                    <th>ISK Value</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $p): ?>
                                    <tr id="participant-<?= $p['user_id'] ?>" class="<?= 
                                        $p['status'] === 'active' 
                                            ? ($p['is_admin'] ? 'participant-admin' : 'participant-active')
                                            : 'participant-left'
                                    ?>">
                                        <td>
                                            <div class="eve-character">
                                                <img src="<?= htmlspecialchars($p['avatar_url'] ?? '') ?>"
                                                     class="eve-character-avatar" 
                                                     alt="<?= htmlspecialchars($p['character_name']) ?>">
                                                <a href="#" class="eve-character-name"><?= htmlspecialchars($p['character_name']) ?></a>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="eve-corporation">
                                                <img src="<?= htmlspecialchars($p['corporation_logo_url'] ?? '') ?>"
                                                     class="eve-corporation-logo" 
                                                     alt="<?= htmlspecialchars($p['corporation_name']) ?>">
                                                <span class="eve-corporation-name"><?= htmlspecialchars($p['corporation_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="ores-mined-col" id="ores-<?= $p['user_id'] ?>">
                                            <?php if (isset($miningData[$p['user_id']]) && !empty($miningData[$p['user_id']]['ores'])): ?>
                                                <?php foreach ($miningData[$p['user_id']]['ores'] as $ore): ?>
                                                    <div class="ore-item">
                                                        <?php if (!empty($ore['icon_url'])): ?>
                                                            <img src="<?= htmlspecialchars($ore['icon_url']) ?>" class="ore-icon" alt="<?= htmlspecialchars($ore['name']) ?>">
                                                        <?php endif; ?>
                                                        <span class="ore-name"><?= htmlspecialchars($ore['name']) ?></span>
                                                        <span class="ore-quantity"><?= number_format($ore['quantity']) ?></span>
                                                        <span class="ore-value"><?= formatIsk($ore['value']) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-muted">No ores mined</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="isk-value-col" id="isk-value-<?= $p['user_id'] ?>">
                                            <span class="isk-value">
                                                <?= formatIsk($miningData[$p['user_id']]['total_value'] ?? 0) ?>
                                            </span>
                                        </td>
                                        <td class="actions-col">
                                            <?php if ($p['user_id'] == $user['user_id']): ?>
                                                <button class="btn btn-sm btn-outline-danger" id="leave-operation-btn">Leave</button>
                                            <?php elseif ($isAdmin && $p['status'] === 'active'): ?>
                                                <?php if (!$p['is_admin'] || $isDirector): ?>
                                                    <button class="btn btn-sm btn-outline-warning kick-participant-btn" 
                                                            data-user-id="<?= $p['user_id'] ?>" 
                                                            data-user-name="<?= htmlspecialchars($p['character_name']) ?>"
                                                            data-bs-toggle="tooltip" 
                                                            title="Kick from operation">
                                                        <i class="fas fa-user-times"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger ban-participant-btn" 
                                                            data-user-id="<?= $p['user_id'] ?>" 
                                                            data-user-name="<?= htmlspecialchars($p['character_name']) ?>"
                                                            data-bs-toggle="tooltip" 
                                                            title="Ban from operation">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                    
                                                    <?php if ($isDirector): ?>
                                                        <button class="btn btn-sm btn-outline-primary promote-participant-btn" 
                                                                data-user-id="<?= $p['user_id'] ?>" 
                                                                data-user-name="<?= htmlspecialchars($p['character_name']) ?>"
                                                                data-bs-toggle="tooltip" 
                                                                title="Promote to Mining Director">
                                                            <i class="fas fa-crown"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>