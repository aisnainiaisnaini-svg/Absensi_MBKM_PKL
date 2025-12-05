<?php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . 'config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

// Filter periode
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Statistik umum
$total_participants = fetchOne("SELECT COUNT(*) as count FROM participants WHERE status = 'aktif'");
$total_divisions = fetchOne('SELECT COUNT(*) as count FROM divisions');
$total_users = fetchOne('SELECT COUNT(*) as count FROM users');

// Statistik kehadiran
$attendance_stats = fetchOne(
    "SELECT 
    COUNT(*) as total_attendance,
    SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as leave_days,
    SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sick_days,
    SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as absent_days
    FROM attendance 
    WHERE date BETWEEN ? AND ?",
    [$start_date, $end_date],
);

// Statistik izin
$leave_stats = fetchOne(
    "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM leave_requests 
    WHERE request_date BETWEEN ? AND ?",
    [$start_date, $end_date],
);

// Statistik laporan
$report_stats = fetchOne(
    "SELECT 
    COUNT(*) as total_reports,
    AVG(rating) as average_rating
    FROM activity_reports 
    WHERE report_date BETWEEN ? AND ?",
    [$start_date, $end_date],
);

// Data kehadiran per divisi
$attendance_by_division = fetchAll(
    "SELECT 
    d.name as division_name,
    COUNT(a.id) as total_attendance,
    SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as present_days,
    ROUND(
        CASE 
            WHEN COUNT(a.id) = 0 THEN 0 
            ELSE (CAST(SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS FLOAT) / COUNT(a.id)) * 100 
        END, 2
    ) as attendance_rate
    FROM divisions d
    LEFT JOIN participants p ON d.id = p.division_id
    LEFT JOIN attendance a ON p.id = a.participant_id AND a.date BETWEEN ? AND ?
    GROUP BY d.id, d.name
    ORDER BY attendance_rate DESC",
    [$start_date, $end_date],
);

// Peserta terbaik
$top_performers = fetchAll(
    "SELECT 
    u.full_name,
    d.name as division_name,
    COUNT(a.id) as total_attendance,
    SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as present_days,
    ROUND(
        CASE 
            WHEN COUNT(a.id) = 0 THEN 0 
            ELSE (CAST(SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS FLOAT) / COUNT(a.id)) * 100 
        END, 2
    ) as attendance_rate,
    AVG(ar.rating) as average_rating
    FROM participants p
    JOIN users u ON p.user_id = u.id
    JOIN divisions d ON p.division_id = d.id
    LEFT JOIN attendance a ON p.id = a.participant_id AND a.date BETWEEN ? AND ?
    LEFT JOIN activity_reports ar ON p.id = ar.participant_id AND ar.report_date BETWEEN ? AND ?
    WHERE p.status = 'aktif'
    GROUP BY p.id, u.full_name, d.name
    HAVING COUNT(a.id) > 0
    ORDER BY attendance_rate DESC, average_rating DESC
    OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY",
    [$start_date, $end_date, $start_date, $end_date],
);

// Data izin terbaru
$recent_leaves = fetchAll(
    "SELECT 
    lr.*,
    u.full_name as participant_name,
    d.name as division_name
    FROM leave_requests lr
    JOIN participants p ON lr.participant_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN divisions d ON p.division_id = d.id
    WHERE lr.request_date BETWEEN ? AND ?
    ORDER BY lr.created_at DESC
    OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY",
    [$start_date, $end_date],
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Sistem - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }

        /* ========================================= */
        /* SIDEBAR - IDENTIK DENGAN admin_participants */
        /* ========================================= */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            z-index: 1040;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 12px 20px;
            border-radius: 10px;
            margin: 4px 12px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* ========================================= */
        /* MAIN CONTENT - IDENTIK DENGAN admin_participants */
        /* ========================================= */
        .main-content {
            margin-left: 260px;
            transition: margin-left 0.3s ease-in-out;
            min-height: 100vh;
            padding: 20px 0;
        }

        .table-card, .reports-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        /* Stats cards */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: transform 0.3s;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .stats-card:hover { 
            transform: translateY(-5px); 
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
        .bg-danger-gradient {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        /* Filter form */
        .filter-form {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f4f8 100%);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        .filter-form .form-control,
        .filter-form .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            background: white;
        }
        .filter-form .form-control:focus,
        .filter-form .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        /* Report cards */
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s;
            margin-bottom: 20px;
            height: 100%;
        }
        .report-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        /* Progress bars */
        .progress {
            height: 25px;
            border-radius: 12px;
            background: #f8f9fa;
            overflow: visible;
        }
        .progress-bar {
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        /* Rating stars */
        .rating-stars {
            color: #ffc107;
            font-size: 1.1rem;
        }

        /* Attendance icons */
        .attendance-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin: 0 auto 10px;
            color: white;
        }

        /* ========================================= */
        /* MOBILE & TABLET - IDENTIK DENGAN admin_participants */
        /* ========================================= */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
                padding: 70px 15px 20px !important;
            }
            .sidebar-overlay {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1030;
                display: none;
            }
            .sidebar-overlay.show { display: block; }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .report-card {
                margin-bottom: 1.5rem;
            }

            .table-responsive {
                border-radius: 10px;
                overflow: hidden;
            }
        }

        @media (min-width: 992px) {
            .main-content {
                padding: 40px;
            }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            opacity: 0.5;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

    <!-- ========================================= -->
    <!-- HAMBURGER BUTTON - IDENTIK -->
    <!-- ========================================= -->
    <button class="btn btn-primary rounded-circle shadow-lg d-lg-none position-fixed top-0 start-0 m-3" 
            style="z-index: 1050; width: 50px; height: 50px;" id="sidebarToggle">
        <i class="fas fa-bars fa-lg"></i>
    </button>

    <!-- ========================================= -->
    <!-- OVERLAY - IDENTIK -->
    <!-- ========================================= -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ========================================= -->
    <!-- SIDEBAR - IDENTIK -->
    <!-- ========================================= -->
    <div class="sidebar d-flex flex-column" id="reportsSidebar">
        <div class="p-4 text-center border-bottom border-light border-opacity-25">
            <h4 class="mb-0">
                <i class="fas fa-graduation-cap me-2"></i>
                Admin Panel
            </h4>
        </div>
        <nav class="nav flex-column flex-grow-1 px-2 py-3">
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
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php">
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
            <a class="nav-link active" href="<?= APP_URL ?>/app/Reports/admin_reports.php">
                <i class="fas fa-chart-bar me-2"></i>Laporan Sistem
            </a>
            <hr class="my-4 opacity-25">
            <a class="nav-link text-danger" href="<?= APP_URL ?>/public/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>

    <!-- ========================================= -->
    <!-- MAIN CONTENT -->
    <!-- ========================================= -->
    <div class="main-content p-4 p-lg-5">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-chart-bar text-primary me-2"></i>
                    Laporan Sistem
                </h2>
                <p class="text-muted mb-0">Analisis dan statistik sistem pengawasan magang/PKL</p>
            </div>
        </div>

        <!-- Filter Periode -->
        <div class="table-card filter-form">
            <h5 class="mb-4">
                <i class="fas fa-filter me-2 text-primary"></i>
                Filter Periode
            </h5>
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-4">
                    <label for="start_date" class="form-label fw-semibold">
                        <i class="fas fa-calendar-alt me-1"></i>Tanggal Mulai
                    </label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                           value="<?= $start_date ?>">
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label for="end_date" class="form-label fw-semibold">
                        <i class="fas fa-calendar-alt me-1"></i>Tanggal Selesai
                    </label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                           value="<?= $end_date ?>">
                </div>
                <div class="col-sm-6 col-lg-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Filter Data
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics Overview -->
        <div class="reports-container">
            <h5 class="mb-4">
                <i class="fas fa-chart-line me-2"></i>
                Statistik Utama
            </h5>
            <div class="row g-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-primary-gradient">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 class="mb-1"><?= $total_participants['count'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Total Peserta</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-success-gradient">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="mb-1"><?= $total_divisions['count'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Total Divisi</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-warning-gradient">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="mb-1"><?= $attendance_stats['present_days'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Hari Hadir</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-info-gradient">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="mb-1"><?= $report_stats['total_reports'] ?? 0 ?></h3>
                        <p class="text-muted mb-0">Total Laporan</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance & Leave Stats -->
        <div class="row g-4 mb-4">
            <!-- Attendance Statistics -->
            <div class="col-lg-6">
                <div class="report-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2 text-info"></i>
                            Statistik Kehadiran
                        </h5>
                    </div>
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <div class="attendance-icon bg-success-gradient">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4 class="text-success mt-2"><?= $attendance_stats['present_days'] ?? 0 ?></h4>
                            <small class="text-muted">Hadir</small>
                        </div>
                        <div class="col-6">
                            <div class="attendance-icon bg-warning-gradient">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h4 class="text-warning mt-2"><?= $attendance_stats['leave_days'] ?? 0 ?></h4>
                            <small class="text-muted">Izin</small>
                        </div>
                        <div class="col-6">
                            <div class="attendance-icon bg-info-gradient">
                                <i class="fas fa-user-injured"></i>
                            </div>
                            <h4 class="text-info mt-2"><?= $attendance_stats['sick_days'] ?? 0 ?></h4>
                            <small class="text-muted">Sakit</small>
                        </div>
                        <div class="col-6">
                            <div class="attendance-icon bg-danger-gradient">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h4 class="text-danger mt-2"><?= $attendance_stats['absent_days'] ?? 0 ?></h4>
                            <small class="text-muted">Alpa</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Statistics -->
            <div class="col-lg-6">
                <div class="report-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-times me-2 text-warning"></i>
                            Statistik Izin
                        </h5>
                    </div>
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="attendance-icon bg-success-gradient">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h5 class="text-success mt-2"><?= $leave_stats['approved_requests'] ?? 0 ?></h5>
                            <small class="text-muted">Disetujui</small>
                        </div>
                        <div class="col-4">
                            <div class="attendance-icon bg-warning-gradient">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h5 class="text-warning mt-2"><?= $leave_stats['pending_requests'] ?? 0 ?></h5>
                            <small class="text-muted">Pending</small>
                        </div>
                        <div class="col-4">
                            <div class="attendance-icon bg-danger-gradient">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h5 class="text-danger mt-2"><?= $leave_stats['rejected_requests'] ?? 0 ?></h5>
                            <small class="text-muted">Ditolak</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance by Division -->
        <div class="reports-container">
            <h5 class="mb-4">
                <i class="fas fa-building me-2"></i>
                Kehadiran per Divisi
            </h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Divisi</th>
                            <th>Total</th>
                            <th>Hadir</th>
                            <th>Tingkat Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_by_division as $division): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($division['division_name']) ?></strong>
                                </td>
                                <td><?= $division['total_attendance'] ?></td>
                                <td><?= $division['present_days'] ?></td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-<?= $division['attendance_rate'] >= 80 ? 'success' : ($division['attendance_rate'] >= 60 ? 'warning' : 'danger') ?>"
                                             style="width: <?= $division['attendance_rate'] ?>%">
                                            <span class="small fw-bold"><?= $division['attendance_rate'] ?>%</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($attendance_by_division)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                                    <div>Belum ada data kehadiran</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Performers & Recent Leaves -->
        <div class="row g-4">
            <!-- Top Performers -->
            <div class="col-lg-6">
                <div class="report-card">
                    <h5 class="mb-4">
                        <i class="fas fa-trophy me-2 text-warning"></i>
                        Peserta Terbaik (Top 5)
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama</th>
                                    <th>Divisi</th>
                                    <th>Kehadiran</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($top_performers, 0, 5) as $index => $performer): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-warning bg-opacity-20 rounded-circle me-2 p-2">
                                                    <i class="fas fa-user text-warning" style="font-size: 1rem;"></i>
                                                </div>
                                                <div>
                                                    <strong><?= htmlspecialchars($performer['full_name']) ?></strong>
                                                    <?php if ($index === 0): ?>
                                                        <span class="badge bg-warning ms-2">🏆 #1</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-nowrap">
                                            <small class="text-muted"><?= htmlspecialchars($performer['division_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?= $performer['attendance_rate'] ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($performer['average_rating']): ?>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= round($performer['average_rating']) ? '' : '-o' ?>"></i>
                                                    <?php endfor; ?>
                                                    <small class="ms-1 text-muted"><?= round($performer['average_rating'], 1) ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($top_performers)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-trophy fa-2x mb-2"></i>
                                            <div>Belum ada data performa</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Leaves -->
            <div class="col-lg-6">
                <div class="report-card">
                    <h5 class="mb-4">
                        <i class="fas fa-clock me-2 text-info"></i>
                        Izin Terbaru (5 Terakhir)
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Peserta</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recent_leaves, 0, 5) as $leave): ?>
                                    <tr>
                                        <td class="fw-semibold">
                                            <?= htmlspecialchars($leave['participant_name']) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $type_labels = [
                                                'sakit' => ['icon' => 'fas fa-user-injured', 'color' => 'info'],
                                                'izin' => ['icon' => 'fas fa-calendar-times', 'color' => 'warning'],
                                                'keperluan_mendesak' => ['icon' => 'fas fa-exclamation-triangle', 'color' => 'danger'],
                                            ];
                                            $type = $type_labels[$leave['leave_type']] ?? $type_labels['izin'];
                                            ?>
                                            <span class="badge bg-<?= $type['color'] ?>">
                                                <i class="<?= $type['icon'] ?> me-1"></i>
                                                <?= ucwords(str_replace('_', ' ', $leave['leave_type'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => ['bg-warning', 'fas fa-clock'],
                                                'approved' => ['bg-success', 'fas fa-check-circle'],
                                                'rejected' => ['bg-danger', 'fas fa-times-circle']
                                            ];
                                            $status = $status_badges[$leave['status']] ?? $status_badges['pending'];
                                            ?>
                                            <span class="badge <?= $status[0] ?>">
                                                <i class="<?= $status[1] ?> me-1"></i>
                                                <?= ucfirst($leave['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <small class="text-muted">
                                                <?= date('d M Y', strtotime($leave['request_date'])) ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_leaves)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                            <div>Belum ada permintaan izin</div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ========================================= -->
    <!-- JAVASCRIPT - IDENTIK DENGAN admin_participants */
    <!-- ========================================= -->
    <script>
        const sidebar = document.getElementById('reportsSidebar');
        const overlay  = document.getElementById('sidebarOverlay');
        const toggle   = document.getElementById('sidebarToggle');

        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        // Tutup saat klik overlay
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        // Tutup saat klik link di dalam sidebar
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 991) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>