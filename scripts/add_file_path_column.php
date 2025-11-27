<?php
// Migration: add File_Path column to Leave_Requests if missing
// Usage (PowerShell): php .\scripts\add_file_path_column.php

require_once __DIR__ . '/../config/database.php';

try {
    if (function_exists('getConnection')) {
        $pdo = getConnection();
    } else if (isset($pdo) && $pdo instanceof PDO) {
        // ok, pdo is already set
    } else if (isset($conn) && $conn instanceof PDO) {
        // use $conn from config/database.php
        $pdo = $conn;
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
