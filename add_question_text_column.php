<?php
require_once __DIR__ . '/config/database.php';

try {
    // Add the missing Question_Text column to the Guidance_PKL table
    $sql = "ALTER TABLE dbo.Guidance_PKL ADD Question_Text VARCHAR(MAX) NOT NULL DEFAULT ''";
    $conn->exec($sql);
    echo "Column 'Question_Text' added successfully to Guidance_PKL table.";
} catch (PDOException $e) {
    // Check if the error is because the column already exists
    if (strpos($e->getMessage(), 'Column names in each table must be unique') !== false) {
        echo "Column 'Question_Text' already exists in Guidance_PKL table.";
    } else {
        echo "Error adding column: " . $e->getMessage();
    }
}
?>