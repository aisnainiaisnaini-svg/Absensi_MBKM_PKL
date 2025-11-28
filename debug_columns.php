<?php
require_once __DIR__ . '/config/database.php';

// Query to get column information for Guidance_PKL table
try {
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'Guidance_PKL'
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute();
    $columns = $stmt->fetchAll();

    echo "<h2>Columns in Guidance_PKL table:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Column Name</th><th>Data Type</th><th>Nullable</th></tr>";
    foreach ($columns as $column) {
        // Access the columns using lowercase since PDO is configured with CASE_LOWER
        echo "<tr>";
        echo "<td>" . $column['column_name'] . "</td>";
        echo "<td>" . $column['data_type'] . "</td>";
        echo "<td>" . $column['is_nullable'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>