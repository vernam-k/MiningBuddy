<?php
/**
 * Utility Functions for Mining Buddy
 * 
 * This file contains utility functions used throughout the application
 */

// Include the configuration file
require_once __DIR__ . '/../config.php';

/**
 * Initialize the application
 * Sets up session and checks for maintenance mode
 */
function initApp() {
    // Start output buffering to allow header modifications after HTML output
    ob_start();
    
    // Start or resume session
    if (session_status() == PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
    
    // Set default headers
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('X-Content-Type-Options: nosniff');
    
    // Check for maintenance mode (could be set in config or DB)
    // if (MAINTENANCE_MODE && !isAdmin()) {
    //     include __DIR__ . '/../maintenance.php';
    //     exit;
    // }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
        exit;
    }
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

/**
 * Get current user data
 * 
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $userId = $_SESSION['user_id'];
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Check if current user is in an active operation
 * 
 * @return bool True if user is in an active operation
 */
function isInActiveOperation() {
    $user = getCurrentUser();
    return !empty($user['active_operation_id']);
}

/**
 * Get current user's active operation
 * 
 * @return array|null Operation data or null if not in an operation
 */
function getCurrentOperation() {
    $user = getCurrentUser();
    if (empty($user['active_operation_id'])) {
        return null;
    }
    
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM mining_operations WHERE operation_id = ?");
    $stmt->execute([$user['active_operation_id']]);
    return $stmt->fetch();
}

/**
 * Check if current user is an admin of their active operation
 * 
 * @return bool True if user is an admin
 */
function isOperationAdmin() {
    $user = getCurrentUser();
    if (empty($user['active_operation_id'])) {
        return false;
    }
    
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT is_admin 
        FROM operation_participants 
        WHERE operation_id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->execute([$user['active_operation_id'], $user['user_id']]);
    $result = $stmt->fetch();
    
    return $result && $result['is_admin'];
}

/**
 * Check if current user is the director of their active operation
 * 
 * @return bool True if user is the director
 */
function isOperationDirector() {
    $user = getCurrentUser();
    if (empty($user['active_operation_id'])) {
        return false;
    }
    
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT director_id 
        FROM mining_operations 
        WHERE operation_id = ?
    ");
    $stmt->execute([$user['active_operation_id']]);
    $result = $stmt->fetch();
    
    return $result && $result['director_id'] == $user['user_id'];
}

/**
 * Redirect to another page
 * 
 * @param string $location Location to redirect to
 */
function redirect($location) {
    header("Location: $location");
    exit;
}

/**
 * Display a flash message
 * 
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 * 
 * @return array|null Flash message data or null if no message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Generate a secure random token
 * 
 * @param int $length Token length
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate a random operation join code
 * 
 * @return string Random join code
 */
function generateJoinCode() {
    // Generate 6 character alphanumeric code
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    // Check if code already exists
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT operation_id FROM mining_operations WHERE join_code = ?");
    $stmt->execute([$code]);
    
    // If code exists, generate a new one recursively
    if ($stmt->fetch()) {
        return generateJoinCode();
    }
    
    return $code;
}

/**
 * Format ISK value with commas and decimal places
 * 
 * @param float $amount Amount to format
 * @param int $decimals Number of decimal places
 * @return string Formatted ISK amount
 */
function formatIsk($amount, $decimals = 2) {
    return number_format($amount, $decimals) . ' ISK';
}

/**
 * Format a date/time string
 * 
 * @param string $dateTime Date/time string
 * @param string $format Output format
 * @return string Formatted date/time
 */
function formatDateTime($dateTime, $format = 'Y-m-d H:i:s') {
    $dt = new DateTime($dateTime);
    return $dt->format($format);
}

/**
 * Calculate time difference in human-readable format
 * 
 * @param string $startTime Start time
 * @param string $endTime End time (defaults to now)
 * @return string Human-readable time difference
 */
function getTimeDifference($startTime, $endTime = null) {
    $start = new DateTime($startTime);
    $end = $endTime ? new DateTime($endTime) : new DateTime();
    $diff = $start->diff($end);
    
    if ($diff->y > 0) {
        return $diff->format('%y years, %m months');
    } else if ($diff->m > 0) {
        return $diff->format('%m months, %d days');
    } else if ($diff->d > 0) {
        return $diff->format('%d days, %h hours');
    } else if ($diff->h > 0) {
        return $diff->format('%h hours, %i minutes');
    } else if ($diff->i > 0) {
        return $diff->format('%i minutes, %s seconds');
    } else {
        return $diff->format('%s seconds');
    }
}

/**
 * Sanitize user input
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Log a message to the application log
 * 
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 */
function logMessage($message, $level = 'info') {
    $logFile = __DIR__ . '/../logs/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Make a request to the EVE ESI API
 * 
 * @param string $endpoint API endpoint
 * @param array $params Query parameters
 * @param string $method HTTP method (GET, POST)
 * @param string $accessToken User access token (optional)
 * @return array API response
 */
function eveApiRequest($endpoint, $params = [], $method = 'GET', $accessToken = null) {
    $url = EVE_ESI_BASE_URL . $endpoint;
    
    // Add parameters to URL for GET requests
    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Set headers
    $headers = ['Accept: application/json'];
    if ($accessToken) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Set POST data
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logMessage("EVE API request error: $error", 'error');
        throw new Exception("EVE API request failed: $error");
    }
    
    $data = json_decode($response, true);
    
    // Check for API errors
    if ($httpCode >= 400) {
        $errorMessage = isset($data['error']) ? $data['error'] : "HTTP Error $httpCode";
        logMessage("EVE API error: $errorMessage", 'error');
        throw new Exception("EVE API error: $errorMessage");
    }
    
    return $data;
}

/**
 * Request an access token from EVE SSO
 * 
 * @param string $authCode Authorization code from EVE SSO
 * @return array Token response
 */
function requestEveAccessToken($authCode) {
    $url = EVE_SSO_TOKEN_URL;
    $headers = [
        'Authorization: Basic ' . base64_encode(EVE_CLIENT_ID . ':' . EVE_SECRET_KEY),
        'Content-Type: application/x-www-form-urlencoded',
        'Host: login.eveonline.com'
    ];
    
    $postData = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $authCode
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logMessage("EVE SSO token request error: $error", 'error');
        throw new Exception("EVE SSO token request failed: $error");
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 400 || isset($data['error'])) {
        $errorMessage = isset($data['error']) ? $data['error'] : "HTTP Error $httpCode";
        logMessage("EVE SSO token error: $errorMessage", 'error');
        throw new Exception("EVE SSO token error: $errorMessage");
    }
    
    return $data;
}

/**
 * Refresh an EVE access token
 * 
 * @param string $refreshToken Refresh token
 * @return array Token response
 */
function refreshEveAccessToken($refreshToken) {
    $url = EVE_SSO_TOKEN_URL;
    $headers = [
        'Authorization: Basic ' . base64_encode(EVE_CLIENT_ID . ':' . EVE_SECRET_KEY),
        'Content-Type: application/x-www-form-urlencoded',
        'Host: login.eveonline.com'
    ];
    
    $postData = http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logMessage("EVE SSO token refresh error: $error", 'error');
        throw new Exception("EVE SSO token refresh failed: $error");
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 400 || isset($data['error'])) {
        $errorMessage = isset($data['error']) ? $data['error'] : "HTTP Error $httpCode";
        logMessage("EVE SSO token refresh error: $errorMessage", 'error');
        throw new Exception("EVE SSO token refresh error: $errorMessage");
    }
    
    return $data;
}

/**
 * Verify an EVE access token and get character information
 * 
 * @param string $accessToken Access token
 * @return array Character information
 */
function verifyEveAccessToken($accessToken) {
    $url = EVE_SSO_VERIFY_URL;
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logMessage("EVE SSO verify error: $error", 'error');
        throw new Exception("EVE SSO verify failed: $error");
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode >= 400 || isset($data['error'])) {
        $errorMessage = isset($data['error']) ? $data['error'] : "HTTP Error $httpCode";
        logMessage("EVE SSO verify error: $errorMessage", 'error');
        throw new Exception("EVE SSO verify error: $errorMessage");
    }
    
    return $data;
}

/**
 * Get character information from EVE API
 * 
 * @param int $characterId Character ID
 * @param string $accessToken Access token
 * @return array Character information
 */
function getCharacterInfo($characterId, $accessToken) {
    return eveApiRequest("/characters/$characterId/", [], 'GET', $accessToken);
}

/**
 * Get character portrait URL
 * 
 * @param int $characterId Character ID
 * @param string $accessToken Access token
 * @return string Portrait URL
 */
function getCharacterPortrait($characterId, $accessToken) {
    $data = eveApiRequest("/characters/$characterId/portrait/", [], 'GET', $accessToken);
    return $data['px128x128'] ?? null; // Use 128x128 portrait
}

/**
 * Get character corporation history
 * 
 * @param int $characterId Character ID
 * @param string $accessToken Access token
 * @return array Corporation history
 */
function getCharacterCorporationHistory($characterId, $accessToken) {
    return eveApiRequest("/characters/$characterId/corporationhistory/", [], 'GET', $accessToken);
}

/**
 * Get corporation information
 * 
 * @param int $corporationId Corporation ID
 * @param string $accessToken Access token
 * @return array Corporation information
 */
function getCorporationInfo($corporationId, $accessToken) {
    return eveApiRequest("/corporations/$corporationId/", [], 'GET', $accessToken);
}

/**
 * Get corporation logo URL
 * 
 * @param int $corporationId Corporation ID
 * @param string $accessToken Access token
 * @return string Logo URL
 */
function getCorporationLogo($corporationId, $accessToken) {
    $data = eveApiRequest("/corporations/$corporationId/icons/", [], 'GET', $accessToken);
    return $data['px128x128'] ?? null; // Use 128x128 logo
}

/**
 * Get character mining ledger
 * 
 * @param int $characterId Character ID
 * @param string $accessToken Access token
 * @return array Mining ledger data
 */
function getCharacterMiningLedger($characterId, $accessToken) {
    return eveApiRequest("/characters/$characterId/mining/", [], 'GET', $accessToken);
}

/**
 * Get market prices
 * 
 * @param string $accessToken Access token
 * @return array Market prices
 */
function getMarketPrices($accessToken) {
    return eveApiRequest("/markets/prices/", [], 'GET', $accessToken);
}

/**
 * Get Jita market orders for a specific type
 * 
 * @param int $typeId Type ID
 * @param string $accessToken Access token
 * @return array Market orders
 */
function getJitaMarketOrders($typeId, $accessToken) {
    // Region ID 10000002 is The Forge (Jita)
    return eveApiRequest("/markets/10000002/orders/", [
        'type_id' => $typeId,
        'order_type' => 'buy' // Get buy orders
    ], 'GET', $accessToken);
}

/**
 * Get best Jita buy price for a type
 * 
 * @param int $typeId Type ID
 * @param string $accessToken Access token
 * @return float Best buy price
 */
function getBestJitaBuyPrice($typeId, $accessToken) {
    $orders = getJitaMarketOrders($typeId, $accessToken);
    
    // Filter for Jita 4-4 (station ID 60003760)
    $jitaOrders = array_filter($orders, function($order) {
        return $order['location_id'] == 60003760; // Jita IV - Moon 4 - Caldari Navy Assembly Plant
    });
    
    if (empty($jitaOrders)) {
        return 0;
    }
    
    // Find highest buy price
    $bestPrice = 0;
    foreach ($jitaOrders as $order) {
        if ($order['price'] > $bestPrice) {
            $bestPrice = $order['price'];
        }
    }
    
    return $bestPrice;
}

/**
 * Get information about a type (ore, etc)
 * 
 * @param int $typeId Type ID
 * @param string $accessToken Access token
 * @return array Type information
 */
function getTypeInfo($typeId, $accessToken) {
    return eveApiRequest("/universe/types/$typeId/", [], 'GET', $accessToken);
}

/**
 * Update ore type information in database
 * 
 * @param int $typeId Type ID
 * @param string $accessToken Access token
 * @return bool Success
 */
function updateOreTypeInfo($typeId, $accessToken) {
    try {
        $typeInfo = getTypeInfo($typeId, $accessToken);
        
        $db = getDbConnection();
        $stmt = $db->prepare("
            INSERT INTO ore_types (type_id, name, volume, description, last_updated)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                volume = VALUES(volume),
                description = VALUES(description),
                last_updated = NOW()
        ");
        
        $stmt->execute([
            $typeId,
            $typeInfo['name'],
            $typeInfo['volume'] ?? 0,
            $typeInfo['description'] ?? null
        ]);
        
        return true;
    } catch (Exception $e) {
        logMessage("Error updating ore type info: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Update market price for an ore type
 * 
 * @param int $typeId Type ID
 * @param string $accessToken Access token
 * @return bool Success
 */
function updateOreMarketPrice($typeId, $accessToken) {
    try {
        $buyPrice = getBestJitaBuyPrice($typeId, $accessToken);
        
        // Get sell price from market prices endpoint
        $marketPrices = getMarketPrices($accessToken);
        $sellPrice = 0;
        
        foreach ($marketPrices as $price) {
            if ($price['type_id'] == $typeId) {
                $sellPrice = $price['adjusted_price'] ?? 0;
                break;
            }
        }
        
        $db = getDbConnection();
        $stmt = $db->prepare("
            INSERT INTO market_prices (type_id, jita_best_buy, jita_best_sell, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                jita_best_buy = VALUES(jita_best_buy),
                jita_best_sell = VALUES(jita_best_sell),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $typeId,
            $buyPrice,
            $sellPrice
        ]);
        
        return true;
    } catch (Exception $e) {
        logMessage("Error updating ore market price: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Take a snapshot of a user's mining ledger
 * 
 * @param int $operationId Operation ID
 * @param int $userId User ID
 * @param string $accessToken Access token
 * @param string $snapshotType Snapshot type (start, update, end)
 * @return bool Success
 */
function takeMiningLedgerSnapshot($operationId, $userId, $accessToken, $snapshotType = 'update') {
    try {
        $user = getUserById($userId);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $ledger = getCharacterMiningLedger($user['character_id'], $accessToken);
        
        $db = getDbConnection();
        $db->beginTransaction();
        
        foreach ($ledger as $entry) {
            $stmt = $db->prepare("
                INSERT INTO mining_ledger_snapshots (
                    operation_id, user_id, type_id, quantity, 
                    snapshot_time, snapshot_type
                )
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");
            
            $stmt->execute([
                $operationId,
                $userId,
                $entry['type_id'],
                $entry['quantity'],
                $snapshotType
            ]);
            
            // Update ore type information if needed
            updateOreTypeInfoIfNeeded($entry['type_id'], $accessToken);
        }
        
        // Update operation's last mining activity
        $stmt = $db->prepare("
            UPDATE mining_operations 
            SET last_mining_activity = NOW()
            WHERE operation_id = ?
        ");
        $stmt->execute([$operationId]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        logMessage("Error taking mining ledger snapshot: " . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Update ore type information if needed (if older than 1 day)
 * 
 * @param int $typeId Type ID
 * @param string $accessToken Access token
 */
function updateOreTypeInfoIfNeeded($typeId, $accessToken) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT last_updated FROM ore_types 
        WHERE type_id = ?
    ");
    $stmt->execute([$typeId]);
    $oreType = $stmt->fetch();
    
    // If ore type doesn't exist or was last updated more than 1 day ago
    if (!$oreType || strtotime($oreType['last_updated']) < strtotime('-1 day')) {
        updateOreTypeInfo($typeId, $accessToken);
        updateOreMarketPrice($typeId, $accessToken);
    }
}

/**
 * Get user by ID
 * 
 * @param int $userId User ID
 * @return array|null User data
 */
function getUserById($userId) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Get user by character ID
 * 
 * @param int $characterId Character ID
 * @return array|null User data
 */
function getUserByCharacterId($characterId) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE character_id = ?");
    $stmt->execute([$characterId]);
    return $stmt->fetch();
}

/**
 * Create or update user
 * 
 * @param array $userData User data
 * @return int User ID
 */
function createOrUpdateUser($userData) {
    $db = getDbConnection();
    
    // Check if user exists
    $existing = getUserByCharacterId($userData['character_id']);
    
    if ($existing) {
        // Update existing user
        $stmt = $db->prepare("
            UPDATE users SET
                character_name = ?,
                avatar_url = ?,
                corporation_id = ?,
                corporation_name = ?,
                corporation_logo_url = ?,
                access_token = ?,
                refresh_token = ?,
                token_expires = ?,
                last_login = NOW()
            WHERE character_id = ?
        ");
        
        $stmt->execute([
            $userData['character_name'],
            $userData['avatar_url'],
            $userData['corporation_id'],
            $userData['corporation_name'],
            $userData['corporation_logo_url'],
            $userData['access_token'],
            $userData['refresh_token'],
            $userData['token_expires'],
            $userData['character_id']
        ]);
        
        return $existing['user_id'];
    } else {
        // Create new user
        $stmt = $db->prepare("
            INSERT INTO users (
                character_id, character_name, avatar_url,
                corporation_id, corporation_name, corporation_logo_url,
                access_token, refresh_token, token_expires,
                created_at, last_login
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $userData['character_id'],
            $userData['character_name'],
            $userData['avatar_url'],
            $userData['corporation_id'],
            $userData['corporation_name'],
            $userData['corporation_logo_url'],
            $userData['access_token'],
            $userData['refresh_token'],
            $userData['token_expires']
        ]);
        
        return $db->lastInsertId();
    }
}

/**
 * Check if user token needs refresh
 * 
 * @param array $user User data
 * @return bool True if token needs refresh
 */
function tokenNeedsRefresh($user) {
    // Refresh if token expires in less than 5 minutes
    return strtotime($user['token_expires']) - time() < 300;
}

/**
 * Refresh user token if needed
 * 
 * @param int $userId User ID
 * @return bool Success
 */
function refreshUserTokenIfNeeded($userId) {
    $user = getUserById($userId);
    if (!$user) {
        return false;
    }
    
    if (!tokenNeedsRefresh($user)) {
        return true; // No refresh needed
    }
    
    try {
        $tokenData = refreshEveAccessToken($user['refresh_token']);
        
        // Calculate token expiration time
        $expiresIn = $tokenData['expires_in'] ?? 1200; // Default to 20 minutes
        $tokenExpires = date('Y-m-d H:i:s', time() + $expiresIn);
        
        // Update user with new token
        $db = getDbConnection();
        $stmt = $db->prepare("
            UPDATE users SET
                access_token = ?,
                refresh_token = ?,
                token_expires = ?
            WHERE user_id = ?
        ");
        
        $stmt->execute([
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? $user['refresh_token'], // Sometimes refresh token doesn't change
            $tokenExpires,
            $userId
        ]);
        
        return true;
    } catch (Exception $e) {
        logMessage("Error refreshing user token: " . $e->getMessage(), 'error');
        return false;
    }
}