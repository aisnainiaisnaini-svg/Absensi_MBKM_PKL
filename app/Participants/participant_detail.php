<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$participant_id = $_GET['id'] ?? '';

if (!$participant_id) {
    header('Location: ' . APP_URL . '/public/dashboard.php');
    exit();
}

// Ambil data peserta
$participant = fetchOne("SELECT p.*, u.username, u.email, u.full_name, d.name as division_name,
                         s.full_name as supervisor_name
                         FROM participants p
                         JOIN users u ON p.user_id = u.id
                         JOIN divisions d ON p.division_id = d.id
                         LEFT JOIN users s ON p.supervisor_id = s.id
                         WHERE p.id = ?", [$participant_id]);

if (!$participant) {
    header('Location: dashboard.php');
    exit();
}

// Cek akses berdasarkan role
if ($_SESSION['role'] === 'peserta' && $participant['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php');
    exit();
}

if ($_SESSION['role'] === 'pembimbing' && $participant['supervisor_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php');
    exit();
}

// Ambil statistik kehadiran
$attendance_stats = fetchOne("SELECT
    COUNT(*) as total_days,
    SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as leave_days,
    SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sick_days,
    SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as absent_days
    FROM attendance
    WHERE participant_id = ?", [$participant_id]);

// Ambil riwayat kehadiran terbaru
$recent_attendance = fetchAll("SELECT * FROM attendance
                               WHERE participant_id = ?
                               ORDER BY date DESC
                               LIMIT 10", [$participant_id]);

// Ambil data izin
$leave_requests = fetchAll("SELECT * FROM leave_requests
                           WHERE participant_id = ?
                           ORDER BY created_at DESC
                           LIMIT 5", [$participant_id]);

// Ambil data laporan
$activity_reports = fetchAll("SELECT * FROM activity_reports
                             WHERE participant_id = ?
                             ORDER BY report_date DESC
                             LIMIT 5", [$participant_id]);

// Hitung total jam kerja
$total_work_hours = 0;
foreach ($recent_attendance as $attendance) {
    if ($attendance['check_in'] && $attendance['check_out']) {
        $check_in = strtotime($attendance['check_in']);
        $check_out = strtotime($attendance['check_out']);
        $total_work_hours += ($check_out - $check_in) / 3600;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Peserta - <?= htmlspecialchars($participant['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/drawer.css">
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
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin: 0 auto 15px;
        }
        .bg-primary-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        .bg-warning-gradient {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .bg-info-gradient {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
        }

        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .summary-stats {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            height: fit-content;
        }

        .summary-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: transform 0.2s;
        }

        .summary-item:hover {
            transform: translateY(-2px);
        }

        .summary-item:last-child {
            margin-bottom: 0;
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .summary-content h4 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        .summary-content p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .stat-content h4 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
        }

        .stat-content p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .progress {
            height: 10px;
            border-radius: 5px;
        }

        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .attendance-table {
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .attendance-table tr {
            background: white;
        }

        .attendance-table tr:first-child {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }

        .attendance-table tr:last-child {
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .page-header {
            background: linear-gradient(120deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
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
                        <i class="fas fa-graduation-cap me-2"></i>
                        Magang/PKL
                    </h4>
                </div>

                <nav class="nav flex-column">
                    <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <?php if ($_SESSION['role'] === 'peserta'): ?>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance.php">
                            <i class="fas fa-calendar-check me-2"></i>Absensi Harian
                        </a>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance_history.php">
                            <i class="fas fa-history me-2"></i>Riwayat Kehadiran
                        </a>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_request.php">
                            <i class="fas fa-calendar-times me-2"></i>Ajukan Izin
                        </a>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Reports/activity_report.php">
                            <i class="fas fa-file-alt me-2"></i>Laporan Kegiatan
                        </a>
                    <?php elseif ($_SESSION['role'] === 'pembimbing'): ?>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Admin/admin.php">
                            <i class="fas fa-users me-2"></i>Data Peserta
                        </a>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
                            <i class="fas fa-check-circle me-2"></i>Persetujuan Izin
                        </a>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Reports/reports_review.php">
                            <i class="fas fa-clipboard-check me-2"></i>Review Laporan
                        </a>
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Admin/users.php">
                            <i class="fas fa-users-cog me-2"></i>Kelola User
                        </a>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php">
                            <i class="fas fa-user-graduate me-2"></i>Kelola Peserta
                        </a>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Admin/divisions.php">
                            <i class="fas fa-building me-2"></i>Kelola Divisi
                        </a>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Reports/admin_reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Laporan Sistem
                        </a>
                    <?php endif; ?>
                    <hr class="my-3">
                    <a class="nav-link" href="<?= APP_URL ?>/public/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <!-- Page Header -->
                    <div class="page-header">
                        <div class="container">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="mb-2"><i class="fas fa-user me-3"></i>Detail Peserta</h1>
                                    <p class="mb-0"><?= htmlspecialchars($participant['full_name']) ?></p>
                                </div>
                                <div>
                                    <a href="<?= APP_URL ?>/public/dashboard.php" class="btn btn-light">
                                        <i class="fas fa-arrow-left me-2"></i>Kembali
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Information -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="profile-card">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="profile-avatar me-3">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-1"><?= htmlspecialchars($participant['full_name']) ?></h4>
                                        <p class="text-muted mb-0"><?= htmlspecialchars($participant['email']) ?></p>
                                        <span class="badge bg-<?= $participant['status'] === 'aktif' ? 'success' : ($participant['status'] === 'selesai' ? 'info' : 'danger') ?> mt-2">
                                            <i class="fas fa-circle me-1"></i>
                                            <?= ucfirst($participant['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <h6 class="text-primary mb-3">
                                            <i class="fas fa-user me-2"></i>Data Pribadi
                                        </h6>
                                        <div class="info-item">
                                            <i class="fas fa-user-tag me-2 text-muted"></i>
                                            <span class="text-muted">Username:</span>
                                            <strong><?= htmlspecialchars($participant['username']) ?></strong>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-4">
                                        <h6 class="text-success mb-3">
                                            <i class="fas fa-graduation-cap me-2"></i>Data Magang/PKL
                                        </h6>
                                        <div class="info-item">
                                            <i class="fas fa-school me-2 text-muted"></i>
                                            <span class="text-muted">Sekolah:</span>
                                            <strong><?= htmlspecialchars($participant['school']) ?></strong>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-book me-2 text-muted"></i>
                                            <span class="text-muted">Jurusan:</span>
                                            <strong><?= htmlspecialchars($participant['major']) ?></strong>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-building me-2 text-muted"></i>
                                            <span class="text-muted">Divisi:</span>
                                            <strong><?= htmlspecialchars($participant['division_name']) ?></strong>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-user-tie me-2 text-muted"></i>
                                            <span class="text-muted">Pembimbing:</span>
                                            <strong><?= $participant['supervisor_name'] ? htmlspecialchars($participant['supervisor_name']) : 'Belum ditentukan' ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-info mb-3">
                                            <i class="fas fa-calendar me-2"></i>Periode Magang/PKL
                                        </h6>
                                        <div class="info-item">
                                            <i class="fas fa-play me-2 text-muted"></i>
                                            <span class="text-muted">Mulai:</span>
                                            <strong><?= date('d M Y', strtotime($participant['start_date'])) ?></strong>
                                        </div>
                                        <div class="info-item">
                                            <i class="fas fa-stop me-2 text-muted"></i>
                                            <span class="text-muted">Selesai:</span>
                                            <strong><?= date('d M Y', strtotime($participant['end_date'])) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="summary-stats">
                                <h5 class="mb-3 text-center">Ringkasan Kehadiran</h5>

                                <div class="summary-item">
                                    <div class="summary-icon bg-primary">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="summary-content">
                                        <h4><?= $attendance_stats['total_days'] ?></h4>
                                        <p>Total Hari</p>
                                    </div>
                                </div>

                                <div class="summary-item">
                                    <div class="summary-icon bg-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="summary-content">
                                        <h4><?= $attendance_stats['present_days'] ?></h4>
                                        <p>Hari Hadir</p>
                                    </div>
                                </div>

                                <div class="summary-item">
                                    <div class="summary-icon bg-info">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="summary-content">
                                        <h4><?= number_format($total_work_hours, 1) ?></h4>
                                        <p>Jam Kerja</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Statistics -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="info-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Detail Kehadiran
                                </h5>

                                <div class="row">
                                    <div class="col-md-6 col-lg-3 mb-4">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <div class="summary-icon bg-success mx-auto mb-3">
                                                    <i class="fas fa-check-circle"></i>
                                                </div>
                                                <h3 class="card-title"><?= $attendance_stats['present_days'] ?></h3>
                                                <p class="card-text text-muted">Hari Hadir</p>
                                                <div class="progress mt-2">
                                                    <div class="progress-bar bg-success" role="progressbar"
                                                        style="width: <?= $attendance_stats['total_days'] > 0 ? ($attendance_stats['present_days'] / $attendance_stats['total_days'] * 100) : 0 ?>%"
                                                        aria-valuenow="<?= $attendance_stats['present_days'] ?>"
                                                        aria-valuemin="0"
                                                        aria-valuemax="<?= $attendance_stats['total_days'] ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3 mb-4">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <div class="summary-icon bg-warning mx-auto mb-3">
                                                    <i class="fas fa-calendar-times"></i>
                                                </div>
                                                <h3 class="card-title"><?= $attendance_stats['leave_days'] ?></h3>
                                                <p class="card-text text-muted">Hari Izin</p>
                                                <div class="progress mt-2">
                                                    <div class="progress-bar bg-warning" role="progressbar"
                                                        style="width: <?= $attendance_stats['total_days'] > 0 ? ($attendance_stats['leave_days'] / $attendance_stats['total_days'] * 100) : 0 ?>%"
                                                        aria-valuenow="<?= $attendance_stats['leave_days'] ?>"
                                                        aria-valuemin="0"
                                                        aria-valuemax="<?= $attendance_stats['total_days'] ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3 mb-4">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <div class="summary-icon bg-info mx-auto mb-3">
                                                    <i class="fas fa-user-injured"></i>
                                                </div>
                                                <h3 class="card-title"><?= $attendance_stats['sick_days'] ?></h3>
                                                <p class="card-text text-muted">Hari Sakit</p>
                                                <div class="progress mt-2">
                                                    <div class="progress-bar bg-info" role="progressbar"
                                                        style="width: <?= $attendance_stats['total_days'] > 0 ? ($attendance_stats['sick_days'] / $attendance_stats['total_days'] * 100) : 0 ?>%"
                                                        aria-valuenow="<?= $attendance_stats['sick_days'] ?>"
                                                        aria-valuemin="0"
                                                        aria-valuemax="<?= $attendance_stats['total_days'] ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-lg-3 mb-4">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <div class="summary-icon bg-danger mx-auto mb-3">
                                                    <i class="fas fa-times-circle"></i>
                                                </div>
                                                <h3 class="card-title"><?= $attendance_stats['absent_days'] ?></h3>
                                                <p class="card-text text-muted">Hari Alpa</p>
                                                <div class="progress mt-2">
                                                    <div class="progress-bar bg-danger" role="progressbar"
                                                        style="width: <?= $attendance_stats['total_days'] > 0 ? ($attendance_stats['absent_days'] / $attendance_stats['total_days'] * 100) : 0 ?>%"
                                                        aria-valuenow="<?= $attendance_stats['absent_days'] ?>"
                                                        aria-valuemin="0"
                                                        aria-valuemax="<?= $attendance_stats['total_days'] ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-history me-2"></i>
                                        Kehadiran Terbaru
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover attendance-table">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Check In</th>
                                                    <th>Check Out</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_attendance as $attendance): ?>
                                                    <tr>
                                                        <td><?= date('d M', strtotime($attendance['date'])) ?></td>
                                                        <td>
                                                            <?php if ($attendance['check_in']): ?>
                                                                <span class="badge bg-success">
                                                                    <?= date('H:i', strtotime($attendance['check_in'])) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($attendance['check_out']): ?>
                                                                <span class="badge bg-danger">
                                                                    <?= date('H:i', strtotime($attendance['check_out'])) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status_info = getAttendanceStatus($attendance['status']);
                                                            ?>
                                                            <span class="badge bg-<?= $status_info['class'] ?>">
                                                                <i class="fas fa-<?= $status_info['icon'] ?> me-1"></i>
                                                                <?= $status_info['label'] ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-times me-2"></i>
                                        Izin Terbaru
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover attendance-table">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Jenis</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leave_requests as $leave): ?>
                                                    <tr>
                                                        <td><?= date('d M', strtotime($leave['request_date'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?= getLeaveTypeLabel($leave['leave_type']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status_class = '';
                                                            switch ($leave['status']) {
                                                                case 'pending':
                                                                    $status_class = 'warning';
                                                                    break;
                                                                case 'approved':
                                                                    $status_class = 'success';
                                                                    break;
                                                                case 'rejected':
                                                                    $status_class = 'danger';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge bg-<?= $status_class ?>">
                                                                <?= ucfirst($leave['status']) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Reports -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-alt me-2"></i>
                                        Laporan Kegiatan Terbaru
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Judul</th>
                                                    <th>Rating</th>
                                                    <th>Komentar</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($activity_reports as $report): ?>
                                                    <tr>
                                                        <td><?= date('d M Y', strtotime($report['report_date'])) ?></td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($report['title']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($report['rating']): ?>
                                                                <div class="rating-stars">
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <i class="fas fa-star<?= $i <= $report['rating'] ? '' : '-o' ?> text-warning"></i>
                                                                    <?php endfor; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">Belum dinilai</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($report['supervisor_comment']): ?>
                                                                <span class="text-truncate d-inline-block" style="max-width: 200px;"
                                                                      title="<?= htmlspecialchars($report['supervisor_comment']) ?>">
                                                                    <?= htmlspecialchars($report['supervisor_comment']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>

                                                <?php if (empty($activity_reports)): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted py-4">
                                                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                            Belum ada laporan kegiatan
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="drawer-backdrop"></div>
    <button class="btn drawer-toggle floating-toggle" aria-label="Toggle menu" style="position:fixed;bottom:18px;left:18px;z-index:1400;">☰</button>
    <script src="<?= APP_URL ?>/assets/js/drawer.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
