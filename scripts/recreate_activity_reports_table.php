<?php
// Migration: recreate the Activity_Reports table
// Usage (PowerShell): php .\scripts\recreate_activity_reports_table.php

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

    if ($row && intval($row['cnt']) > 0) {
        echo "Table Activity_Reports already exists. Skipping recreation.\n";
        exit(0);
    }

    // Create table
    $sql = "
    CREATE TABLE dbo.Activity_Reports (
        Id                  INT IDENTITY(1,1) PRIMARY KEY,
        Participant_Id      INT NOT NULL,
        Report_Date         DATE NOT NULL,
        Title               VARCHAR(200) NOT NULL,
        [Description]       VARCHAR(MAX) NOT NULL,
        File_Path           VARCHAR(255) NULL,
        Supervisor_Comment  VARCHAR(MAX) NULL,
        Rating              INT NULL,
        Created_At          DATETIME2 NOT NULL DEFAULT GETDATE(),   -- FIXED WIB
        Updated_At          DATETIME2 NOT NULL DEFAULT GETDATE(),   -- FIXED WIB

        FOREIGN KEY (Participant_Id) REFERENCES dbo.Participants(Id) ON DELETE CASCADE
    );
    ";
    $pdo->exec($sql);
    echo "Table Activity_Reports recreated successfully.\n";

} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
