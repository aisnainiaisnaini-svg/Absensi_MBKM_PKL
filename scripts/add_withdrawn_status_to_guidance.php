<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Attempt to drop the existing constraint
    $sql_drop = "ALTER TABLE dbo.Guidance_PKL DROP CONSTRAINT CK_GuidancePKL_Status;";
    $conn->exec($sql_drop);
    echo "Existing constraint CK_GuidancePKL_Status dropped successfully.\n";
} catch (PDOException $e) {
    // It might fail if the constraint doesn't exist, which is fine.
    echo "Could not drop constraint (it might not exist, which is okay).\n";
    echo "Error: " . $e->getMessage() . "\n";
}

try {
    // Add the new constraint with the 'ditarik' status
    $sql_add = "ALTER TABLE dbo.Guidance_PKL ADD CONSTRAINT CK_GuidancePKL_Status CHECK ([Status] IN ('pending','diproses','selesai','ditarik'));";
    $conn->exec($sql_add);
    echo "New constraint CK_GuidancePKL_Status added successfully with 'ditarik' status.\n";
    echo "Migration successful.\n";
} catch (PDOException $e) {
    echo "Failed to add the new constraint.\n";
    die("Error: " . $e->getMessage() . "\n");
}

