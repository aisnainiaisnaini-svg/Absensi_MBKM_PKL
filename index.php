<?php
session_start();
require_once 'config/database.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$message = '';
$message_type = '';

/*
 |==================================================
 | LOGIN PROSES
 |==================================================
*/
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        // Alias required → SQL Server uses PascalCase so we normalize it
        $user = fetchOne(
            "
            SELECT 
                Id         AS id,
                Username   AS username,
                [Password] AS password,
                Full_Name  AS full_name,
                [Role]     AS role
            FROM Users
            WHERE Username = ?
        ",
            [$username],
        );

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Username atau password salah!';
        }
    } else {
        $error = 'Username dan password harus diisi!';
    }
}

/*
 |==================================================
 | ADD USER (ADMIN)
 |==================================================
*/
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    // Role allowed: admin, mahasiswa_mbkm, siswa_pkl
    $allowed_roles = ['admin', 'mahasiswa_mbkm', 'siswa_pkl'];

    if (!in_array($role, $allowed_roles)) {
        $message = 'Role tidak valid!';
        $message_type = 'danger';
    }

    if ($username && $email && $full_name && $role && $password) {
        // Cek duplikasi username/email
        $existing_user = fetchOne(
            "
            SELECT Id 
            FROM Users 
            WHERE Username = ? OR Email = ?
        ",
            [$username, $email],
        );

        if ($existing_user) {
            $message = 'Username atau email sudah digunakan!';
            $message_type = 'danger';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            executeQuery(
                "
                INSERT INTO Users (Username, Email, Full_Name, [Role], [Password])
                VALUES (?, ?, ?, ?, ?)
            ",
                [$username, $email, $full_name, $role, $hashed_password],
            );

            // Ambil id
            $new_user = fetchOne('SELECT Id FROM Users WHERE Username = ?', [$username]);
            $new_user_id = $new_user['id'] ?? null;

            /*
             |=========================
             | Insert ke Participants
             |=========================
             | mahasiswa_mbkm membutuhkan peserta + laporan
             | siswa_pkl membutuhkan peserta + bimbingan
             */
            // if ($new_user_id) {

            //     if ($role === 'mahasiswa_mbkm') {

            //         // Mahasiswa MBKM tetap punya participants
            //         executeQuery("
            //             INSERT INTO Participants (User_Id, School, Major, Division_Id, Status)
            //             VALUES (?, '-', '-', 1, 'aktif')
            //         ", [$new_user_id]);

            //     } else if ($role === 'siswa_pkl') {

            //         // Siswa PKL juga peserta, tapi nanti halaman laporan diganti bimbingan
            //         executeQuery("
            //             INSERT INTO Participants (User_Id, School, Major, Division_Id, Status)
            //             VALUES (?, '-', '-', 1, 'aktif')
            //         ", [$new_user_id]);
            //     }
            // }

            $message = 'User ' . htmlspecialchars($username) . ' berhasil ditambahkan!';
            $message_type = 'success';
        }
    } else {
        $message = 'Semua field harus diisi!';
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Aplikasi Pengawasan Magang/PKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="login-header">
                        <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                        <h3>Aplikasi Pengawasan</h3>
                        <p class="mb-0">Magang & PKL</p>
                    </div>
                    <div class="card-body p-4">

                        <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>

                        <!-- FORM LOGIN -->
                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>

                        <div class="mt-4 text-center">
                            <small class="text-muted">
                                <strong>Demo Login:</strong><br>
                                Admin: admin / password<br>
                                <!-- Pembimbing: pembimbing1 / password -->
                            </small>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
