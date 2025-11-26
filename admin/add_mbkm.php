<?php
session_start();
require_once '../config/database.php';

// Hanya untuk admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$message_type = '';

// PROSES SIMPAN
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_mbkm') {
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

    if ($username && $email && $full_name && $password && $division && $start && $end) {
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
                VALUES (?, ?, ?, 'mahasiswa_mbkm', ?)
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

            header('Location: participants_mbkm.php');
            exit();
            // $message = "Mahasiswa MBKM berhasil ditambahkan!";
            // $message_type = "success";
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
    <title>Tambah Mahasiswa MBKM - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
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
            padding: 10px 28px;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .card-section-divider {
            border-top: 1px dashed #dee2e6;
            margin: 1.5rem 0;
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
                    <a class="nav-link" href="participants_mbkm.php">
                        <i class="fas fa-arrow-left me-2"></i>Kembali ke MBKM
                    </a>
                    <hr class="my-3">
                    <a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
                    <a class="nav-link" href="participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola
                        Peserta</a>
                    <a class="nav-link active" href="participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola
                        MBKM</a>
                    <a class="nav-link" href="participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
                    <a class="nav-link" href="bimbingan_pkl.php"><i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan
                        PKL</a>
                    <a class="nav-link" href="../leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan
                        Izin</a>
                    <a class="nav-link" href="../reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review
                        Laporan</a>
                    <a class="nav-link" href="divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
                    <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>
                    <hr class="my-3">
                    <a class="nav-link" href="../logout.php">
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
                                <i class="fas fa-user-plus me-2"></i>Tambah Mahasiswa MBKM
                            </h2>
                            <p class="text-muted mb-0">Lengkapi data akun dan data MBKM mahasiswa</p>
                        </div>
                        <a href="participants_mbkm.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>

                    <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                        <i
                            class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Form Card -->
                    <div class="form-card">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_mbkm">

                            <!-- Data Akun -->
                            <div class="mb-3">
                                <div class="section-title">
                                    <i class="fas fa-user-circle me-2"></i>Data Akun
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="username" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Nama Lengkap</label>
                                        <input type="text" name="full_name" class="form-control" required>
                                    </div>
                                </div>

                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <div class="card-section-divider"></div>

                            <!-- Data Mahasiswa MBKM -->
                            <div class="mb-3">
                                <div class="section-title">
                                    <i class="fas fa-graduation-cap me-2"></i>Data Mahasiswa MBKM
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Asal Kampus / Sekolah</label>
                                        <input type="text" name="school" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Jurusan</label>
                                        <input type="text" name="major" class="form-control" required>
                                    </div>
                                </div>

                                <div class="row g-3 mt-2">
                                    <div class="col-md-4">
                                        <label class="form-label">Divisi</label>
                                        <select name="division_id" class="form-select" required>
                                            <option value="">-- Pilih Divisi --</option>
                                            <?php foreach ($divisions as $d): ?>
                                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tanggal Mulai</label>
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tanggal Selesai</label>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <!-- <div class="card-section-divider"></div> -->

                            <div class="mt-4 d-flex justify-content-end">
                                <button class="btn btn-submit">
                                    <i class="fas fa-save me-2"></i>Simpan Mahasiswa MBKM
                                </button>
                            </div>
                        </form>
                    </div>
                    <!-- /form-card -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
