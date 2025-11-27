<?php
// Migration: drop the Activity_Reports table
// Usage (PowerShell): php .\scripts\drop_activity_reports_table.php

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = null;
    if (function_exists('getConnection')) {
        $pdo = getConnection();
    } else if (isset($conn) && $conn instanceof PDO) {
        $pdo = $conn;
    }

    if (!$pdo) {
        echo "Cannot find a PDO instance. Ensure `config/database.php` exposes a PDO connection ($conn) or `getConnection()` helper.\n";
        exit(1);
    }

    // Check if table exists
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'Activity_Reports'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && intval($row['cnt']) == 0) {
        echo "Table Activity_Reports does not exist.\n";
        exit(0);
    }

    // Drop table
    $sql = "DROP TABLE dbo.Activity_Reports";
    $pdo->exec($sql);
    echo "Table Activity_Reports dropped successfully.\n";

} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>