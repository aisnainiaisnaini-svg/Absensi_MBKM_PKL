<?php
// Konfigurasi Database

// $host = 'localhost';
$host = 'localhost';
$dbname = 'db_Pengawas';
$username = 'Ais';
$password = '123';

try {
    // Koneksi ke SQL Server
    $conn = new PDO("sqlsrv:Server=$host;Database=$dbname;TrustServerCertificate=yes;Encrypt=no", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

/**
 * Jalankan query (INSERT, UPDATE, DELETE)
 * @return bool
 */
function executeQuery($sql, $params = []) {
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        die("Query gagal: " . $e->getMessage());
    }
}

/**
 * Ambil semua data (SELECT banyak baris)
 * @return array
 */
function fetchAll($sql, $params = []) {
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Query gagal: " . $e->getMessage());
    }
}

/**
 * Ambil satu data (SELECT satu baris)
 * @return array|null
 */
function fetchOne($sql, $params = []) {
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        die("Query gagal: " . $e->getMessage());
    }
}
?>
