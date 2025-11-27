<?php
// Migration: update Preferred_Day column in Guidance_PKL to DATETIME2
// Usage (PowerShell): php .\scripts\update_guidance_preferred_day_to_datetime.php

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

    // Check if column is already DATETIME2
    $stmt = $pdo->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Guidance_PKL' AND COLUMN_NAME = 'Preferred_Day'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && strtolower($row['DATA_TYPE']) === 'datetime2') {
        echo "Column Preferred_Day is already DATETIME2 on Guidance_PKL.\n";
        exit(0);
    }

    // Alter column
    // Drop CHECK constraint first if it exists
    $check_constraint_exists = $pdo->prepare(
        "SELECT COUNT(*)\n        FROM sys.check_constraints\n        WHERE parent_object_id = OBJECT_ID('dbo.Guidance_PKL')\n        AND name = 'CK_GuidancePKL_DayOfWeek'"
    );
    $check_constraint_exists->execute();
    if ($check_constraint_exists->fetchColumn() > 0) {
        $pdo->exec("ALTER TABLE dbo.Guidance_PKL DROP CONSTRAINT CK_GuidancePKL_DayOfWeek");
        echo "Dropped CHECK constraint CK_GuidancePKL_DayOfWeek from Guidance_PKL.\n";
    }


    $sql_preferred_day = "ALTER TABLE dbo.Guidance_PKL ALTER COLUMN Preferred_Day DATETIME2 NULL";
    $pdo->exec($sql_preferred_day);
    echo "Column Preferred_Day in Guidance_PKL updated to DATETIME2 NULL successfully.\n";

} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
