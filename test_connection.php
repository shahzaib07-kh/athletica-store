<?php
/**
 * ATHLETICA — Database Connection Test
 * 
 * Run this file ONCE to verify that PHP can connect to the
 * ATHLETICA MySQL database. Then DELETE this file.
 *
 * Usage (terminal):
 *   php test_connection.php
 *
 * Usage (browser via XAMPP/WAMP):
 *   http://localhost/athletica/test_connection.php
 *
 * WARNING:
 *   This file is for DEVELOPMENT ONLY.
 *   DELETE it before deploying to production.
 */

echo "===========================================\n";
echo " ATHLETICA — Database Connection Test\n";
echo "===========================================\n\n";

// --------------------------------------------------------
// Step 1: Load the database configuration
// --------------------------------------------------------
echo "[1] Loading config/database.php ... ";

$configPath = __DIR__ . '/config/database.php';

if (!file_exists($configPath)) {
    echo "FAILED\n\n";
    echo "ERROR: config/database.php not found.\n";
    echo "Make sure you are running this file from the project root:\n";
    echo "  php test_connection.php\n";
    exit(1);
}

require $configPath;

echo "OK\n";

// --------------------------------------------------------
// Step 2: Verify PDO connection exists
// --------------------------------------------------------
echo "[2] Checking PDO connection ... ";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "FAILED\n\n";
    echo "ERROR: \$pdo is not a valid PDO instance.\n";
    echo "Check config/database.php for errors.\n";
    exit(1);
}

echo "OK\n";

// --------------------------------------------------------
// Step 3: Verify the database is reachable
// --------------------------------------------------------
echo "[3] Running test query (SELECT 1) ... ";

try {
    $result = $pdo->query('SELECT 1 AS connection_test')->fetch();
    if ($result && $result['connection_test'] == 1) {
        echo "OK\n";
    } else {
        echo "FAILED (unexpected result)\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "FAILED\n\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// --------------------------------------------------------
// Step 4: Verify ATHLETICA tables exist
// --------------------------------------------------------
echo "[4] Checking ATHLETICA tables ... ";

try {
    $stmt   = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredTables = [
        'admins',
        'categories',
        'products',
        'product_images',
        'orders',
        'order_items',
        'site_settings',
    ];

    $missing = array_diff($requiredTables, $tables);

    if (count($missing) > 0) {
        echo "WARNING\n\n";
        echo "The following tables are missing:\n";
        foreach ($missing as $table) {
            echo "  - $table\n";
        }
        echo "\nRun schema.sql first:\n";
        echo "  mysql -u root -p athletica < database/schema.sql\n\n";
    } else {
        echo "OK (" . count($requiredTables) . "/" . count($requiredTables) . " tables found)\n";
    }
} catch (PDOException $e) {
    echo "FAILED\n\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// --------------------------------------------------------
// Step 5: Verify charset
// --------------------------------------------------------
echo "[5] Checking charset (utf8mb4) ... ";

try {
    $charset = $pdo->query("SELECT @@character_set_connection AS charset")->fetch();
    if ($charset['charset'] === 'utf8mb4') {
        echo "OK\n";
    } else {
        echo "WARNING (got: " . $charset['charset'] . ", expected: utf8mb4)\n";
    }
} catch (PDOException $e) {
    echo "FAILED\n\n";
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// --------------------------------------------------------
// Summary
// --------------------------------------------------------
echo "\n===========================================\n";
echo " ALL CHECKS PASSED\n";
echo " PHP " . PHP_VERSION . " -> MySQL -> athletica\n";
echo "===========================================\n";
echo "\n";
echo "Connection is working. You can now:\n";
echo "  1. Run seed data:  mysql -u root -p athletica < database/seed.sql\n";
echo "  2. DELETE this test file before going to production.\n";
echo "\n";
