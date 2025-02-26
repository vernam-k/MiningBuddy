<?php
/**
 * Mining Buddy - Dashboard
 * 
 * User dashboard for creating/joining operations and viewing statistics
 */

// Set page title
$pageTitle = 'Dashboard';

// Include header
require_once 'includes/header.php';

// Require user to be logged in
requireLogin();

// Get current user
$user = getCurrentUser();

// Check if user is already in an active operation
$inOperation = !empty($user['active_operation_id']);
$activeOperation = null;

if ($inOperation) {
    // Get active operation details
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT mo.*, u.character_name as director_name, u.avatar_url as director_avatar,
               u.corporation_name as director_corporation, u.corporation_logo_url as corporation_logo
        FROM mining_operations mo
        JOIN users u ON mo.director_id = u.user_id
        WHERE mo.operation_id = ?
    ");
    $stmt->execute([$user['active_operation_id']]);
    $activeOperation = $stmt->fetch();
}

// Get user's past operations
function getUserOperations($userId, $limit = 10) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT
            mo.operation_id,
            mo.title,
            mo.created_at,
            mo.ended_at,
            mo.status,
            u.character_name as director_name,
            u.avatar_url as director_avatar,
            u.corporation_name as director_corporation,
            u.corporation_logo_url as corporation_logo,
            os.total_isk_generated,
            os.participant_count,
            (SELECT SUM(quantity * mp.jita_best_buy)
             FROM mining_ledger_snapshots mls
             JOIN market_prices mp ON mls.type_id = mp.type_id
             WHERE mls.operation_id = mo.operation_id
             AND mls.user_id = :user_id_inner
             AND mls.snapshot_type = 'end') as user_isk
        FROM mining_operations mo
        JOIN operation_participants op ON mo.operation_id = op.operation_id
        JOIN users u ON mo.director_id = u.user_id
        LEFT JOIN operation_statistics os ON mo.operation_id = os.operation_id
        WHERE op.user_id = :user_id_outer
        AND (mo.status = 'ended' OR op.status != 'active')
        ORDER BY mo.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':user_id_inner', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id_outer', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get user's statistics
function getUserStats($userId) {
    $db = getDbConnection();
    
    // Check if user_statistics table exists and has data for this user
    $stmt = $db->prepare("
        SELECT EXISTS (
            SELECT 1 FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'user_statistics'
        ) as table_exists
    ");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Return default values if table doesn't exist
        return [
            'total_isk_mined' => 0,
            'total_operations_joined' => 0,
            'average_isk_per_operation' => 0,
            'current_rank' => null
        ];
    }
    
    // Get stats from user_statistics table
    $stmt = $db->prepare("
        SELECT * FROM user_statistics WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    if (!$stats) {
        // Return default values if no stats found
        return [
            'total_isk_mined' => 0,
            'total_operations_joined' => 0,
            'average_isk_per_operation' => 0,
            'current_rank' => null
        ];
    }
    
    return $stats;
}

// Handle form submissions
$formError = '';
$formSuccess = '';

// Create operation form
if (isset($_POST['create_operation'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validate input
    if (empty($title)) {
        $formError = 'Operation title is required';
    } else if ($inOperation) {
        $formError = 'You are already in an active mining operation. Please leave it before creating a new one.';
    } else {
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            
            // Generate unique join code
            $joinCode = generateJoinCode();
            
            // Create operation
            $stmt = $db->prepare("
                INSERT INTO mining_operations (
                    director_id, join_code, title, description,
                    created_at, status
                ) VALUES (?, ?, ?, ?, NOW(), 'active')
            ");
            $stmt->execute([$user['user_id'], $joinCode, $title, $description]);
            $operationId = $db->lastInsertId();
            
            // Add creator as participant and admin
            $stmt = $db->prepare("
                INSERT INTO operation_participants (
                    operation_id, user_id, status, is_admin, join_time
                ) VALUES (?, ?, 'active', 1, NOW())
            ");
            $stmt->execute([$operationId, $user['user_id']]);
            
            // Update user's active operation
            $stmt = $db->prepare("
                UPDATE users SET active_operation_id = ? WHERE user_id = ?
            ");
            $stmt->execute([$operationId, $user['user_id']]);
            
            // Take initial mining ledger snapshot
            $user['active_operation_id'] = $operationId;
            takeMiningLedgerSnapshot($operationId, $user['user_id'], $user['access_token'], 'start');
            
            $db->commit();
            
            // Redirect to operation page
            setFlashMessage('Mining operation created successfully. Share the join code with others to invite them.', 'success');
            redirect('operation.php');
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $formError = 'Error creating operation: ' . $e->getMessage();
            logMessage('Error creating operation: ' . $e->getMessage(), 'error');
        }
    }
}

// Join operation form
if (isset($_POST['join_operation'])) {
    $joinCode = strtoupper(trim($_POST['join_code'] ?? ''));
    
    // Validate input
    if (empty($joinCode)) {
        $formError = 'Join code is required';
    } else if ($inOperation) {
        $formError = 'You are already in an active mining operation. Please leave it before joining a new one.';
    } else {
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            
            // Find operation by join code
            $stmt = $db->prepare("
                SELECT * FROM mining_operations
                WHERE join_code = ? AND status = 'active'
            ");
            $stmt->execute([$joinCode]);
            $operation = $stmt->fetch();
            
            if (!$operation) {
                throw new Exception('Invalid join code or operation is not active');
            }
            
            // Check if user is banned from this operation
            $stmt = $db->prepare("
                SELECT * FROM banned_users
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operation['operation_id'], $user['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception('You have been banned from this operation');
            }
            
            // Check if user was already a participant
            $stmt = $db->prepare("
                SELECT * FROM operation_participants
                WHERE operation_id = ? AND user_id = ?
            ");
            $stmt->execute([$operation['operation_id'], $user['user_id']]);
            $participant = $stmt->fetch();
            
            if ($participant) {
                // Update existing participant status
                $stmt = $db->prepare("
                    UPDATE operation_participants
                    SET status = 'active', join_time = NOW(), leave_time = NULL
                    WHERE operation_id = ? AND user_id = ?
                ");
                $stmt->execute([$operation['operation_id'], $user['user_id']]);
            } else {
                // Add user as new participant
                $stmt = $db->prepare("
                    INSERT INTO operation_participants (
                        operation_id, user_id, status, is_admin, join_time
                    ) VALUES (?, ?, 'active', 0, NOW())
                ");
                $stmt->execute([$operation['operation_id'], $user['user_id']]);
            }
            
            // Update user's active operation
            $stmt = $db->prepare("
                UPDATE users SET active_operation_id = ? WHERE user_id = ?
            ");
            $stmt->execute([$operation['operation_id'], $user['user_id']]);
            
            // Take initial mining ledger snapshot
            $user['active_operation_id'] = $operation['operation_id'];
            takeMiningLedgerSnapshot($operation['operation_id'], $user['user_id'], $user['access_token'], 'start');
            
            $db->commit();
            
            // Redirect to operation page
            setFlashMessage('You have joined the mining operation successfully.', 'success');
            redirect('operation.php');
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $formError = 'Error joining operation: ' . $e->getMessage();
            logMessage('Error joining operation: ' . $e->getMessage(), 'error');
        }
    }
}

// Get user's past operations and stats
$pastOperations = getUserOperations($user['user_id']);
$userStats = getUserStats($user['user_id']);
?>

<div class="container py-4">
    <h1 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h1>
    
    <?php if ($formError): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($formError) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($formSuccess): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($formSuccess) ?>
        </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-8">
            <!-- Active Operation -->
            <?php if ($inOperation && $activeOperation): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary">
                        <h4 class="mb-0"><i class="fas fa-rocket me-2"></i> Your Active Operation</h4>
                    </div>
                    <div class="card-body">
                        <div class="operation-box">
                            <div class="operation-title"><?= htmlspecialchars($activeOperation['title']) ?></div>
                            
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <div class="eve-character mb-2">
                                        <img src="<?= htmlspecialchars($activeOperation['director_avatar'] ?? '') ?>"
                                             class="eve-character-avatar" 
                                             alt="<?= htmlspecialchars($activeOperation['director_name']) ?>">
                                        <span>Director: <?= htmlspecialchars($activeOperation['director_name']) ?></span>
                                    </div>
                                    
                                    <div class="eve-corporation">
                                        <img src="<?= htmlspecialchars($activeOperation['corporation_logo'] ?? '') ?>"
                                             class="eve-corporation-logo" 
                                             alt="<?= htmlspecialchars($activeOperation['director_corporation']) ?>">
                                        <span>Corporation: <?= htmlspecialchars($activeOperation['director_corporation']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 text-md-end">
                                    <div class="join-code mb-2">
                                        <?= htmlspecialchars($activeOperation['join_code']) ?>
                                    </div>
                                    <div class="small text-muted">Operation Join Code</div>
                                </div>
                            </div>
                            
                            <a href="operation.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-rocket me-2"></i> Return to Operation
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Operation Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-rocket me-2"></i> Mining Operations</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-plus-circle me-2"></i> Create Operation</h5>
                                        <p class="text-muted">Create a new mining operation and become the Mining Director.</p>
                                        
                                        <form action="" method="post">
                                            <div class="mb-3">
                                                <label for="title" class="form-label">Operation Title</label>
                                                <input type="text" class="form-control" id="title" name="title" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Description (Optional)</label>
                                                <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                                            </div>
                                            
                                            <button type="submit" name="create_operation" class="btn btn-primary w-100">
                                                <i class="fas fa-plus-circle me-2"></i> Create Operation
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-sign-in-alt me-2"></i> Join Operation</h5>
                                        <p class="text-muted">Join an existing mining operation using a join code.</p>
                                        
                                        <form action="" method="post">
                                            <div class="mb-3">
                                                <label for="join_code" class="form-label">Operation Join Code</label>
                                                <input type="text" class="form-control" id="join_code" name="join_code" required
                                                       placeholder="Enter the 6-character code" maxlength="6">
                                            </div>
                                            
                                            <button type="submit" name="join_operation" class="btn btn-primary w-100">
                                                <i class="fas fa-sign-in-alt me-2"></i> Join Operation
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Past Operations -->
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-history me-2"></i> Your Mining History</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($pastOperations)): ?>
                        <p class="text-muted text-center">You haven't participated in any mining operations yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>Operation</th>
                                        <th>Director</th>
                                        <th>Corporation</th>
                                        <th>Your ISK</th>
                                        <th>Total ISK</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pastOperations as $op): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($op['title']) ?></td>
                                            <td>
                                                <div class="eve-character">
                                                    <img src="<?= htmlspecialchars($op['director_avatar'] ?? '') ?>"
                                                         class="eve-character-avatar" 
                                                         alt="<?= htmlspecialchars($op['director_name']) ?>">
                                                    <span><?= htmlspecialchars($op['director_name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="eve-corporation">
                                                    <img src="<?= htmlspecialchars($op['corporation_logo'] ?? '') ?>"
                                                         class="eve-corporation-logo" 
                                                         alt="<?= htmlspecialchars($op['director_corporation']) ?>">
                                                    <span><?= htmlspecialchars($op['director_corporation']) ?></span>
                                                </div>
                                            </td>
                                            <td class="isk-value"><?= formatIsk($op['user_isk'] ?? 0) ?></td>
                                            <td class="isk-value"><?= formatIsk($op['total_isk_generated'] ?? 0) ?></td>
                                            <td><?= formatDateTime($op['created_at'], 'M d, Y H:i') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- User Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Your Statistics</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-4">
                            <div class="stats-card">
                                <div class="stats-label">Total ISK Mined</div>
                                <div class="stats-value"><?= formatIsk($userStats['total_isk_mined'] ?? 0) ?></div>
                            </div>
                        </div>
                        
                        <div class="col-6 mb-4">
                            <div class="stats-card">
                                <div class="stats-label">Operations Joined</div>
                                <div class="stats-value"><?= number_format($userStats['total_operations_joined'] ?? 0) ?></div>
                            </div>
                        </div>
                        
                        <div class="col-6 mb-4">
                            <div class="stats-card">
                                <div class="stats-label">Average ISK/Op</div>
                                <div class="stats-value"><?= formatIsk($userStats['average_isk_per_operation'] ?? 0) ?></div>
                            </div>
                        </div>
                        
                        <div class="col-6 mb-4">
                            <div class="stats-card">
                                <div class="stats-label">Current Rank</div>
                                <div class="stats-value">
                                    <?= $userStats['current_rank'] ? '#' . number_format($userStats['current_rank']) : 'N/A' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (($userStats['total_isk_mined'] ?? 0) > 0): ?>
                        <div class="text-center mt-3">
                            <a href="#" class="btn btn-outline-primary btn-sm">View Detailed Statistics</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- User Profile -->
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-user me-2"></i> Character Profile</h4>
                </div>
                <div class="card-body text-center">
                    <img src="<?= htmlspecialchars($user['avatar_url'] ?? '') ?>"
                         alt="<?= htmlspecialchars($user['character_name']) ?>"
                         class="rounded-circle mb-3"
                         style="width: 128px; height: 128px;">
                    
                    <h5><?= htmlspecialchars($user['character_name']) ?></h5>
                    
                    <div class="d-flex justify-content-center mb-3">
                        <img src="<?= htmlspecialchars($user['corporation_logo_url'] ?? '') ?>"
                             alt="<?= htmlspecialchars($user['corporation_name']) ?>"
                             class="me-2"
                             style="width: 32px; height: 32px;">
                        <span><?= htmlspecialchars($user['corporation_name']) ?></span>
                    </div>
                    
                    <div class="small text-muted mb-3">
                        Member since: <?= formatDateTime($user['created_at'], 'F j, Y') ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
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