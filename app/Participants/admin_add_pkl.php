<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// Hanya untuk admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$message = '';
$message_type = '';

// PROSES SIMPAN
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_pkl') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $password = $_POST['password'] ?? '';
    $school = $_POST['school'] ?? '';
    $major = $_POST['major'] ?? '';
    $division = $_POST['division_id'] ?? '';
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $company = $_POST['company_supervisor'] ?? '';
    $school_sup = $_POST['school_supervisor'] ?? '';

    if ($username && $email && $full_name && $password && $division && $start && $end && $school && $major) {
        // Cek username/email duplicate
        $exist = fetchOne('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email]);

        if ($exist) {
            $message = 'Username atau email sudah digunakan!';
            $message_type = 'danger';
        } else {
            // Buat user
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            executeQuery(
                "
                INSERT INTO users (username, email, full_name, role, password)
                VALUES (?, ?, ?, 'siswa_pkl', ?)
            ",
                [$username, $email, $full_name, $hashed],
            );

            // Ambil ID user baru
            $new_user = fetchOne('SELECT id FROM users WHERE username = ?', [$username]);
            $user_id = $new_user['id'];

            // Insert ke participants
            executeQuery(
                "
                INSERT INTO participants 
                (user_id, school, major, division_id, start_date, end_date, status,
                 company_supervisor, school_supervisor)
                VALUES (?, ?, ?, ?, ?, ?, 'aktif', ?, ?)
            ",
                [$user_id, $school, $major, $division, $start, $end, $company, $school_sup],
            );

            // Redirect ke list PKL
            header('Location: ' . APP_URL . '/app/Participants/admin_participants_pkl.php?success=1');
            exit();
        }
    } else {
        $message = 'Semua field wajib diisi!';
        $message_type = 'danger';
    }
}

// Ambil semua divisi
$divisions = fetchAll('SELECT id, name FROM divisions ORDER BY name ASC');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tambah Siswa PKL - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-radius: 10px;
            margin: 5px 10px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }

        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: transform 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-cogs me-2"></i>
                        Admin Panel
                    </h4>
                </div>

                <nav class="nav flex-column">
                    <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/users.php">
                        <i class="fas fa-users-cog me-2"></i>Kelola User
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php">
                        <i class="fas fa-user-graduate me-2"></i>Kelola Peserta
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_mbkm.php">
                        <i class="fas fa-user me-2"></i>Kelola MBKM
                    </a>
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php">
                        <i class="fas fa-user me-2"></i>Kelola PKL
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/admin_bimbingan_pkl.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
                        <i class="fas fa-check-circle me-2"></i>Persetujuan Izin
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/reports_review.php">
                        <i class="fas fa-clipboard-check me-2"></i>Review Laporan
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/divisions.php">
                        <i class="fas fa-building me-2"></i>Kelola Divisi
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/admin_reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Laporan Sistem
                    </a>
                    <hr class="my-3">
                    <a class="nav-link" href="<?= APP_URL ?>/public/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">
                                <i class="fas fa-user-plus me-2"></i>Tambah Siswa PKL
                            </h2>
                            <p class="text-muted mb-0">Form untuk menambahkan data akun dan peserta PKL baru</p>
                        </div>
                        <a href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                        <i
                            class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Form Card -->
                    <div class="form-card">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_pkl">

                            <!-- Data Akun -->
                            <h5 class="mb-3">
                                <i class="fas fa-user-circle me-2"></i>Data Akun
                            </h5>
                            <div class="row mb-3">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" name="full_name" class="form-control" required>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Data Siswa PKL -->
                            <h5 class="mb-3">
                                <i class="fas fa-school me-2"></i>Data Siswa PKL
                            </h5>

                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Asal Sekolah</label>
                                    <input type="text" name="school" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jurusan</label>
                                    <input type="text" name="major" class="form-control" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Divisi</label>
                                    <select name="division_id" class="form-select" required>
                                        <option value="">-- Pilih Divisi --</option>
                                        <?php foreach ($divisions as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tanggal Mulai</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tanggal Selesai</label>
                                    <input type="date" name="end_date" class="form-control" required>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Data Pembimbing -->
                            <h5 class="mb-3">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Data Pembimbing
                            </h5>

                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pembimbing Perusahaan</label>
                                    <input type="text" name="company_supervisor" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pembimbing Sekolah</label>
                                    <input type="text" name="school_supervisor" class="form-control">
                                </div>
                            </div>

                            <button class="btn btn-submit px-4">
                                <i class="fas fa-save me-2"></i>Simpan
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>
