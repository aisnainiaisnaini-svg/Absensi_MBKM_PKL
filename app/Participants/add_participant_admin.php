<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<h3 style='color:red;'>Akses ditolak! Halaman ini hanya untuk admin.</h3>");
}

// Ambil daftar divisi untuk dropdown
$divisions = fetchAll("SELECT * FROM divisions");

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $school = trim($_POST['school']);
    $major = trim($_POST['major']);
    $division_id = $_POST['division_id'];
    $username = strtolower(str_replace(' ', '', $full_name)); // buat username otomatis dari nama
    $password_plain = 'password'; // default password
    $password_hashed = password_hash($password_plain, PASSWORD_BCRYPT);

    // Pastikan username unik
    $base_username = $username;
    $counter = 1;
    while (fetchOne("SELECT id FROM users WHERE username = ?", [$username])) {
        $username = $base_username . $counter;
        $counter++;
    }

    // Cek email unik
    if (fetchOne("SELECT id FROM users WHERE email = ?", [$email])) {
        echo "<p style='color:red;'>❌ Email sudah digunakan!</p>";
    } else {
        // Tambahkan ke tabel users
        executeQuery(
            "INSERT INTO users (username, password, email, full_name, role)
             VALUES (?, ?, ?, ?, 'peserta')",
            [$username, $password_hashed, $email, $full_name]
        );

        // Ambil ID user yang baru dibuat
        $user = fetchOne("SELECT id FROM users WHERE username = ?", [$username]);

        // Tambahkan ke tabel participants
        executeQuery(
            "INSERT INTO participants (user_id, school, major, division_id, status)
             VALUES (?, ?, ?, ?, 'aktif')",
            [$user['id'], $school, $major, $division_id]
        );

        echo "<p style='color:green;'>✅ Peserta <b>$full_name</b> berhasil ditambahkan!<br>
              Username: <b>$username</b> | Password: <b>$password_plain</b></p>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Peserta Baru</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<style>
    body { font-family: Arial; background: #f5f5f5; padding: 30px; }
    h2 { color: #333; }
    form {
        background: white;
        padding: 20px;
        border-radius: 10px;
        width: 400px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    input, select {
        width: 100%;
        padding: 8px;
        margin: 5px 0 15px 0;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    button {
        padding: 10px 15px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    button:hover { background: #45a049; }
</style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<h2>🧑‍💻 Tambah Peserta Baru</h2>

<form method="POST">
    <label>Nama Lengkap:</label>
    <input type="text" name="full_name" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Sekolah:</label>
    <input type="text" name="school" required>

    <label>Jurusan:</label>
    <input type="text" name="major" required>

    <label>Divisi:</label>
    <select name="division_id" required>
        <option value="">-- Pilih Divisi --</option>
        <?php foreach ($divisions as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Simpan Peserta</button>
</form>

<p><a href="../Attendance/attendance.php">← Kembali ke halaman absensi</a></p>

</body>
</html>
