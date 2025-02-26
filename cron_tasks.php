<?php
/**
 * Mining Buddy - Cron Tasks
 *
 * Background tasks to run periodically via cron job
 * Recommended to run every 5-15 minutes
 *
 * Example cron entry (run every 15 minutes):
 * Run: php /path/to/MiningBuddy/cron_tasks.php > /dev/null 2>&1
 */

// Set to CLI mode
define('CLI_MODE', true);

// Include functions file
require_once __DIR__ . '/includes/functions.php';

// Log script start
logMessage('Starting cron tasks', 'info');

try {
    $db = getDbConnection();
    
    // Task 1: Check for inactive operations
    checkInactiveOperations($db);
    
    // Task 2: Update market prices
    updateMarketPrices($db);
    
    // Task 3: Refresh expiring tokens
    refreshExpiringTokens($db);
    
    // Task 4: Generate statistics
    generateStatistics($db);
    
    // Log successful completion
    logMessage('Cron tasks completed successfully', 'info');
    
} catch (Exception $e) {
    logMessage('Error in cron tasks: ' . $e->getMessage(), 'error');
}

/**
 * Check for inactive operations
 * 
 * Identifies operations with no mining activity for over 2 hours
 * and automatically ends them
 */
function checkInactiveOperations($db) {
    logMessage('Checking for inactive operations...', 'info');
    
    // Get operations with no activity for over 2 hours
    $stmt = $db->prepare("
        SELECT operation_id, title
        FROM mining_operations
        WHERE status = 'active'
        AND last_mining_activity IS NOT NULL
        AND last_mining_activity < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ");
    $stmt->execute();
    $inactiveOperations = $stmt->fetchAll();
    
    if (empty($inactiveOperations)) {
        logMessage('No inactive operations found', 'info');
        return;
    }
    
    logMessage('Found ' . count($inactiveOperations) . ' inactive operations', 'info');
    
    // End each inactive operation
    foreach ($inactiveOperations as $operation) {
        try {
            $db->beginTransaction();
            
            // Update operation status to ending
            $stmt = $db->prepare("
                UPDATE mining_operations
                SET status = 'ending', ended_at = DATE_ADD(NOW(), INTERVAL 5 SECOND),
                    termination_type = 'inactivity'
                WHERE operation_id = ?
            ");
            $stmt->execute([$operation['operation_id']]);
            
            $db->commit();
            
            logMessage('Auto-ended inactive operation #' . $operation['operation_id'] . ': ' . $operation['title'], 'info');
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            logMessage('Error ending inactive operation #' . $operation['operation_id'] . ': ' . $e->getMessage(), 'error');
        }
    }
}

/**
 * Update market prices
 * 
 * Updates market prices for all ore types in the database
 */
function updateMarketPrices($db) {
    logMessage('Updating market prices...', 'info');
    
    // Get list of ore types
    $stmt = $db->prepare("SELECT type_id FROM ore_types");
    $stmt->execute();
    $oreTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($oreTypes)) {
        logMessage('No ore types found in database', 'info');
        return;
    }
    
    // Find a user with a valid token
    $stmt = $db->prepare("
        SELECT user_id, access_token 
        FROM users 
        WHERE token_expires > NOW()
        ORDER BY last_login DESC
        LIMIT 1
    ");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        logMessage('No users with valid tokens found', 'warning');
        return;
    }
    
    // Get market prices
    try {
        // Get prices from EVE API
        $prices = getMarketPrices($user['access_token']);
        
        if (empty($prices)) {
            logMessage('No market prices returned from API', 'warning');
            return;
        }
        
        // Create price map
        $priceMap = [];
        foreach ($prices as $price) {
            if (isset($price['type_id'])) {
                $priceMap[$price['type_id']] = [
                    'adjusted_price' => $price['adjusted_price'] ?? 0,
                    'average_price' => $price['average_price'] ?? 0
                ];
            }
        }
        
        // Update prices for each ore type
        $updated = 0;
        foreach ($oreTypes as $typeId) {
            try {
                // Get Jita buy orders
                $buyPrice = getBestJitaBuyPrice($typeId, $user['access_token']);
                
                // Get adjusted price from price map
                $sellPrice = $priceMap[$typeId]['adjusted_price'] ?? 0;
                
                // Update in database
                $stmt = $db->prepare("
                    INSERT INTO market_prices (type_id, jita_best_buy, jita_best_sell, updated_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        jita_best_buy = VALUES(jita_best_buy),
                        jita_best_sell = VALUES(jita_best_sell),
                        updated_at = NOW()
                ");
                $stmt->execute([$typeId, $buyPrice, $sellPrice]);
                $updated++;
                
            } catch (Exception $e) {
                logMessage('Error updating price for type ID ' . $typeId . ': ' . $e->getMessage(), 'error');
            }
        }
        
        logMessage('Updated prices for ' . $updated . ' ore types', 'info');
        
    } catch (Exception $e) {
        logMessage('Error updating market prices: ' . $e->getMessage(), 'error');
    }
}

/**
 * Refresh expiring tokens
 * 
 * Refreshes tokens that will expire in the next 5 minutes
 */
function refreshExpiringTokens($db) {
    logMessage('Refreshing expiring tokens...', 'info');
    
    // Get users with tokens expiring in the next 5 minutes
    $stmt = $db->prepare("
        SELECT user_id, character_name
        FROM users
        WHERE token_expires > NOW()
        AND token_expires < DATE_ADD(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        logMessage('No expiring tokens found', 'info');
        return;
    }
    
    logMessage('Found ' . count($users) . ' expiring tokens', 'info');
    
    // Refresh each token
    $refreshed = 0;
    foreach ($users as $user) {
        try {
            $success = refreshUserTokenIfNeeded($user['user_id']);
            
            if ($success) {
                $refreshed++;
                logMessage('Refreshed token for user #' . $user['user_id'] . ': ' . $user['character_name'], 'info');
            } else {
                logMessage('Token refresh not needed for user #' . $user['user_id'] . ': ' . $user['character_name'], 'info');
            }
        } catch (Exception $e) {
            logMessage('Error refreshing token for user #' . $user['user_id'] . ': ' . $e->getMessage(), 'error');
        }
    }
    
    logMessage('Refreshed ' . $refreshed . ' tokens', 'info');
}

/**
 * Generate statistics
 * 
 * Updates aggregate statistics tables
 */
function generateStatistics($db) {
    logMessage('Generating statistics...', 'info');
    
    // Check if statistics tables exist
    $tables = ['user_statistics', 'corporation_statistics', 'operation_statistics', 'time_period_statistics'];
    $allTablesExist = true;
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
            ) as table_exists
        ");
        $stmt->execute([$table]);
        
        if (!$stmt->fetchColumn()) {
            $allTablesExist = false;
            logMessage("Table $table does not exist, skipping statistics generation", 'warning');
            break;
        }
    }
    
    if (!$allTablesExist) {
        return;
    }
    
    try {
        // Update user statistics
        $stmt = $db->prepare("
            INSERT INTO user_statistics (
                user_id, total_isk_mined, total_operations_joined,
                total_active_time, average_isk_per_operation,
                average_isk_per_hour, highest_isk_operation_id,
                highest_isk_operation_value, last_updated
            )
            SELECT
                u.user_id,
                COALESCE(SUM(
                    mp.jita_best_buy * (mls_latest.quantity - IFNULL(mls_initial.quantity, 0))
                ), 0) as total_isk_mined,
                COUNT(DISTINCT op.operation_id) as total_operations_joined,
                COALESCE(SUM(
                    TIMESTAMPDIFF(SECOND, op.join_time, IFNULL(op.leave_time, NOW()))
                ), 0) as total_active_time,
                COALESCE(SUM(
                    mp.jita_best_buy * (mls_latest.quantity - IFNULL(mls_initial.quantity, 0))
                ) / COUNT(DISTINCT op.operation_id), 0) as average_isk_per_operation,
                COALESCE(SUM(
                    mp.jita_best_buy * (mls_latest.quantity - IFNULL(mls_initial.quantity, 0))
                ) / (SUM(TIMESTAMPDIFF(SECOND, op.join_time, IFNULL(op.leave_time, NOW()))) / 3600), 0) as average_isk_per_hour,
                NULL as highest_isk_operation_id,
                0 as highest_isk_operation_value,
                NOW() as last_updated
            FROM users u
            LEFT JOIN operation_participants op ON u.user_id = op.user_id
            LEFT JOIN (
                SELECT user_id, type_id, quantity, operation_id
                FROM mining_ledger_snapshots
                WHERE snapshot_type = 'start'
            ) as mls_initial ON op.user_id = mls_initial.user_id AND op.operation_id = mls_initial.operation_id
            LEFT JOIN (
                SELECT mls.user_id, mls.type_id, mls.quantity, mls.operation_id
                FROM mining_ledger_snapshots mls
                JOIN (
                    SELECT user_id, type_id, operation_id, MAX(snapshot_id) as latest_id
                    FROM mining_ledger_snapshots
                    WHERE snapshot_type IN ('update', 'end')
                    GROUP BY user_id, type_id, operation_id
                ) latest ON mls.user_id = latest.user_id AND mls.type_id = latest.type_id 
                    AND mls.operation_id = latest.operation_id AND mls.snapshot_id = latest.latest_id
            ) as mls_latest ON op.user_id = mls_latest.user_id AND op.operation_id = mls_latest.operation_id 
                AND mls_initial.type_id = mls_latest.type_id
            LEFT JOIN market_prices mp ON mls_latest.type_id = mp.type_id
            WHERE (mls_latest.quantity - IFNULL(mls_initial.quantity, 0)) > 0
            GROUP BY u.user_id
            ON DUPLICATE KEY UPDATE
                total_isk_mined = VALUES(total_isk_mined),
                total_operations_joined = VALUES(total_operations_joined),
                total_active_time = VALUES(total_active_time),
                average_isk_per_operation = VALUES(average_isk_per_operation),
                average_isk_per_hour = VALUES(average_isk_per_hour),
                last_updated = NOW()
        ");
        $stmt->execute();
        
        // Update user rankings
        $stmt = $db->prepare("
            SET @rank := 0;
            UPDATE user_statistics
            JOIN (
                SELECT user_id, @rank := @rank + 1 as rank
                FROM user_statistics
                ORDER BY total_isk_mined DESC
            ) as rankings ON user_statistics.user_id = rankings.user_id
            SET user_statistics.current_rank = rankings.rank
        ");
        $stmt->execute();
        
        logMessage('Updated user statistics', 'info');
        
        // Update corporation statistics (simplified version)
        $stmt = $db->prepare("
            INSERT INTO corporation_statistics (
                corporation_id, corporation_name, total_isk_mined,
                total_members_participating, total_operations_directed,
                average_isk_per_operation, most_active_director_id,
                top_contributor_id, last_updated
            )
            SELECT
                u.corporation_id,
                u.corporation_name,
                COALESCE(SUM(us.total_isk_mined), 0) as total_isk_mined,
                COUNT(DISTINCT u.user_id) as total_members_participating,
                COUNT(DISTINCT mo.operation_id) as total_operations_directed,
                COALESCE(SUM(us.total_isk_mined) / COUNT(DISTINCT mo.operation_id), 0) as average_isk_per_operation,
                NULL as most_active_director_id,
                NULL as top_contributor_id,
                NOW() as last_updated
            FROM users u
            LEFT JOIN user_statistics us ON u.user_id = us.user_id
            LEFT JOIN mining_operations mo ON u.user_id = mo.director_id
            GROUP BY u.corporation_id, u.corporation_name
            ON DUPLICATE KEY UPDATE
                corporation_name = VALUES(corporation_name),
                total_isk_mined = VALUES(total_isk_mined),
                total_members_participating = VALUES(total_members_participating),
                total_operations_directed = VALUES(total_operations_directed),
                average_isk_per_operation = VALUES(average_isk_per_operation),
                last_updated = NOW()
        ");
        $stmt->execute();
        
        // Update corporation rankings
        $stmt = $db->prepare("
            SET @rank := 0;
            UPDATE corporation_statistics
            JOIN (
                SELECT corporation_id, @rank := @rank + 1 as rank
                FROM corporation_statistics
                ORDER BY total_isk_mined DESC
            ) as rankings ON corporation_statistics.corporation_id = rankings.corporation_id
            SET corporation_statistics.current_rank = rankings.rank
        ");
        $stmt->execute();
        
        logMessage('Updated corporation statistics', 'info');
        
        // Update operation statistics for completed operations
        $stmt = $db->prepare("
            INSERT INTO operation_statistics (
                operation_id, total_isk_generated, operation_duration,
                participant_count, peak_concurrent_participants,
                most_valuable_contributor_id, most_active_contributor_id,
                average_isk_per_participant, most_mined_ore_type_id,
                created_at
            )
            SELECT
                mo.operation_id,
                COALESCE(SUM(
                    mp.jita_best_buy * (mls_latest.quantity - IFNULL(mls_initial.quantity, 0))
                ), 0) as total_isk_generated,
                TIMESTAMPDIFF(SECOND, mo.created_at, mo.ended_at) as operation_duration,
                COUNT(DISTINCT op.user_id) as participant_count,
                0 as peak_concurrent_participants,
                NULL as most_valuable_contributor_id,
                NULL as most_active_contributor_id,
                COALESCE(SUM(
                    mp.jita_best_buy * (mls_latest.quantity - IFNULL(mls_initial.quantity, 0))
                ) / COUNT(DISTINCT op.user_id), 0) as average_isk_per_participant,
                NULL as most_mined_ore_type_id,
                mo.created_at
            FROM mining_operations mo
            LEFT JOIN operation_participants op ON mo.operation_id = op.operation_id
            LEFT JOIN (
                SELECT user_id, type_id, quantity, operation_id
                FROM mining_ledger_snapshots
                WHERE snapshot_type = 'start'
            ) as mls_initial ON op.user_id = mls_initial.user_id AND op.operation_id = mls_initial.operation_id
            LEFT JOIN (
                SELECT mls.user_id, mls.type_id, mls.quantity, mls.operation_id
                FROM mining_ledger_snapshots mls
                JOIN (
                    SELECT user_id, type_id, operation_id, MAX(snapshot_id) as latest_id
                    FROM mining_ledger_snapshots
                    WHERE snapshot_type IN ('update', 'end')
                    GROUP BY user_id, type_id, operation_id
                ) latest ON mls.user_id = latest.user_id AND mls.type_id = latest.type_id 
                    AND mls.operation_id = latest.operation_id AND mls.snapshot_id = latest.latest_id
            ) as mls_latest ON op.user_id = mls_latest.user_id AND op.operation_id = mls_latest.operation_id 
                AND mls_initial.type_id = mls_latest.type_id
            LEFT JOIN market_prices mp ON mls_latest.type_id = mp.type_id
            WHERE mo.status = 'ended' AND mo.operation_id NOT IN (SELECT operation_id FROM operation_statistics)
            GROUP BY mo.operation_id, mo.created_at, mo.ended_at
        ");
        $stmt->execute();
        
        logMessage('Updated operation statistics', 'info');
        
    } catch (Exception $e) {
        logMessage('Error generating statistics: ' . $e->getMessage(), 'error');
    }
}