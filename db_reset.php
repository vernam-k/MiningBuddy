<?php
/**
 * Mining Buddy - Database Reset Tool
 * 
 * This script wipes and rebuilds the database from schema.sql.
 * It also destroys the current session to prevent errors.
 * 
 * USE WITH CAUTION! All data will be lost.
 */

// Start the session if it hasn't been started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Destroy the current session (log out the user)
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Include configuration
require_once 'config.php';

// Set a flag to confirm the reset action
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
$import = isset($_GET['import']) && $_GET['import'] === 'yes';

// Check if the confirm parameter is provided
if (!$confirm) {
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Database Reset Tool</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #212529; color: #f8f9fa; }
        .warning { background-color: #2c0b0e; color: #f5c2c7; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; }
        .btn:hover { background-color: #c82333; }
        .btn-secondary { background-color: #495057; }
        .btn-secondary:hover { background-color: #343a40; }
        h1, h2 { color: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Database Reset Tool</h1>
    <div class="warning">
        <h2>⚠️ WARNING ⚠️</h2>
        <p>This tool will completely erase all data in the database and rebuild it from the schema file. This action cannot be undone.</p>
        <p>Any active sessions will be destroyed, and all users will need to log in again.</p>
        <p>Are you sure you want to proceed?</p>
    </div>
    <a href="?confirm=yes" class="btn">Wipe Database Only</a>
    <a href="?confirm=yes&import=yes" class="btn">Wipe & Rebuild Schema</a>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
</body>
</html>';
    exit;
}

// Attempt to connect to the database
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Get all tables in the database
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Disable foreign key checks temporarily
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop each table
    $droppedTables = [];
    foreach ($tables as $table) {
        $db->exec("DROP TABLE `$table`");
        $droppedTables[] = $table;
    }
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Import schema if requested
    $schema_imported = false;
    $import_output = "";
    
    if ($import && file_exists('sql/schema.sql')) {
        try {
            // Read the schema file
            $schema = file_get_contents('sql/schema.sql');
            
            // Split the schema into individual statements
            $statements = explode(';', $schema);
            
            // Execute each statement
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $db->exec($statement);
                }
            }
            
            $schema_imported = true;
        } catch (PDOException $e) {
            $import_output = "<div class='error'><h3>❌ Schema Import Error</h3><p>" . 
                htmlspecialchars($e->getMessage()) . "</p></div>";
        }
    }
    
    // Output success message
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Database Reset Complete</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #212529; color: #f8f9fa; }
        .success { background-color: #0f5132; color: #d1e7dd; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .error { background-color: #2c0b0e; color: #f5c2c7; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #198754; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; }
        .btn:hover { background-color: #157347; }
        .btn-secondary { background-color: #0d6efd; }
        .btn-secondary:hover { background-color: #0b5ed7; }
        ul { margin-top: 20px; }
        pre { background-color: #343a40; padding: 15px; border-radius: 5px; overflow-x: auto; color: #f8f9fa; }
        h1, h2, h3 { color: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Database Reset Complete</h1>
    <div class="success">
        <h2>✅ Database Successfully Reset</h2>
        <p>The following tables have been dropped:</p>
        <ul>';
        
    foreach ($droppedTables as $table) {
        echo "<li>$table</li>";
    }
    
    echo '</ul>
    </div>';
    
    if ($import) {
        if ($schema_imported) {
            echo '<div class="success">
                <h3>✅ Schema Successfully Imported</h3>
                <p>The database schema has been rebuilt from sql/schema.sql</p>
            </div>';
        } else {
            echo $import_output != "" ? $import_output : '<div class="error">
                <h3>❌ Schema Import Failed</h3>
                <p>Could not find or read the schema file at sql/schema.sql</p>
            </div>';
        }
    }
    
    echo '<p>Your session has been reset. You will need to log in again.</p>
    
    <div>
        <a href="index.php" class="btn">Go to Homepage</a>
        <a href="login.php" class="btn btn-secondary">Log In</a>
    </div>
    
    <p style="margin-top: 30px;">If you need to manually rebuild the schema, you can run the following command:</p>
    <pre>mysql -u ' . DB_USER . ' -p ' . DB_NAME . ' < sql/schema.sql</pre>
</body>
</html>';
    
} catch (PDOException $e) {
    // Output error message
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Database Reset Error</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background-color: #212529; color: #f8f9fa; }
        .error { background-color: #2c0b0e; color: #f5c2c7; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 5px; }
        .btn:hover { background-color: #5a6268; }
        ul { margin-top: 20px; }
        h1, h2 { color: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Database Reset Error</h1>
    <div class="error">
        <h2>❌ An Error Occurred</h2>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
    </div>
    <p>Please check your database configuration in config.php:</p>
    <ul>
        <li>DB_HOST: ' . DB_HOST . '</li>
        <li>DB_NAME: ' . DB_NAME . '</li>
        <li>DB_USER: ' . DB_USER . '</li>
        <li>DB_CHARSET: ' . DB_CHARSET . '</li>
    </ul>
    <a href="index.php" class="btn">Go to Homepage</a>
</body>
</html>';
}