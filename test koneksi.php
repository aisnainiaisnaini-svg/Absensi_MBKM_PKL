<?php
require_once 'config/database.php';

try {
    $query = $conn->query("SELECT DB_NAME() AS database_aktif");
    $result = $query->fetch(PDO::FETCH_ASSOC);
    echo "Terkoneksi ke database: " . $result['database_aktif'];
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
