<?php
// Migration: add File_Path column to Leave_Requests if missing
// Usage (PowerShell): php .\scripts\add_file_path_column.php

require_once __DIR__ . '/../config/database.php';

try {
    // Use the same DB connection helper if available, otherwise create new PDO
    if (function_exists('getConnection')) {
        $pdo = getConnection();
    } else {
        // Try to read config from config/database.php (assumes it sets $pdo or similar)
        // Fallback: attempt to use $pdo if defined by included file
        if (isset($pdo) && $pdo instanceof PDO) {
            // ok
        } else {
            // Try to build from config arrays if present
            if (isset($db_dsn) && isset($db_user)) {
                $pdo = new PDO($db_dsn, $db_user, $db_pass ?? null);
            } else {
                // As last resort, create a new PDO using common SQL Server DSN from config/database.php
                // Modify below if your config requires different credentials
                // This script expects config/database.php to make a $pdo available or provide helper.
            }
        }
    }

    if (!isset($pdo) || !$pdo instanceof PDO) {
        echo "Cannot find a PDO instance. Ensure `config/database.php` exposes a PDO connection (\$pdo) or `getConnection()` helper.\n";
        exit(1);
    }

    // Check if column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Leave_Requests' AND COLUMN_NAME = 'File_Path'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && intval($row['cnt']) > 0) {
        echo "Column File_Path already exists on Leave_Requests.\n";
        exit(0);
    }

    // Add column
    $sql = "ALTER TABLE dbo.Leave_Requests ADD File_Path VARCHAR(1000) NULL";
    $pdo->exec($sql);
    echo "Column File_Path added to Leave_Requests successfully.\n";

} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
