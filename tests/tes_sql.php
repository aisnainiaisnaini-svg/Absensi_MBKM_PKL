<?php
require BASE_PATH . 'config/database.php';

try {
    $stmt = $conn->query("SELECT TOP 1 * FROM Divisions");
    $row = $stmt->fetch();

    echo "Koneksi BERHASIL!<br>";
    echo "Divisi pertama: " . ($row['Name'] ?? '(tidak ada data)');
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
