<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// ✅ Hanya ADMIN yang boleh akses halaman ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Pagination configuration
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $limit;
$offset = max(0, $offset); // Ensure offset is at least 0

// Filter configuration
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// ==============================
// PROSES SETUJUI IZIN
// ==============================
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'approve_leave') {
    $leave_id = $_POST['leave_id'] ?? '';
    $notes    = $_POST['notes'] ?? '';

    if ($leave_id) {
        executeQuery(
            "
            UPDATE leave_requests
            SET status      = 'approved',
                approved_by = ?,
                approved_at = GETDATE(),
                notes       = ?
            WHERE id = ?
            ",
            [$admin_id, $notes, $leave_id]
        );

        $message = 'Izin berhasil disetujui!';
        $message_type = 'success';
    }
}

// ==============================
// PROSES TOLAK IZIN
// ==============================
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reject_leave') {
    $leave_id = $_POST['leave_id'] ?? '';
    $notes    = $_POST['notes'] ?? '';

    if ($leave_id) {
        executeQuery(
            "
            UPDATE leave_requests
            SET status      = 'rejected',
                approved_by = ?,
                approved_at = GETDATE(),
                notes       = ?
            WHERE id = ?
            ",
            [$admin_id, $notes, $leave_id]
        );

        $message = 'Izin berhasil ditolak!';
        $message_type = 'success';
    }
}

// ==============================
// AMBIL PENGAJUAN IZIN DENGAN PAGINATION DAN FILTER
// ==============================
// Query untuk filtering
$bind_values = [];
$where_clause = "1=1"; // Default condition

if (!empty($search)) {
    $where_clause .= " AND (u.full_name LIKE ? OR d.name LIKE ? OR lr.reason LIKE ?)";
    $bind_values = array_merge($bind_values, ["%$search%", "%$search%", "%$search%"]);
}

if (!empty($filter_status)) {
    $where_clause .= " AND lr.status = ?";
    $bind_values = array_merge($bind_values, [$filter_status]);
}

// Gunakan nilai offset dan limit secara langsung di query karena SQL Server tidak menerima parameter binding untuk OFFSET/FETCH
$leave_requests = fetchAll(
    "
    SELECT
        lr.id,
        lr.participant_id,
        lr.leave_type,
        lr.start_date,
        lr.end_date,
        lr.reason,
        lr.file_path,
        lr.status,
        lr.approved_by,
        lr.approved_at,
        lr.notes,
        lr.created_at,
        u.full_name AS participant_name,
        d.name AS division_name
    FROM leave_requests lr
    INNER JOIN participants p ON lr.participant_id = p.id
    INNER JOIN users u ON p.user_id = u.id
    INNER JOIN divisions d ON p.division_id = d.id
    WHERE $where_clause
    ORDER BY lr.created_at DESC
    OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
    ",
    $bind_values
);

// ==============================
// MENGHITUNG TOTAL DATA UNTUK PAGINATION
// ==============================
$total_query = "
    SELECT COUNT(*) as total
    FROM leave_requests lr
    INNER JOIN participants p ON lr.participant_id = p.id
    INNER JOIN users u ON p.user_id = u.id
    INNER JOIN divisions d ON p.division_id = d.id
    WHERE $where_clause
";
$total_count = fetchOne($total_query, $bind_values)['total'];
$total_pages = ceil($total_count / $limit);

// ==============================
// HITUNG STATISTIK (tanpa filter agar menampilkan total keseluruhan)
// ==============================
$pending_count_sql = "SELECT COUNT(*) AS count FROM leave_requests lr JOIN participants p ON lr.participant_id = p.id WHERE lr.status = ?";
$approved_count_sql = "SELECT COUNT(*) AS count FROM leave_requests lr JOIN participants p ON lr.participant_id = p.id WHERE lr.status = ?";
$rejected_count_sql = "SELECT COUNT(*) AS count FROM leave_requests lr JOIN participants p ON lr.participant_id = p.id WHERE lr.status = ?";

$pending_count  = fetchOne($pending_count_sql, ['pending'])['count'];
$approved_count = fetchOne($approved_count_sql, ['approved'])['count'];
$rejected_count = fetchOne($rejected_count_sql, ['rejected'])['count'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Izin - Sistem Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
    <style>
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
        }
        .btn-action {
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        .btn-action:hover {
            transform: translateY(-2px);
        }
        .leave-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <aside class="col-md-3 col-lg-2 sidebar p-0 d-none d-md-block">
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
                    <?php if ($_SESSION['role'] === 'mahasiswa_mbkm' || $_SESSION['role'] === 'siswa_pkl'): ?>
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
                        <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php">
                            <i class="fas fa-users me-2"></i>Data Peserta
                        </a>
                        <a class="nav-link active" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
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
                        <a class="nav-link active" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
                            <i class="fas fa-check-circle me-2"></i>Persetujuan Izin
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
            </aside>

            <!-- Mobile Menu Button -->
            <div class="d-md-none p-3">
                <button class="btn drawer-toggle w-100 d-flex justify-content-between align-items-center"
                    data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    <span><i class="fas fa-bars me-2"></i>Menu</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>

            <!-- Mobile Offcanvas Sidebar -->
            <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" data-bs-scroll="true">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Menu Navigasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                </div>
                <div class="offcanvas-body">
                    <nav class="nav flex-column">
                        <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <?php if ($_SESSION['role'] === 'mahasiswa_mbkm' || $_SESSION['role'] === 'siswa_pkl'): ?>
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
                            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php">
                                <i class="fas fa-users me-2"></i>Data Peserta
                            </a>
                            <a class="nav-link active" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
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
                            <a class="nav-link active" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
                                <i class="fas fa-check-circle me-2"></i>Persetujuan Izin
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
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <!-- Header -->
                    <div class="page-header mb-4">
                        <div class="container">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="mb-2"><i class="fas fa-check-circle me-3"></i>Persetujuan Izin</h1>
                                    <p class="mb-0">Kelola pengajuan izin peserta</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filter Card -->
                    <div class="filter-card">
                        <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="statusFilter" class="form-label">Status Izin</label>
                                <select name="status" id="statusFilter" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                                    <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label for="searchInput" class="form-label">Pencarian</label>
                                <input type="text" name="search" id="searchInput" class="form-control"
                                       placeholder="Cari nama peserta, divisi, atau alasan..."
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Cari
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="summary-icon bg-warning mx-auto mb-3">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <h3 class="card-title"><?= $pending_count ?></h3>
                                    <p class="card-text text-muted">Pending</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="summary-icon bg-success mx-auto mb-3">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3 class="card-title"><?= $approved_count ?></h3>
                                    <p class="card-text text-muted">Disetujui</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="summary-icon bg-danger mx-auto mb-3">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <h3 class="card-title"><?= $rejected_count ?></h3>
                                    <p class="card-text text-muted">Ditolak</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3 mb-4">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="summary-icon bg-primary mx-auto mb-3">
                                        <i class="fas fa-list"></i>
                                    </div>
                                    <h3 class="card-title"><?= $total_count ?></h3>
                                    <p class="card-text text-muted">Total</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Leave Requests -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                Daftar Pengajuan Izin
                                <span class="badge bg-primary ms-2"><?= $total_count ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php if (!empty($leave_requests)): ?>
                                    <?php foreach ($leave_requests as $leave): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="leave-item">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h5 class="mb-1"><?= htmlspecialchars($leave['participant_name']) ?></h5>
                                                        <p class="text-muted mb-0"><?= htmlspecialchars($leave['division_name']) ?></p>
                                                    </div>
                                                    <div>
                                                        <?php
                                                        $status_class = '';
                                                        $status_icon  = '';
                                                        switch ($leave['status']) {
                                                            case 'pending':
                                                                $status_class = 'warning';
                                                                $status_icon  = 'clock';
                                                                break;
                                                            case 'approved':
                                                                $status_class = 'success';
                                                                $status_icon  = 'check-circle';
                                                                break;
                                                            case 'rejected':
                                                                $status_class = 'danger';
                                                                $status_icon  = 'times-circle';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?= $status_class ?> status-badge">
                                                            <i class="fas fa-<?= $status_icon ?> me-1"></i>
                                                            <?= ucfirst($leave['status']) ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <h6>Jenis Izin</h6>
                                                            <span class="badge bg-info">
                                                                <?php
                                                                $type_labels = [
                                                                    'sakit'              => 'Sakit',
                                                                    'izin'               => 'Izin Pribadi',
                                                                    'keperluan_mendesak' => 'Izin Akademik',
                                                                    'izin_akademik'      => 'Izin Akademik'
                                                                ];
                                                                echo $type_labels[$leave['leave_type']] ?? $leave['leave_type'];
                                                                ?>
                                                            </span>
                                                        </div>
                                                        <div class="col-6">
                                                            <h6>Periode</h6>
                                                            <small class="text-muted">
                                                                <?= date('d M Y H:i', strtotime($leave['start_date'])) ?>
                                                                <?php if ($leave['start_date'] !== $leave['end_date']): ?>
                                                                    - <?= date('d M Y H:i', strtotime($leave['end_date'])) ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <h6>Alasan</h6>
                                                    <p class="text-muted"><?= htmlspecialchars($leave['reason']) ?></p>
                                                </div>

                                                <?php if (!empty($leave['file_path'])): ?>
                                                <div class="mb-3">
                                                    <h6>Lampiran</h6>
                                                    <a href="<?= APP_URL ?>/<?= htmlspecialchars($leave['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download me-1"></i> Lihat Lampiran
                                                    </a>
                                                </div>
                                                <?php endif; ?>

                                                <?php if ($leave['status'] === 'pending'): ?>
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-success btn-action flex-fill"
                                                                onclick="approveLeave(<?= $leave['id'] ?>)">
                                                            <i class="fas fa-check me-2"></i>Setujui
                                                        </button>
                                                        <button class="btn btn-danger btn-action flex-fill"
                                                                onclick="rejectLeave(<?= $leave['id'] ?>)">
                                                            <i class="fas fa-times me-2"></i>Tolak
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-<?= $leave['status'] === 'approved' ? 'success' : 'danger' ?> mb-0">
                                                        <i class="fas fa-<?= $leave['status'] === 'approved' ? 'check-circle' : 'times-circle' ?> me-2"></i>
                                                        Izin <?= $leave['status'] === 'approved' ? 'disetujui' : 'ditolak' ?>
                                                        pada <?= $leave['approved_at'] ? date('d M Y H:i', strtotime($leave['approved_at'])) : '-' ?>
                                                        <?php if (!empty($leave['notes'])): ?>
                                                            <br><small><?= htmlspecialchars($leave['notes']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">Belum ada pengajuan izin</h5>
                                            <p class="text-muted">Tidak ditemukan pengajuan izin sesuai dengan filter yang dipilih</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="pagination-wrapper">
                                <nav>
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>">
                                                    <i class="fas fa-chevron-left"></i> Sebelumnya
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php
                                        // Tampilkan hanya beberapa halaman di sekitar halaman saat ini
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);

                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>">
                                                    Berikutnya <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Offcanvas Sidebar -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" data-bs-scroll="true">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu Navigasi</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav flex-column">
                <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <?php if ($_SESSION['role'] === 'mahasiswa_mbkm' || $_SESSION['role'] === 'siswa_pkl'): ?>
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
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php">
                        <i class="fas fa-users me-2"></i>Data Peserta
                    </a>
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
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
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
                        <i class="fas fa-check-circle me-2"></i>Persetujuan Izin
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
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>Setujui Izin
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve_leave">
                        <input type="hidden" name="leave_id" id="approve_leave_id">

                        <div class="mb-3">
                            <label for="approve_notes" class="form-label">Catatan (Opsional)</label>
                            <textarea class="form-control" id="approve_notes" name="notes" rows="3"
                                      placeholder="Berikan catatan untuk peserta..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Setujui Izin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>Tolak Izin
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_leave">
                        <input type="hidden" name="leave_id" id="reject_leave_id">

                        <div class="mb-3">
                            <label for="reject_notes" class="form-label">Alasan Penolakan</label>
                            <textarea class="form-control" id="reject_notes" name="notes" rows="3"
                                      placeholder="Berikan alasan penolakan..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak Izin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/drawer.js"></script>
    <script>
        function approveLeave(leaveId) {
            document.getElementById('approve_leave_id').value = leaveId;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }

        function rejectLeave(leaveId) {
            document.getElementById('reject_leave_id').value = leaveId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
    </script>
</body>
</html>
