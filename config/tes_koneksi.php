<?php
$host = 'localhost';
$dbname = 'db_Pengawas';
$username = 'sa';
$password = '303030';

try {
    $conn = new PDO("sqlsrv:Server=$host;Database=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Koneksi berhasil ke database: " . $dbname . "<br>";

    $stmt = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📋 Daftar tabel di database:<br>";
    foreach ($tables as $t) {
        echo "- $t<br>";
    }

} catch (PDOException $e) {
    die("❌ Koneksi database gagal: " . $e->getMessage());
}
?>