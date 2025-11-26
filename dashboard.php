<?php
session_start();
require_once 'config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Struktur data dasar
$dashboard_data = ['role' => $role];

// ===============================
// ROLE: MAHASISWA MBKM & SISWA PKL
// ===============================
if ($role === 'mahasiswa_mbkm' || $role === 'siswa_pkl') {
    // Data peserta berdasarkan user_id
    $participant = fetchOne(
        "
        SELECT 
            p.*,
            d.name AS division_name
        FROM participants p
        LEFT JOIN divisions d ON p.division_id = d.id
        WHERE p.user_id = ?
    ",
        [$user_id],
    );

    if ($participant) {
        $dashboard_data['participant'] = $participant;

                // =====================
        // AMBIL MASA KERJA PKL
        // =====================
        $masa_mulai   = $participant['start_date']  ?? null;
        $masa_selesai = $participant['end_date']    ?? null;

        // Validasi format tanggal
        if (!$masa_mulai || !strtotime($masa_mulai)) {
            $masa_mulai = null;
        }
        if (!$masa_selesai || !strtotime($masa_selesai)) {
            $masa_selesai = null;
        }

        // Hitung kehadiran bulan ini
        $current_month = date('Y-m');
        $attendance_count = fetchOne(
            "
            SELECT COUNT(*) AS count 
            FROM attendance 
            WHERE participant_id = ? 
              AND FORMAT([date], 'yyyy-MM') = ? 
              AND status = 'hadir'
        ",
            [$participant['id'], $current_month],
        );
        $dashboard_data['attendance_count'] = $attendance_count['count'];

        // Hitung izin pending
        $pending_leaves = fetchOne(
            "
            SELECT COUNT(*) AS count 
            FROM leave_requests 
            WHERE participant_id = ? 
              AND status = 'pending'
        ",
            [$participant['id']],
        );
        $dashboard_data['pending_leaves'] = $pending_leaves['count'];

        // Untuk MAHASISWA MBKM saja: laporan kegiatan
        if ($role === 'mahasiswa_mbkm') {
            $reports_count = fetchOne(
                "
                SELECT COUNT(*) AS count 
                FROM activity_reports 
                WHERE participant_id = ? 
                  AND FORMAT(report_date, 'yyyy-MM') = ?
            ",
                [$participant['id'], $current_month],
            );
            $dashboard_data['reports_count'] = $reports_count['count'];
        }

        // Untuk SISWA PKL: simpan nama pembimbing
        if ($role === 'siswa_pkl') {
            $dashboard_data['company_supervisor'] = $participant['company_supervisor'] ?? null;
            $dashboard_data['school_supervisor'] = $participant['school_supervisor'] ?? null;
        }
    }
}

// ===============================
// ROLE: ADMIN
// ===============================
elseif ($role === 'admin') {
    // Data ringkas
    $total_participants = fetchOne("SELECT COUNT(*) AS count FROM participants WHERE status = 'aktif'");
    $total_divisions = fetchOne('SELECT COUNT(*) AS count FROM divisions');
    $total_users = fetchOne('SELECT COUNT(*) AS count FROM users');

    $dashboard_data['total_participants'] = $total_participants['count'];
    $dashboard_data['total_divisions'] = $total_divisions['count'];
    $dashboard_data['total_users'] = $total_users['count'];

    // Kehadiran hari ini
    $today_attendance = fetchAll("
        SELECT a.*, u.full_name, d.name AS division_name 
        FROM attendance a 
        JOIN participants p ON a.participant_id = p.id 
        JOIN users u ON p.user_id = u.id 
        JOIN divisions d ON p.division_id = d.id 
        WHERE CAST(a.[date] AS DATE) = CAST(GETDATE() AS DATE)
    ");
    $dashboard_data['today_attendance'] = $today_attendance;

    // --- Ringkasan laporan sistem ---
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');

    $attendance_stats = fetchOne(
        "
        SELECT 
            COUNT(*) as total_attendance,
            SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as leave_days,
            SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sick_days,
            SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as absent_days
        FROM attendance 
        WHERE [date] BETWEEN ? AND ?
    ",
        [$start_date, $end_date],
    );

    $leave_stats = fetchOne(
        "
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
        FROM leave_requests 
        WHERE request_date BETWEEN ? AND ?
    ",
        [$start_date, $end_date],
    );

    $report_stats = fetchOne(
        "
        SELECT 
            COUNT(*) as total_reports,
            AVG(rating) as average_rating
        FROM activity_reports 
        WHERE report_date BETWEEN ? AND ?
    ",
        [$start_date, $end_date],
    );

    $attendance_by_division = fetchAll(
        "
        SELECT 
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
        LEFT JOIN attendance a ON p.id = a.participant_id AND a.[date] BETWEEN ? AND ?
        GROUP BY d.id, d.name
        ORDER BY attendance_rate DESC
    ",
        [$start_date, $end_date],
    );

    $top_performers = fetchAll(
        "
        SELECT 
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
        LEFT JOIN attendance a ON p.id = a.participant_id AND a.[date] BETWEEN ? AND ?
        LEFT JOIN activity_reports ar ON p.id = ar.participant_id AND ar.report_date BETWEEN ? AND ?
        WHERE p.status = 'aktif'
        GROUP BY p.id, u.full_name, d.name
        HAVING COUNT(a.id) > 0
        ORDER BY attendance_rate DESC, average_rating DESC
    ",
        [$start_date, $end_date, $start_date, $end_date],
    );

    $recent_leaves = fetchAll(
        "
        SELECT 
            lr.*,
            u.full_name as participant_name,
            d.name as division_name
        FROM leave_requests lr
        JOIN participants p ON lr.participant_id = p.id
        JOIN users u ON p.user_id = u.id
        JOIN divisions d ON p.division_id = d.id
        WHERE lr.request_date BETWEEN ? AND ?
        ORDER BY lr.created_at DESC
    ",
        [$start_date, $end_date],
    );

    $dashboard_data['attendance_stats'] = $attendance_stats;
    $dashboard_data['leave_stats'] = $leave_stats;
    $dashboard_data['report_stats'] = $report_stats;
    $dashboard_data['attendance_by_division'] = $attendance_by_division;
    $dashboard_data['top_performers'] = $top_performers;
    $dashboard_data['recent_leaves'] = $recent_leaves;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aplikasi Pengawasan Magang/PKL</title>
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

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
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
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">

            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3 text-center">
                    <h4><i class="fas fa-graduation-cap me-2"></i>Magang/PKL</h4>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>

                    <!-- MENU PESERTA MBKM -->
                    <?php if ($role === 'mahasiswa_mbkm'): ?>
                    <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi
                        Harian</a>
                    <a class="nav-link" href="attendance_history.php"><i class="fas fa-history me-2"></i>Riwayat
                        Kehadiran</a>
                    <a class="nav-link" href="leave_request.php"><i class="fas fa-calendar-times me-2"></i>Ajukan
                        Izin</a>
                    <a class="nav-link" href="activity_report.php"><i class="fas fa-file-alt me-2"></i>Laporan
                        Kegiatan</a>

                    <!-- MENU SISWA PKL -->
                    <?php elseif ($role === 'siswa_pkl'): ?>
                    <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi
                        Harian</a>
                    <a class="nav-link" href="attendance_history.php"><i class="fas fa-history me-2"></i>Riwayat
                        Kehadiran</a>
                    <a class="nav-link" href="leave_request.php"><i class="fas fa-calendar-times me-2"></i>Ajukan
                        Izin</a>
                    <a class="nav-link" href="activity_report.php"><i class="fas fa-file-alt me-2"></i>Laporan
                        Kegiatan</a>
                    <a class="nav-link" href="bimbingan_pkl.php"><i
                            class="fas fa-chalkboard-teacher me-2"></i>Bimbingan</a>

                    <!-- MENU ADMIN -->
                    <?php elseif ($role === 'admin'): ?>
                    <a class="nav-link" href="admin/users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
                    <a class="nav-link" href="admin/participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola
                        Peserta</a>
                    <a class="nav-link" href="admin/participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola
                        MBKM</a>
                    <a class="nav-link" href="admin/participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
                    <a class="nav-link" href="admin/bimbingan_pkl.php"><i
                            class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL</a>
                    <a class="nav-link" href="leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan
                        Izin</a>
                    <a class="nav-link" href="reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review
                        Laporan</a>
                    <a class="nav-link" href="admin/divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
                    <a class="nav-link" href="admin/reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>
                    <?php endif; ?>

                    <hr class="my-3">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
<div class="col-md-9 col-lg-10 main-content">
    <div class="p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Dashboard</h2>
                <p class="text-muted mb-0">Selamat datang, <?= htmlspecialchars($full_name) ?>!</p>
            </div>
            <span class="badge bg-primary fs-6"><?= ucfirst($role) ?></span>
        </div>

        <!-- ======================================= -->
        <!-- HANYA UNTUK MAHASISWA MBKM & SISWA PKL -->
        <!-- ======================================= -->
        <?php if ($role === 'mahasiswa_mbkm' || $role === 'siswa_pkl'): ?>

        <h4 class="mb-3"><i class="fas fa-user-graduate me-2"></i>Dashboard Peserta</h4>

        <!-- Masa Kerja -->
        <div class="alert alert-info">
            <i class="fas fa-calendar-alt me-2"></i>
            Masa kerja:
            <b><?= $masa_mulai ? date('d M Y', strtotime($masa_mulai)) : '-' ?></b>
            s.d.
            <b><?= $masa_selesai ? date('d M Y', strtotime($masa_selesai)) : '-' ?></b>
        </div>

        <!-- Filter Periode -->
        <div class="card p-3 mb-4">
            <h5><i class="fas fa-calendar-alt me-2"></i>Filter Periode Data</h5>
            <form method="GET" class="row g-3">

                <div class="col-md-4">
                    <input type="date" class="form-control"
                        name="start_date"
                        value="<?= $masa_mulai ?>"
                        readonly>
                </div>

                <div class="col-md-4">
                    <input type="date" class="form-control"
                        name="end_date"
                        value="<?= $masa_selesai ?>"
                        readonly>
                </div>

                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100" disabled>
                        <i class="fas fa-lock me-1"></i> Dikunci
                    </button>
                </div>

            </form>
        </div>

        <?php endif; ?>
        <!-- ============ END UNTUK PESERTA =============== -->
        
                    <!-- ============================ -->
                    <!--       KONTEN STATISTIK       -->
                    <!-- ============================ -->

                    <div class="row g-3 mb-4">

                        <?php if ($role === 'mahasiswa_mbkm'): ?>

                        <!-- Kehadiran -->
                        <div class="col-md-4">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-info-gradient me-3"><i class="fas fa-calendar-check"></i></div>
                                <div>
                                    <h6 class="mb-0">Kehadiran Bulan Ini</h6>
                                    <div class="fs-4"><?= $dashboard_data['attendance_count'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Laporan -->
                        <div class="col-md-4">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-success-gradient me-3"><i class="fas fa-file-alt"></i></div>
                                <div>
                                    <h6 class="mb-0">Laporan Bulan Ini</h6>
                                    <div class="fs-4"><?= $dashboard_data['reports_count'] ?? 0 ?></div>
                                    <a href="activity_report.php" class="small">Lihat laporan</a>
                                </div>
                            </div>
                        </div>

                        <!-- Izin pending -->
                        <div class="col-md-4">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-warning-gradient me-3"><i class="fas fa-calendar-times"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Izin Pending</h6>
                                    <div class="fs-4"><?= $dashboard_data['pending_leaves'] ?? 0 ?></div>
                                    <a href="leave_request.php" class="small">Ajukan/cek izin</a>
                                </div>
                            </div>
                        </div>


                        <?php elseif ($role === 'siswa_pkl'): ?>

                        <!-- Kehadiran -->
                        <div class="col-md-4">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-info-gradient me-3"><i class="fas fa-calendar-check"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Kehadiran Bulan Ini</h6>
                                    <div class="fs-4"><?= $dashboard_data['attendance_count'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Pembimbing perusahaan -->
                        <div class="col-md-4">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-success-gradient me-3"><i class="fas fa-user-tie"></i></div>
                                <div>
                                    <h6 class="mb-0">Pembimbing Perusahaan</h6>
                                    <div class="fs-6">
                                        <?= htmlspecialchars($dashboard_data['company_supervisor'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Pembimbing sekolah -->
                        <div class="col-md-4">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-warning-gradient me-3"><i class="fas fa-school"></i></div>
                                <div>
                                    <h6 class="mb-0">Pembimbing Sekolah</h6>
                                    <div class="fs-6">
                                        <?= htmlspecialchars($dashboard_data['school_supervisor'] ?? '-') ?></div>
                                    <a href="bimbingan_pkl.php" class="small">Pergi ke halaman bimbingan</a>
                                </div>
                            </div>
                        </div>


                        <?php elseif ($role === 'admin'): ?>

                        <!-- Peserta -->
                        <div class="col-md-3">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-primary-gradient me-3"><i class="fas fa-user-graduate"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">Peserta Aktif</h6>
                                    <div class="fs-4"><?= $dashboard_data['total_participants'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Divisi -->
                        <div class="col-md-3">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-success-gradient me-3"><i class="fas fa-building"></i></div>
                                <div>
                                    <h6 class="mb-0">Divisi</h6>
                                    <div class="fs-4"><?= $dashboard_data['total_divisions'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- User -->
                        <div class="col-md-3">
                            <div class="stat-card d-flex align-items-center">
                                <div class="stat-icon bg-warning-gradient me-3"><i class="fas fa-users-cog"></i></div>
                                <div>
                                    <h6 class="mb-0">User</h6>
                                    <div class="fs-4"><?= $dashboard_data['total_users'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Ringkasan Sistem -->
                        <div class="col-12">
                            <div class="card shadow-sm mb-4">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5><i class="fas fa-chart-bar me-2 text-primary"></i>Ringkasan Laporan Sistem
                                        </h5>
                                        <small class="text-muted">Periode: <?= $start_date ?> —
                                            <?= $end_date ?></small>
                                    </div>

                                    <!-- ... (bagian statistik admin lain tetap sama) ... -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-sm-6 col-md-3">
                                            <div class="p-3 bg-white rounded shadow-sm text-center">
                                                <div class="text-muted small">Peserta Aktif</div>
                                                <div class="fs-4 fw-bold">
                                                    <?= $dashboard_data['total_participants'] ?? 0 ?></div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="p-3 bg-white rounded shadow-sm text-center">
                                                <div class="text-muted small">Divisi</div>
                                                <div class="fs-4 fw-bold">
                                                    <?= $dashboard_data['total_divisions'] ?? 0 ?></div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="p-3 bg-white rounded shadow-sm text-center">
                                                <div class="text-muted small">Hari Hadir</div>
                                                <div class="fs-4 fw-bold">
                                                    <?= $dashboard_data['attendance_stats']['present_days'] ?? 0 ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="p-3 bg-white rounded shadow-sm text-center">
                                                <div class="text-muted small">Total Laporan</div>
                                                <div class="fs-4 fw-bold">
                                                    <?= $dashboard_data['report_stats']['total_reports'] ?? 0 ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="p-3 bg-white rounded shadow-sm">
                                                <h6 class="mb-3">Statistik Kehadiran</h6>
                                                <div class="d-flex justify-content-between text-center">
                                                    <div>
                                                        <div class="text-success fs-4 fw-bold">
                                                            <?= $dashboard_data['attendance_stats']['present_days'] ?? 0 ?>
                                                        </div>
                                                        <div class="text-muted small">Hadir</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-warning fs-4 fw-bold">
                                                            <?= $dashboard_data['attendance_stats']['leave_days'] ?? 0 ?>
                                                        </div>
                                                        <div class="text-muted small">Izin</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-info fs-4 fw-bold">
                                                            <?= $dashboard_data['attendance_stats']['sick_days'] ?? 0 ?>
                                                        </div>
                                                        <div class="text-muted small">Sakit</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-danger fs-4 fw-bold">
                                                            <?= $dashboard_data['attendance_stats']['absent_days'] ?? 0 ?>
                                                        </div>
                                                        <div class="text-muted small">Alpa</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <div class="p-3 bg-white rounded shadow-sm">
                                                <h6 class="mb-3">Statistik Izin</h6>
                                                <div class="d-flex justify-content-between text-center">
                                                    <div>
                                                        <div class="text-success fs-4 fw-bold">
                                                            <?= $dashboard_data['leave_stats']['approved_requests'] ?? 0 ?>
                                                        </div>
                                                        <div class="text-muted small">Disetujui</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-warning fs-4 fw-bold">
                                                            <?= $dashboard_data['leave_stats']['pending_requests'] ?? 0 ?>
                                                        </div>
                                                        <div class="text-muted small">Pending</div>
                                                    </div>
                                                    <div>
                                                        <div class="text-danger fs-4 fw-bold">
                                                            <?= $dashboard_data['leave_stats']['rejected_requests'] ?? 0 ?>
                                                        </div>
                                                        <div class="text-muted small">Ditolak</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-lg-7 mb-3">
                                            <div class="p-3 bg-white rounded shadow-sm">
                                                <h6 class="mb-3">Kehadiran per Divisi</h6>
                                                <div class="table-responsive" style="max-height:260px; overflow:auto">
                                                    <table class="table table-sm table-hover mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Divisi</th>
                                                                <th>Kehadiran</th>
                                                                <th>Hadir</th>
                                                                <th>%</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($dashboard_data['attendance_by_division'] as $division): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($division['division_name']) ?>
                                                                </td>
                                                                <td><?= $division['total_attendance'] ?></td>
                                                                <td><?= $division['present_days'] ?></td>
                                                                <td><span
                                                                        class="badge bg-secondary"><?= $division['attendance_rate'] ?>%</span>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-5 mb-3">
                                            <div class="p-3 bg-white rounded shadow-sm mb-3">
                                                <h6 class="mb-3">Peserta Terbaik</h6>
                                                <ul class="list-group list-group-flush">
                                                    <?php foreach (array_slice($dashboard_data['top_performers'], 0, 5) as $perf): ?>
                                                    <li
                                                        class="list-group-item d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?= htmlspecialchars($perf['full_name']) ?></strong>
                                                            <div class="small text-muted">
                                                                <?= htmlspecialchars($perf['division_name']) ?></div>
                                                        </div>
                                                        <span
                                                            class="badge bg-success"><?= $perf['attendance_rate'] ?>%</span>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>

                                            <div class="p-3 bg-white rounded shadow-sm">
                                                <h6 class="mb-3">Izin Terbaru</h6>
                                                <ul class="list-group list-group-flush">
                                                    <?php foreach (array_slice($dashboard_data['recent_leaves'], 0, 5) as $leave): ?>
                                                    <li class="list-group-item">
                                                        <div class="d-flex justify-content-between">
                                                            <div>
                                                                <strong><?= htmlspecialchars($leave['participant_name']) ?></strong>
                                                                <div class="small text-muted">
                                                                    <?= htmlspecialchars($leave['division_name']) ?>
                                                                </div>
                                                            </div>
                                                            <div class="text-end">
                                                                <div class="small text-muted">
                                                                    <?php
                                                                    $type_labels = [
                                                                        'sakit' => 'Sakit',
                                                                        'izin' => 'Izin',
                                                                        'keperluan_mendesak' => 'Mendesak',
                                                                    ];
                                                                    echo $type_labels[$leave['leave_type']] ?? $leave['leave_type'];
                                                                    ?>
                                                                </div>
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
                                                            </div>
                                                        </div>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div> <!-- row mt-3 -->

                                </div>
                            </div>
                        </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
