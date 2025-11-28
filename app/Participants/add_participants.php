<?php
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// --- INPUTAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $school = trim($_POST['school']);
    $major = trim($_POST['major']);
    $division_id = $_POST['division_id'];
    $supervisor_id = $_POST['supervisor_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Validasi sederhana
    if (empty($username) || empty($full_name) || empty($email)) {
        die("Semua field wajib diisi!");
    }

    // Cek username/email unik
    $cekUser = fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    if ($cekUser) {
        die("❌ Username atau email sudah digunakan!");
    }

    // --- 1️⃣ Tambah user baru ---
    $default_password = password_hash('password', PASSWORD_DEFAULT);
    executeQuery("
        INSERT INTO users (username, password, email, full_name, role)
        VALUES (?, ?, ?, ?, 'peserta')
    ", [$username, $default_password, $email, $full_name]);

    $user_id = fetchOne("SELECT id FROM users WHERE username = ?", [$username])['id'];

    // --- 2️⃣ Tambah peserta magang ---
    executeQuery("
        INSERT INTO participants (user_id, school, major, division_id, supervisor_id, start_date, end_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif')
    ", [$user_id, $school, $major, $division_id, $supervisor_id, $start_date, $end_date]);

    echo "<h3 style='color:green'>✅ Peserta baru berhasil ditambahkan!</h3>";
    echo "<p>Username: <b>$username</b><br>Password: <b>password</b></p>";
    exit;
}

// --- Ambil data untuk form ---
$divisions = fetchAll("SELECT id, name FROM divisions");
$pembimbing = fetchAll("SELECT id, full_name FROM users WHERE role = 'pembimbing'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Peserta Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="p-5">
<div class="container">
    <h2 class="mb-4">Tambah Peserta Baru</h2>
    <form method="POST">
        <div class="row mb-3">
            <div class="col">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="col">
                <label>Nama Lengkap</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col">
                <label>Sekolah</label>
                <input type="text" name="school" class="form-control" required>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col">
                <label>Jurusan</label>
                <input type="text" name="major" class="form-control" required>
            </div>
            <div class="col">
                <label>Divisi</label>
                <select name="division_id" class="form-select" required>
                    <option value="">Pilih Divisi</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col">
                <label>Pembimbing</label>
                <select name="supervisor_id" class="form-select" required>
                    <option value="">Pilih Pembimbing</option>
                    <?php foreach ($pembimbing as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label>Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col">
                <label>Tanggal Selesai</label>
                <input type="date" name="end_date" class="form-control" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Tambah Peserta</button>
    </form>
</div>
</body>
</html>
