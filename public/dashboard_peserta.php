<?php
session_start();
require_once __DIR__ . '/../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

// Filter periode
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-t');

// Statistik umum
$total_participants = fetchOne("SELECT COUNT(*) as count FROM participants WHERE status = 'aktif'");
$total_divisions    = fetchOne("SELECT COUNT(*) as count FROM divisions");
$total_users        = fetchOne("SELECT COUNT(*) as count FROM users");

// Statistik kehadiran
$attendance_stats = fetchOne("SELECT
    COUNT(*) as total_attendance,
    SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as leave_days,
    SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sick_days,
    SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as absent_days
    FROM attendance
    WHERE date BETWEEN ? AND ?", [$start_date, $end_date]);

// Statistik izin
$leave_stats = fetchOne("SELECT
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM leave_requests
    WHERE request_date BETWEEN ? AND ?", [$start_date, $end_date]);

// Statistik laporan
$report_stats = fetchOne("SELECT
    COUNT(*) as total_reports,
    AVG(rating) as average_rating
    FROM activity_reports
    WHERE report_date BETWEEN ? AND ?", [$start_date, $end_date]);

// Data kehadiran per divisi (diperbaiki CASE WHEN)
$attendance_by_division = fetchAll("SELECT
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
    ORDER BY attendance_rate DESC", [$start_date, $end_date]);

// Peserta terbaik (juga diperbaiki CASE WHEN)
$top_performers = fetchAll("SELECT
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
    OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY", [$start_date, $end_date, $start_date, $end_date]);

// Data izin terbaru
$recent_leaves = fetchAll("SELECT
    lr.*,
    u.full_name as participant_name,
    d.name as division_name
    FROM leave_requests lr
    JOIN participants p ON lr.participant_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN divisions d ON p.division_id = d.id
    WHERE lr.request_date BETWEEN ? AND ?
    ORDER BY lr.created_at DESC
    OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY", [$start_date, $end_date]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Sistem - Admin Panel</title>
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
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/divisions.php">
                        <i class="fas fa-building me-2"></i>Kelola Divisi
                    </a>
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Reports/admin_reports.php">
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
                            <h2><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</h2>
                            <p class="text-muted mb-0">Analisis dan statistik sistem pengawasan magang/PKL</p>
                        </div>
                    </div>

                    <!-- Filter Periode -->
                    <div class="filter-card">
                        <h5 class="mb-3">
                            <i class="fas fa-filter me-2"></i>Filter Periode
                        </h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Tanggal Mulai</label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                       value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">Tanggal Selesai</label>
                                <input type="date" class="form-control" id="end_date" name="end_date"
                                       value="<?= $end_date ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">
                                    <i class="fas fa-search me-2"></i>Filter Data
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Statistics Overview -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-primary-gradient">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <h3 class="mb-1"><?= $total_participants['count'] ?></h3>
                                <p class="text-muted mb-0">Total Peserta</p>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-success-gradient">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h3 class="mb-1"><?= $total_divisions['count'] ?></h3>
                                <p class="text-muted mb-0">Total Divisi</p>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-warning-gradient">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h3 class="mb-1"><?= $attendance_stats['present_days'] ?></h3>
                                <p class="text-muted mb-0">Hari Hadir</p>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-info-gradient">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h3 class="mb-1"><?= $report_stats['total_reports'] ?></h3>
                                <p class="text-muted mb-0">Total Laporan</p>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Reports -->
                    <div class="row">
                        <!-- Attendance Statistics -->
                        <div class="col-md-6 mb-4">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Statistik Kehadiran
                                </h5>

                                <div class="row text-center">
                                    <div class="col-3">
                                        <div class="text-success">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <div><strong><?= $attendance_stats['present_days'] ?></strong></div>
                                            <small>Hadir</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="text-warning">
                                            <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                            <div><strong><?= $attendance_stats['leave_days'] ?></strong></div>
                                            <small>Izin</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="text-info">
                                            <i class="fas fa-user-injured fa-2x mb-2"></i>
                                            <div><strong><?= $attendance_stats['sick_days'] ?></strong></div>
                                            <small>Sakit</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="text-danger">
                                            <i class="fas fa-times-circle fa-2x mb-2"></i>
                                            <div><strong><?= $attendance_stats['absent_days'] ?></strong></div>
                                            <small>Alpa</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leave Statistics -->
                        <div class="col-md-6 mb-4">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-calendar-times me-2"></i>
                                    Statistik Izin
                                </h5>

                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="text-success">
                                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                                            <div><strong><?= $leave_stats['approved_requests'] ?></strong></div>
                                            <small>Disetujui</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-warning">
                                            <i class="fas fa-clock fa-2x mb-2"></i>
                                            <div><strong><?= $leave_stats['pending_requests'] ?></strong></div>
                                            <small>Pending</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-danger">
                                            <i class="fas fa-times-circle fa-2x mb-2"></i>
                                            <div><strong><?= $leave_stats['rejected_requests'] ?></strong></div>
                                            <small>Ditolak</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance by Division -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-building me-2"></i>
                                    Kehadiran per Divisi
                                </h5>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Divisi</th>
                                                <th>Total Kehadiran</th>
                                                <th>Hari Hadir</th>
                                                <th>Tingkat Kehadiran</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_by_division as $division): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($division['division_name']) ?></strong></td>
                                                    <td><?= $division['total_attendance'] ?></td>
                                                    <td><?= $division['present_days'] ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-<?= $division['attendance_rate'] >= 80 ? 'success' : ($division['attendance_rate'] >= 60 ? 'warning' : 'danger') ?>"
                                                                 style="width: <?= $division['attendance_rate'] ?>%">
                                                                <?= $division['attendance_rate'] ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Performers -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-trophy me-2"></i>
                                    Peserta Terbaik
                                </h5>

                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Divisi</th>
                                                <th>Kehadiran</th>
                                                <th>Rating</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($top_performers, 0, 5) as $performer): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($performer['full_name']) ?></td>
                                                    <td><?= htmlspecialchars($performer['division_name']) ?></td>
                                                    <td>
                                                        <span class="badge bg-success">
                                                            <?= $performer['attendance_rate'] ?>%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($performer['average_rating']): ?>
                                                            <div class="rating-stars">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star<?= $i <= $performer['average_rating'] ? '' : '-o' ?> text-warning"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Leaves -->
                        <div class="col-md-6">
                            <div class="report-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-clock me-2"></i>
                                    Izin Terbaru
                                </h5>

                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Peserta</th>
                                                <th>Divisi</th>
                                                <th>Jenis</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($recent_leaves, 0, 5) as $leave): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($leave['participant_name']) ?></td>
                                                    <td><?= htmlspecialchars($leave['division_name']) ?></td>
                                                    <td>
                                                        <?php
                                                        $type_labels = [
                                                            'sakit' => 'Sakit',
                                                            'izin' => 'Izin',
                                                            'keperluan_mendesak' => 'Mendesak'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-info">
                                                            <?= $type_labels[$leave['leave_type']] ?>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>



