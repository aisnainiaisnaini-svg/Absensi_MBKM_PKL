<?php
// File debug untuk melihat data user
session_start();
require_once __DIR__ . '/config/app.php';
require_once BASE_PATH . 'config/database.php';

echo "<h2>Debug User Data</h2>";

// Ambil semua user untuk dilihat struktur dan isinya
$users = fetchAll("SELECT * FROM users");

if ($users) {
    echo "<h3>Semua User:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr>";
    if (!empty($users)) {
        foreach (array_keys($users[0]) as $column) {
            echo "<th>" . htmlspecialchars($column) . "</th>";
        }
    }
    echo "</tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        foreach ($user as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Tidak ada data user ditemukan</p>";
}

// Spesifik cari user admin
echo "<h3>Detail User 'admin':</h3>";
$user = fetchOne("SELECT * FROM users WHERE username = 'admin'");
if ($user) {
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    echo "<h4>Analisis:</h4>";
    echo "Username: " . ($user['username'] ?? 'N/A') . "<br>";
    echo "Role: " . ($user['role'] ?? 'N/A') . "<br>";
    echo "Status: " . ($user['status'] ?? 'N/A') . " (Jika kolom 'status' tidak ada, maka lihat kolom lain seperti 'active', 'is_active', atau sejenisnya)<br>";
    echo "Password: " . substr($user['password'], 0, 20) . "..." . " (Panjang: " . strlen($user['password']) . ")<br>";
} else {
    echo "<p>User 'admin' tidak ditemukan</p>";
    echo "<p>Nama-nama user yang terdaftar: ";
    foreach ($users as $u) {
        echo $u['username'] . ", ";
    }
    echo "</p>";
}
?>
<a href="public/">Kembali ke Login</a>