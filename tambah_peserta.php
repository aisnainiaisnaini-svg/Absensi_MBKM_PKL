<?php
require_once 'config/database.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? 'peserta');
    $password = trim($_POST['password'] ?? '');

    if ($username && $email && $full_name && $password) {
        try {
            // ✅ Enkripsi password pakai bcrypt
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert ke database
            $sql = "INSERT INTO users (username, password, email, full_name, role) 
                    VALUES (?, ?, ?, ?, ?)";
            executeQuery($sql, [$username, $hashedPassword, $email, $full_name, $role]);

            $success = "Peserta berhasil ditambahkan!";
        } catch (PDOException $e) {
            $error = "Gagal menambahkan peserta: " . $e->getMessage();
        }
    } else {
        $error = "Semua field harus diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Peserta - Aplikasi Pengawasan Magang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: bold;
            border: none;
        }
        .btn-submit:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card p-4">
                <h4 class="text-center mb-4">Tambah Peserta Baru</h4>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Nama Lengkap</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Role</label>
                        <select name="role" class="form-select">
                            <option value="peserta">Peserta</option>
                            <option value="pembimbing">Pembimbing</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-submit w-100">Tambah Peserta</button>
                </form>

                <div class="text-center mt-3">
                    <a href="index.php" class="text-decoration-none">← Kembali ke Login</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
