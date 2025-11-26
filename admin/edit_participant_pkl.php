<?php
session_start();
require_once '../config/database.php';

// Hanya untuk admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Ambil parameter
$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    die('Parameter tidak valid!');
}

// Ambil data peserta PKL berdasarkan user_id
$participant = fetchOne(
    "
    SELECT 
        p.*, 
        u.full_name, 
        u.email,
        u.username,
        u.role AS user_role,
        d.name AS division_name
    FROM participants p
    JOIN users u ON p.user_id = u.id
    JOIN divisions d ON d.id = p.division_id
    WHERE p.user_id = ?
",
    [$user_id],
);

if (!$participant || $participant['user_role'] !== 'siswa_pkl') {
    die('Peserta PKL tidak ditemukan!');
}

// Ambil semua divisi
$divisions = fetchAll('SELECT id, name FROM divisions ORDER BY name ASC');

// PROSES UPDATE
$message = '';
$message_type = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $school = $_POST['school'];
    $major = $_POST['major'];
    $division_id = $_POST['division_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    $company_supervisor = $_POST['company_supervisor'] ?? null;
    $school_supervisor = $_POST['school_supervisor'] ?? null;

    // Update ke tabel users
    executeQuery(
        "
        UPDATE users 
        SET full_name = ?, email = ?
        WHERE id = ?
    ",
        [$full_name, $email, $user_id],
    );

    // Update ke tabel participants
    executeQuery(
        "
        UPDATE participants
        SET school = ?, major = ?, division_id = ?, start_date = ?, end_date = ?, status = ?,
            company_supervisor = ?, school_supervisor = ?
        WHERE user_id = ?
    ",
        [$school, $major, $division_id, $start_date, $end_date, $status, $company_supervisor, $school_supervisor, $user_id],
    );

    $message = 'Data siswa PKL berhasil diperbarui!';
    $message_type = 'success';

    // Refresh data
    $participant = fetchOne(
        "
        SELECT 
            p.*, 
            u.full_name, 
            u.email,
            u.username,
            u.role AS user_role,
            d.name AS division_name
        FROM participants p
        JOIN users u ON p.user_id = u.id
        JOIN divisions d ON d.id = p.division_id
        WHERE p.user_id = ?
    ",
        [$user_id],
    );
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Siswa PKL - Admin Panel</title>
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
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users-cog me-2"></i>Kelola User
                    </a>
                    <a class="nav-link" href="participants.php">
                        <i class="fas fa-user-graduate me-2"></i>Kelola Peserta
                    </a>
                    <a class="nav-link" href="participants_mbkm.php">
                        <i class="fas fa-user me-2"></i>Kelola MBKM
                    </a>
                    <a class="nav-link active" href="participants_pkl.php">
                        <i class="fas fa-user me-2"></i>Kelola PKL
                    </a>
                    <a class="nav-link" href="bimbingan_pkl.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL
                    </a>
                    <a class="nav-link" href="../leave_approval.php">
                        <i class="fas fa-check-circle me-2"></i>Persetujuan Izin
                    </a>
                    <a class="nav-link" href="../reports_review.php">
                        <i class="fas fa-clipboard-check me-2"></i>Review Laporan
                    </a>
                    <a class="nav-link" href="divisions.php">
                        <i class="fas fa-building me-2"></i>Kelola Divisi
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i>Laporan Sistem
                    </a>
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
                            <h2>
                                <i class="fas fa-user-edit me-2"></i>
                                Edit Siswa PKL
                            </h2>
                            <p class="text-muted mb-0">Perbarui data siswa PKL</p>
                        </div>
                        <a href="participants_pkl.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Form Card -->
                    <div class="form-card">
                        <form method="POST">
                            <input type="hidden" name="action" value="update">

                            <h5 class="mb-3">
                                <i class="fas fa-user me-2"></i>Data Akun
                            </h5>

                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" name="full_name"
                                        value="<?= htmlspecialchars($participant['full_name']) ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email"
                                        value="<?= htmlspecialchars($participant['email']) ?>" required>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-3">
                                <i class="fas fa-school me-2"></i>Data Siswa PKL
                            </h5>

                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Asal Sekolah</label>
                                    <input type="text" class="form-control" name="school"
                                        value="<?= htmlspecialchars($participant['school']) ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Jurusan</label>
                                    <input type="text" class="form-control" name="major"
                                        value="<?= htmlspecialchars($participant['major']) ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Divisi</label>
                                    <select name="division_id" class="form-select" required>
                                        <?php foreach ($divisions as $d): ?>
                                        <option value="<?= $d['id'] ?>"
                                            <?= $participant['division_id'] == $d['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Tanggal Mulai</label>
                                    <input type="date" class="form-control" name="start_date"
                                        value="<?= $participant['start_date'] ?>" required>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Tanggal Selesai</label>
                                    <input type="date" class="form-control" name="end_date"
                                        value="<?= $participant['end_date'] ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="aktif"
                                            <?= $participant['status'] == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="selesai"
                                            <?= $participant['status'] == 'selesai' ? 'selected' : '' ?>>Selesai
                                        </option>
                                        <option value="dikeluarkan"
                                            <?= $participant['status'] == 'dikeluarkan' ? 'selected' : '' ?>>Dikeluarkan
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pembimbing Perusahaan</label>
                                    <input type="text" class="form-control" name="company_supervisor"
                                        value="<?= htmlspecialchars($participant['company_supervisor'] ?? '') ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pembimbing Sekolah</label>
                                    <input type="text" class="form-control" name="school_supervisor"
                                        value="<?= htmlspecialchars($participant['school_supervisor'] ?? '') ?>">
                                </div>
                            </div>

                            <button class="btn btn-submit">
                                <i class="fas fa-save me-2"></i>
                                Simpan Perubahan
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
