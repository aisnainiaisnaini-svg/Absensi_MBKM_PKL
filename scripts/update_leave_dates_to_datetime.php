<?php
// Migration: update Start_Date and End_Date columns in Leave_Requests to DATETIME2
// Usage (PowerShell): php .\scripts\update_leave_dates_to_datetime.php

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

    // Check if columns are already DATETIME2
    $stmt = $pdo->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Leave_Requests' AND COLUMN_NAME = 'Start_Date'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && strtolower($row['DATA_TYPE']) === 'datetime2') {
        echo "Columns Start_Date and End_Date are already DATETIME2 on Leave_Requests.\n";
        exit(0);
    }

    // Alter columns
    $sql_start_date = "ALTER TABLE dbo.Leave_Requests ALTER COLUMN Start_Date DATETIME2 NOT NULL";
    $pdo->exec($sql_start_date);
    echo "Column Start_Date in Leave_Requests updated to DATETIME2 successfully.\n";

    $sql_end_date = "ALTER TABLE dbo.Leave_Requests ALTER COLUMN End_Date DATETIME2 NOT NULL";
    $pdo->exec($sql_end_date);
    echo "Column End_Date in Leave_Requests updated to DATETIME2 successfully.\n";

} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
