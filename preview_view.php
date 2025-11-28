
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Aplikasi Pengawasan Magang/PKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="assets/css/drawer.css">
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
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
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
        .bg-primary-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-success-gradient { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); }
        .bg-warning-gradient { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-info-gradient { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
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
                        <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi Harian</a>
                        <a class="nav-link" href="attendance_history.php"><i class="fas fa-history me-2"></i>Riwayat Kehadiran</a>
                        <a class="nav-link" href="leave_request.php"><i class="fas fa-calendar-times me-2"></i>Ajukan Izin</a>
                        <a class="nav-link" href="activity_report.php"><i class="fas fa-file-alt me-2"></i>Laporan Kegiatan</a>
                    
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>Dashboard</h2>
                            <p class="text-muted mb-0">Selamat datang, <?= htmlspecialchars($full_name) ?>!</p>
                        </div>
                        <div><span class="badge bg-primary fs-6"><?= ucfirst($role) ?></span></div>
                    </div>

                    <!-- Statistik Utama (UI-only) -->
                    <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="stat-card d-flex align-items-center">
                                    <div class="stat-icon bg-info-gradient me-3"><i class="fas fa-calendar-check"></i></div>
                                    <div>
                                        <h6 class="mb-0">Kehadiran Bulan Ini</h6>
                                        <div class="fs-4"><?= isset($dashboard_data['attendance_count']) ? $dashboard_data['attendance_count'] : 0 ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card d-flex align-items-center">
                                    <div class="stat-icon bg-success-gradient me-3"><i class="fas fa-file-alt"></i></div>
                                    <div>
                                        <h6 class="mb-0">Laporan Bulan Ini</h6>
                                        <div class="fs-4"><?= isset($dashboard_data['reports_count']) ? $dashboard_data['reports_count'] : 0 ?></div>
                                        <a href="activity_report.php" class="small">Lihat laporan</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-card d-flex align-items-center">
                                    <div class="stat-icon bg-warning-gradient me-3"><i class="fas fa-calendar-times"></i></div>
                                    <div>
                                        <h6 class="mb-0">Izin Pending</h6>
                                        <div class="fs-4"><?= isset($dashboard_data['pending_leaves']) ? $dashboard_data['pending_leaves'] : 0 ?></div>
                                        <a href="leave_request.php" class="small">Ajukan/cek izin</a>
                                    </div>
                                </div>
                            </div>

                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<div class="drawer-backdrop"></div>
<button class="btn drawer-toggle floating-toggle" aria-label="Toggle menu" style="position:fixed;bottom:18px;left:18px;z-index:1400;">☰</button>
<script src="assets/js/drawer.js"></script>
</body>
</html>