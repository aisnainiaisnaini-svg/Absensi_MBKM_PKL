<?php
// Migration: remove Question_Text column from Guidance_PKL
// Usage (PowerShell): php .\scripts\remove_question_text_from_guidance_pkl.php

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
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Guidance_PKL' AND COLUMN_NAME = 'Question_Text'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && intval($row['cnt']) == 0) {
        echo "Column Question_Text does not exist on Guidance_PKL.\n";
        exit(0);
    }

    // Drop column
    $sql = "ALTER TABLE dbo.Guidance_PKL DROP COLUMN Question_Text";
    $pdo->exec($sql);
    echo "Column Question_Text from Guidance_PKL dropped successfully.\n";

} catch (PDOException $e) {
    echo "PDO error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>