<?php
// Migration: remove Description column from Activity_Reports
// Usage (PowerShell): php .\scripts\remove_description_from_activity_reports.php

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

    // Check if column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Activity_Reports' AND COLUMN_NAME = 'Description'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && intval($row['cnt']) == 0) {
        echo "Column Description does not exist on Activity_Reports.\n";
        exit(0);
    }

    // Drop column
    $sql = "ALTER TABLE dbo.Activity_Reports DROP COLUMN [Description]";
    $pdo->exec($sql);
    echo "Column Description from Activity_Reports dropped successfully.\n";

} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>