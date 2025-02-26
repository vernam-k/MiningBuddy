<?php
/**
 * Mining Buddy - Homepage
 * 
 * Displays login button and top rankings
 */

// Set page title
$pageTitle = 'Home';

// Include header
require_once 'includes/header.php';

// Get top rankings data
function getTopMiningCorporations($period = 'year', $limit = 3) {
    $db = getDbConnection();
    
    if ($period === 'year') {
        $periodFilter = "WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    } else if ($period === 'month') {
        $periodFilter = "WHERE last_updated >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    } else {
        $periodFilter = "";
    }
    
    $query = "
        SELECT corporation_id, corporation_name, corporation_logo_url, total_isk_mined, current_rank
        FROM corporation_statistics
        $periodFilter
        ORDER BY total_isk_mined DESC
        LIMIT ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getTopMiningDirectors($period = 'year', $limit = 3) {
    $db = getDbConnection();
    
    if ($period === 'year') {
        $periodFilter = "WHERE mo.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    } else if ($period === 'month') {
        $periodFilter = "WHERE mo.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    } else {
        $periodFilter = "";
    }
    
    $query = "
        SELECT 
            u.user_id, 
            u.character_name, 
            u.avatar_url, 
            u.corporation_name, 
            u.corporation_logo_url,
            COUNT(mo.operation_id) AS operations_count,
            SUM(os.total_isk_generated) AS total_isk
        FROM users u
        JOIN mining_operations mo ON u.user_id = mo.director_id
        LEFT JOIN operation_statistics os ON mo.operation_id = os.operation_id
        $periodFilter
        GROUP BY u.user_id
        ORDER BY total_isk DESC
        LIMIT ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getTopMiningOperations($period = 'year', $limit = 10) {
    $db = getDbConnection();
    
    if ($period === 'year') {
        $periodFilter = "WHERE mo.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
    } else if ($period === 'month') {
        $periodFilter = "WHERE mo.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    } else {
        $periodFilter = "";
    }
    
    $query = "
        SELECT 
            mo.operation_id,
            mo.title,
            mo.created_at,
            u.character_name AS director_name,
            u.avatar_url AS director_avatar,
            u.corporation_name AS director_corporation,
            u.corporation_logo_url AS corporation_logo,
            os.total_isk_generated,
            os.participant_count
        FROM mining_operations mo
        JOIN users u ON mo.director_id = u.user_id
        LEFT JOIN operation_statistics os ON mo.operation_id = os.operation_id
        $periodFilter
        ORDER BY os.total_isk_generated DESC
        LIMIT ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Get top rankings
$topCorporationsYear = [];
$topDirectorsYear = [];
$topOperationsMonth = [];
$topDirectorsMonth = [];

// Only try to get data if tables exist (might not on first run)
try {
    $topCorporationsYear = getTopMiningCorporations('year');
    $topDirectorsYear = getTopMiningDirectors('year');
    $topOperationsMonth = getTopMiningOperations('month');
    $topDirectorsMonth = getTopMiningDirectors('month');
} catch (PDOException $e) {
    // Database might not be set up yet, ignore errors
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto text-center mb-5">
            <h1 class="display-4 eve-glow">Mining Buddy</h1>
            <p class="lead text-light">
                Track mining operations, monitor profits, and optimize your fleet's efficiency.
            </p>
            
            <?php if (!isLoggedIn()): ?>
                <div class="mt-5">
                    <a href="login.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i> Login with EVE Online
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-5">
                    <a href="dashboard.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-tachometer-alt me-2"></i> Go to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h4><i class="fas fa-building me-2"></i> Top Mining Corporations This Year</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($topCorporationsYear)): ?>
                        <p class="text-muted text-center">No data available yet.</p>
                    <?php else: ?>
                        <?php foreach ($topCorporationsYear as $index => $corp): ?>
                            <div class="ranking-item">
                                <div class="ranking-position ranking-top-<?= $index + 1 ?>"><?= $index + 1 ?></div>
                                <div class="eve-corporation">
                                    <img src="<?= htmlspecialchars($corp['corporation_logo_url'] ?? 'assets/img/default-corp.png') ?>" 
                                         class="eve-corporation-logo" 
                                         alt="<?= htmlspecialchars($corp['corporation_name']) ?>">
                                    <div class="ranking-details">
                                        <div class="eve-corporation-name"><?= htmlspecialchars($corp['corporation_name']) ?></div>
                                    </div>
                                </div>
                                <div class="ranking-value"><?= formatIsk($corp['total_isk_mined']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h4><i class="fas fa-crown me-2"></i> Top Mining Directors This Year</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($topDirectorsYear)): ?>
                        <p class="text-muted text-center">No data available yet.</p>
                    <?php else: ?>
                        <?php foreach ($topDirectorsYear as $index => $director): ?>
                            <div class="ranking-item">
                                <div class="ranking-position ranking-top-<?= $index + 1 ?>"><?= $index + 1 ?></div>
                                <div class="eve-character">
                                    <img src="<?= htmlspecialchars($director['avatar_url'] ?? 'assets/img/default-avatar.png') ?>" 
                                         class="eve-character-avatar" 
                                         alt="<?= htmlspecialchars($director['character_name']) ?>">
                                    <div class="ranking-details">
                                        <div class="eve-character-name"><?= htmlspecialchars($director['character_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($director['corporation_name']) ?></small>
                                    </div>
                                </div>
                                <div class="ranking-value"><?= formatIsk($director['total_isk']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-rocket me-2"></i> Top Mining Operations This Month</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($topOperationsMonth)): ?>
                        <p class="text-muted text-center">No data available yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Director</th>
                                        <th>Corporation</th>
                                        <th>Participants</th>
                                        <th>Total ISK</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topOperationsMonth as $index => $op): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($op['title']) ?></td>
                                            <td>
                                                <div class="eve-character">
                                                    <img src="<?= htmlspecialchars($op['director_avatar'] ?? 'assets/img/default-avatar.png') ?>" 
                                                         class="eve-character-avatar" 
                                                         alt="<?= htmlspecialchars($op['director_name']) ?>">
                                                    <span><?= htmlspecialchars($op['director_name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="eve-corporation">
                                                    <img src="<?= htmlspecialchars($op['corporation_logo'] ?? 'assets/img/default-corp.png') ?>" 
                                                         class="eve-corporation-logo" 
                                                         alt="<?= htmlspecialchars($op['director_corporation']) ?>">
                                                    <span><?= htmlspecialchars($op['director_corporation']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= $op['participant_count'] ?? 0 ?></td>
                                            <td class="isk-value"><?= formatIsk($op['total_isk_generated'] ?? 0) ?></td>
                                            <td><?= formatDateTime($op['created_at'], 'M d, Y') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-user-tie me-2"></i> Top Mining Directors This Month</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($topDirectorsMonth)): ?>
                        <p class="text-muted text-center">No data available yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Director</th>
                                        <th>Corporation</th>
                                        <th>Operations</th>
                                        <th>Total ISK</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topDirectorsMonth as $index => $director): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <div class="eve-character">
                                                    <img src="<?= htmlspecialchars($director['avatar_url'] ?? 'assets/img/default-avatar.png') ?>" 
                                                         class="eve-character-avatar" 
                                                         alt="<?= htmlspecialchars($director['character_name']) ?>">
                                                    <span><?= htmlspecialchars($director['character_name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="eve-corporation">
                                                    <img src="<?= htmlspecialchars($director['corporation_logo_url'] ?? 'assets/img/default-corp.png') ?>" 
                                                         class="eve-corporation-logo" 
                                                         alt="<?= htmlspecialchars($director['corporation_name']) ?>">
                                                    <span><?= htmlspecialchars($director['corporation_name']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= $director['operations_count'] ?? 0 ?></td>
                                            <td class="isk-value"><?= formatIsk($director['total_isk'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>