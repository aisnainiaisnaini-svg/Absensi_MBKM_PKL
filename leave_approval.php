<?php
session_start();
require_once 'config/database.php';

// ✅ Hanya ADMIN yang boleh akses halaman ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

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
// AMBIL SEMUA PENGAJUAN IZIN
// (Sekarang admin lihat semuanya, tidak per supervisor lagi)
// ==============================
$leave_requests = fetchAll(
    "
    SELECT 
        lr.*,
        u.full_name AS participant_name,
        d.name      AS division_name
    FROM leave_requests lr
    JOIN participants p ON lr.participant_id = p.id
    JOIN users u       ON p.user_id = u.id
    JOIN divisions d   ON p.division_id = d.id
    ORDER BY lr.created_at DESC
    "
);

// ==============================
// HITUNG STATISTIK
// ==============================
$pending_count  = count(array_filter($leave_requests, fn($lr) => $lr['status'] === 'pending'));
$approved_count = count(array_filter($leave_requests, fn($lr) => $lr['status'] === 'approved'));
$rejected_count = count(array_filter($leave_requests, fn($lr) => $lr['status'] === 'rejected'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Izin - Pembimbing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .bg-warning-gradient {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        .bg-danger-gradient {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .leave-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
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
    </style>
</head>
<body>
    <!-- HTML ASAL TETAP, TIDAK DIUBAH -->
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
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="./admin/users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
                    <a class="nav-link" href="./admin/participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola Peserta</a>
                    <a class="nav-link" href="./admin/participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola MBKM</a>
                    <a class="nav-link" href="./admin/participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
                    <a class="nav-link" href="./admin/bimbingan_pkl.php"><i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL</a>
                    <a class="nav-link active" href="leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan Izin</a>
                    <a class="nav-link" href="reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review Laporan</a>
                    <a class="nav-link" href="./admin/divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
                    <a class="nav-link" href="./admin/reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>
                    <hr class="my-3">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>

            <!-- Drawer backdrop for small screens -->
            <div class="drawer-backdrop" aria-hidden="true"></div>

            <!-- Drawer toggle (hamburger) visible on small screens -->
            <button class="btn btn-outline-secondary drawer-toggle position-fixed top-0 start-0 m-3" 
                    style="z-index:1200; border-radius:10px;" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2><i class="fas fa-check-circle me-2"></i>Persetujuan Izin</h2>
                            <p class="text-muted mb-0">Kelola pengajuan izin peserta</p>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-warning-gradient">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="mb-1"><?= $pending_count ?></h3>
                                <p class="text-muted mb-0">Pending</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-success-gradient">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3 class="mb-1"><?= $approved_count ?></h3>
                                <p class="text-muted mb-0">Disetujui</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-danger-gradient">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <h3 class="mb-1"><?= $rejected_count ?></h3>
                                <p class="text-muted mb-0">Ditolak</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-primary-gradient">
                                    <i class="fas fa-list"></i>
                                </div>
                                <h3 class="mb-1"><?= count($leave_requests) ?></h3>
                                <p class="text-muted mb-0">Total</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Leave Requests -->
                    <div class="row">
                        <?php foreach ($leave_requests as $leave): ?>
                            <div class="col-md-6 mb-4">
                                <div class="leave-card">
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
                                                        'keperluan_mendesak' => 'Keperluan Mendesak'
                                                    ];
                                                    echo $type_labels[$leave['leave_type']] ?? $leave['leave_type'];
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="col-6">
                                                <h6>Periode</h6>
                                                <small class="text-muted">
                                                    <?= date('d M Y', strtotime($leave['start_date'])) ?>
                                                    <?php if ($leave['start_date'] !== $leave['end_date']): ?>
                                                        - <?= date('d M Y', strtotime($leave['end_date'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6>Alasan</h6>
                                        <p class="text-muted"><?= htmlspecialchars($leave['reason']) ?></p>
                                    </div>
                                    
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
                        
                        <?php if (empty($leave_requests)): ?>
                            <div class="col-12">
                                <div class="leave-card text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada pengajuan izin</h5>
                                    <p class="text-muted">Semua pengajuan izin telah diproses</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/drawer.js"></script>
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
