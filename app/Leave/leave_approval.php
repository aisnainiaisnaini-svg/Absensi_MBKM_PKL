<?php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . 'config/database.php';

// Hanya admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// ==================== PAGINATION ====================
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$offset = (int)$offset;
$limit  = (int)$limit;

// ==================== FILTER ====================
$filter_status = $_GET['status'] ?? '';
$search        = $_GET['search'] ?? '';

// ==================== PROSES SETUJUI / TOLAK ====================
if ($_POST) {
    if ($_POST['action'] === 'approve_leave') {
        $leave_id = $_POST['leave_id'] ?? '';
        $notes    = $_POST['notes'] ?? '';
        if ($leave_id) {
            executeQuery("UPDATE leave_requests SET status='approved', approved_by=?, approved_at=GETDATE(), notes=? WHERE id=?", [$admin_id, $notes, $leave_id]);
            $message = 'Izin berhasil disetujui!';
            $message_type = 'success';
        }
    }
    if ($_POST['action'] === 'reject_leave') {
        $leave_id = $_POST['leave_id'] ?? '';
        $notes    = $_POST['notes'] ?? '';
        if ($leave_id) {
            executeQuery("UPDATE leave_requests SET status='rejected', approved_by=?, approved_at=GETDATE(), notes=? WHERE id=?", [$admin_id, $notes, $leave_id]);
            $message = 'Izin berhasil ditolak!';
            $message_type = 'success';
        }
    }
}

// ==================== WHERE + PARAMS ====================
$where  = "1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (u.full_name LIKE ? OR d.name LIKE ? OR lr.reason LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($filter_status)) {
    $where .= " AND lr.status = ?";
    $params[] = $filter_status;
}

// ==================== DATA + PAGINATION (SQL Server safe) ====================
$leave_requests = fetchAll("
    SELECT lr.*, u.full_name AS participant_name, d.name AS division_name
    FROM leave_requests lr
    JOIN participants p ON lr.participant_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN divisions d ON p.division_id = d.id
    WHERE $where
    ORDER BY lr.created_at DESC
    OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY
", $params);

$total = fetchOne("
    SELECT COUNT(*) AS cnt
    FROM leave_requests lr
    JOIN participants p ON lr.participant_id = p.id
    JOIN users u ON p.user_id = u.id
    JOIN divisions d ON p.division_id = d.id
    WHERE $where
", $params)['cnt'];

$total_pages = ceil($total / $limit);

// ==================== STATISTIK ====================
$stats = fetchAll("
    SELECT 
        COUNT(CASE WHEN lr.status = 'pending'   THEN 1 END) AS pending,
        COUNT(CASE WHEN lr.status = 'approved'  THEN 1 END) AS approved,
        COUNT(CASE WHEN lr.status = 'rejected' THEN 1 END) AS rejected,
        COUNT(*) AS total
    FROM leave_requests lr
    JOIN participants p ON lr.participant_id = p.id
")[0] ?? ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Izin - Admin</title>
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

        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        /* Stat cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: transform 0.3s;
            height: 100%;
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
            margin: 0 auto 15px;
        }

        /* Filter form responsive */
        .filter-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
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

            /* Stat cards stack */
            .stat-card {
                margin-bottom: 1rem;
            }

            /* Filter responsive */
            .filter-form .row > * {
                margin-bottom: 10px;
            }
        }

        @media (min-width: 992px) {
            .main-content {
                padding: 40px;
            }

            /* TAMBAHAN CSS UNTUK MOBILE SEARCH */
@media (max-width: 991.98px) {
    .search-form .d-flex {
        flex-wrap: wrap;
        gap: 8px !important;
    }
    .search-form input {
        min-width: 180px !important;
        flex: 1;
    }
    .search-form select {
        width: auto !important;
        min-width: 110px;
    }
    .search-form .btn {
        white-space: nowrap;
        padding: 0.5rem 0.75rem;
    }
}
            
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
    <div class="sidebar d-flex flex-column" id="leaveSidebar">
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
            <a class="nav-link active" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
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
            <hr class="my-4 opacity-25">
            <a class="nav-link text-danger" href="<?= APP_URL ?>/public/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>

    <!-- ========================================= -->
    <!-- MAIN CONTENT - 100% FUNGSIONAL TIDAK BERUBAH -->
    <!-- ========================================= -->
    <div class="main-content p-4 p-lg-5">
        <h2 class="mb-4">
            <i class="fas fa-check-circle text-success me-2"></i>
            Persetujuan Izin / Cuti
        </h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistik - 100% IDENTIK -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning"><i class="fas fa-clock"></i></div>
                    <h4><?= $stats['pending'] ?></h4>
                    <p class="text-muted mb-0">Menunggu</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success"><i class="fas fa-check-circle"></i></div>
                    <h4><?= $stats['approved'] ?></h4>
                    <p class="text-muted mb-0">Disetujui</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-danger"><i class="fas fa-times-circle"></i></div>
                    <h4><?= $stats['rejected'] ?></h4>
                    <p class="text-muted mb-0">Ditolak</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary"><i class="fas fa-list"></i></div>
                    <h4><?= $stats['total'] ?></h4>
                    <p class="text-muted mb-0">Total</p>
                </div>
            </div>
        </div>

        <!-- SETELAH (FIXED - RESPONSIVE MOBILE) -->
<div class="row align-items-center mb-4 g-3">
    <div class="col-md-6">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Daftar Pengajuan Izin (<?= $total ?>)
        </h5>
    </div>
    <div class="col-md-6">
        <form method="GET" class="d-flex gap-2">
            <div class="flex-grow-1">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Cari nama/alasan..." 
                       value="<?= htmlspecialchars($search) ?>"
                       style="min-width: 200px;">
            </div>
            <select name="status" class="form-select" style="width: 120px;">
                <option value="">Semua</option>
                <option value="pending" <?= $filter_status==='pending'?'selected':'' ?>>Pending</option>
                <option value="approved" <?= $filter_status==='approved'?'selected':'' ?>>Disetujui</option>
                <option value="rejected" <?= $filter_status==='rejected'?'selected':'' ?>>Ditolak</option>
            </select>
            <button class="btn btn-primary" type="submit" title="Cari">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
</div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th>Peserta</th>
                            <th>Divisi</th>
                            <th>Jenis Izin</th>
                            <th>Periode</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leave_requests): ?>
                            <?php $no = $offset + 1; foreach ($leave_requests as $lr): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($lr['participant_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($lr['division_name']) ?></td>
                                    <td>
                                        <?php
                                        $jenis = [
                                            'sakit'              => 'Sakit',
                                            'izin'               => 'Izin Pribadi',
                                            'keperluan_mendesak'=> 'Keperluan Mendesak',
                                            'izin_akademik'      => 'Izin Akademik'
                                        ];
                                        ?>
                                        <span class="badge bg-info"><?= $jenis[$lr['leave_type']] ?? $lr['leave_type'] ?></span>
                                    </td>
                                    <td class="text-nowrap">
                                        <?= date('d M Y', strtotime($lr['start_date'])) ?>
                                        <?php if ($lr['start_date'] != $lr['end_date']): ?>
                                            - <?= date('d M Y', strtotime($lr['end_date'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($lr['reason']) ?>">
                                        <?= htmlspecialchars($lr['reason']) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge = $lr['status']=='pending' ? 'warning' : ($lr['status']=='approved'?'success':'danger');
                                        $icon  = $lr['status']=='pending' ? 'clock' : ($lr['status']=='approved'?'check-circle':'times-circle');
                                        ?>
                                        <span class="badge bg-<?= $badge ?>">
                                            <i class="fas fa-<?= $icon ?> me-1"></i> 
                                            <?= ucfirst($lr['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($lr['status'] === 'pending'): ?>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-success" onclick="approveLeave(<?= $lr['id'] ?>)">
                                                    <i class="fas fa-check"></i> Setujui
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectLeave(<?= $lr['id'] ?>)">
                                                    <i class="fas fa-times"></i> Tolak
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="fas fa-check-double"></i> Selesai</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                    Tidak ada pengajuan
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination - 100% IDENTIK -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page-1 ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>">
                                Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <li class="page-item <?= $i==$page?'active':'' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page+1 ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>">
                                Next
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Setujui & Tolak - 100% IDENTIK -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="approve_leave">
                    <input type="hidden" name="leave_id" id="approve_leave_id">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-check-circle me-2"></i>Setujui Izin
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="notes" class="form-control" rows="3" placeholder="Catatan (opsional)"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Setujui</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="reject_leave">
                    <input type="hidden" name="leave_id" id="reject_leave_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-times-circle me-2"></i>Tolak Izin
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <textarea name="notes" class="form-control" rows="3" placeholder="Alasan penolakan..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ========================================= -->
    <!-- JAVASCRIPT - IDENTIK DENGAN admin_participants -->
    <!-- ========================================= -->
    <script>
        const sidebar = document.getElementById('leaveSidebar');
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

        // Modal functions - 100% IDENTIK
        function approveLeave(id) {
            document.getElementById('approve_leave_id').value = id;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }
        function rejectLeave(id) {
            document.getElementById('reject_leave_id').value = id;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }
    </script>
</body>
</html>