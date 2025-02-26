<?php
/**
 * Configuration File for Mining Buddy
 * 
 * This file contains all configuration settings for the application
 * including database credentials and EVE Online API settings.
 * 
 * IMPORTANT: In production, this file should be kept secure and not 
 * accessible via the web server.
 */

// Application settings
define('APP_NAME', 'Mining Buddy');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/MiningBuddy'); // Change in production
define('DEBUG_MODE', true); // Set to false in production

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'mining_buddy');
define('DB_USER', 'root');
define('DB_PASS', ''); // Add your password in production
define('DB_CHARSET', 'utf8mb4');

// EVE Online API settings
define('EVE_CLIENT_ID', ''); // Add your EVE Online Client ID
define('EVE_SECRET_KEY', ''); // Add your EVE Online Secret Key
define('EVE_CALLBACK_URL', APP_URL . '/callback.php');
define('EVE_SCOPES', 'esi-industry.read_character_mining.v1 esi-markets.structure_markets.v1 esi-universe.read_structures.v1');

// EVE ESI API endpoints
define('EVE_ESI_BASE_URL', 'https://esi.evetech.net/latest');
define('EVE_SSO_AUTH_URL', 'https://login.eveonline.com/v2/oauth/authorize/');
define('EVE_SSO_TOKEN_URL', 'https://login.eveonline.com/v2/oauth/token');
define('EVE_SSO_VERIFY_URL', 'https://login.eveonline.com/oauth/verify');

// Application settings
define('TOKEN_REFRESH_INTERVAL', 15 * 60); // 15 minutes in seconds
define('MINING_DATA_REFRESH_INTERVAL', 10 * 60); // 10 minutes in seconds
define('MARKET_PRICES_REFRESH_INTERVAL', 30 * 60); // 30 minutes in seconds
define('OPERATION_INACTIVITY_THRESHOLD', 2 * 60 * 60); // 2 hours in seconds

// Session settings
define('SESSION_NAME', 'mining_buddy_session');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if(!DEBUG_MODE) {
    ini_set('session.cookie_secure', 1); // Require HTTPS in production
}

// Error handling in development
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

// Timezone settings
date_default_timezone_set('UTC'); // EVE Online uses UTC

// Database connection function
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Database Connection Error: " . $e->getMessage());
        } else {
            die("Database connection error. Please contact the administrator.");
        }
    }
}