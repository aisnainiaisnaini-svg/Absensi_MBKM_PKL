<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// Cek apakah user sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$supervisor_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Proses review laporan
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'review_report') {
    $report_id = $_POST['report_id'] ?? '';
    $rating = $_POST['rating'] ?? '';
    $comment = $_POST['comment'] ?? '';
    
    if ($report_id && $rating) {
        executeQuery("UPDATE activity_reports SET rating = ?, supervisor_comment = ? 
                     WHERE id = ?", [$rating, $comment, $report_id]);
        
        $message = 'Laporan berhasil direview!';
        $message_type = 'success';
    } else {
        $message = 'Rating harus diisi!';
        $message_type = 'danger';
    }
}

// Ambil data laporan yang perlu direview
$reports = fetchAll("SELECT ar.*, u.full_name as participant_name, d.name as division_name 
                    FROM activity_reports ar 
                    JOIN participants p ON ar.participant_id = p.id 
                    JOIN users u ON p.user_id = u.id 
                    JOIN divisions d ON p.division_id = d.id 
                    ORDER BY ar.report_date DESC");

// Hitung statistik
$total_reports = count($reports);
$pending_reports = count(array_filter($reports, function($r) { return $r['rating'] === null; }));
$reviewed_reports = count(array_filter($reports, function($r) { return $r['rating'] !== null; }));
$average_rating = 0;
if ($reviewed_reports > 0) {
    $rated_reports = array_filter($reports, function($r) { return $r['rating'] !== null; });
    $average_rating = array_sum(array_column($rated_reports, 'rating')) / count($rated_reports);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Laporan - Pembimbing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
        .bg-info-gradient {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .btn-review {
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        .btn-review:hover {
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
                        <i class="fas fa-graduation-cap me-2"></i>
                        Magang/PKL
                    </h4>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola Peserta</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola MBKM</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/admin_bimbingan_pkl.php"><i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan Izin</a>
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Reports/reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review Laporan</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/admin_reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>
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
                            <h2><i class="fas fa-clipboard-check me-2"></i>Review Laporan</h2>
                            <p class="text-muted mb-0">Review dan berikan rating laporan peserta</p>
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
                                <div class="stats-icon bg-primary-gradient">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h3 class="mb-1"><?= $total_reports ?></h3>
                                <p class="text-muted mb-0">Total Laporan</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-warning-gradient">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="mb-1"><?= $pending_reports ?></h3>
                                <p class="text-muted mb-0">Belum Direview</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-success-gradient">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3 class="mb-1"><?= $reviewed_reports ?></h3>
                                <p class="text-muted mb-0">Sudah Direview</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-info-gradient">
                                    <i class="fas fa-star"></i>
                                </div>
                                <h3 class="mb-1"><?= number_format($average_rating, 1) ?></h3>
                                <p class="text-muted mb-0">Rating Rata-rata</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reports List -->
                    <div class="row">
                        <?php foreach ($reports as $report): ?>
                            <div class="col-md-6 mb-4">
                                <div class="report-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($report['participant_name']) ?></h5>
                                            <p class="text-muted mb-0"><?= htmlspecialchars($report['division_name']) ?></p>
                                        </div>
                                        <div>
                                            <span class="badge bg-primary">
                                                <?= date('d M Y', strtotime($report['report_date'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6><?= htmlspecialchars($report['title']) ?></h6>
                                        <p class="text-muted">
                                            <?= htmlspecialchars(substr($report['description'], 0, 150)) ?>
                                            <?= strlen($report['description']) > 150 ? '...' : '' ?>
                                        </p>
                                    </div>
                                    
                                    <?php if ($report['file_path']): ?>
                                        <div class="mb-3">
                                            <a href="<?= APP_URL ?>/<?= htmlspecialchars($report['file_path']) ?>"
                                               target="_blank" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-download me-1"></i>Download File
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($report['rating']): ?>
                                        <div class="mb-3">
                                            <h6>Rating & Komentar</h6>
                                            <div class="rating-stars mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i <= $report['rating'] ? '' : '-o' ?>"></i>
                                                <?php endfor; ?>
                                                <span class="ms-2">(<?= $report['rating'] ?>/5)</span>
                                            </div>
                                            <?php if ($report['supervisor_comment']): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-comment me-2"></i>
                                                    <?= htmlspecialchars($report['supervisor_comment']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary btn-review flex-fill" 
                                                    onclick="reviewReport(<?= $report['id'] ?>)">
                                                <i class="fas fa-star me-2"></i>Review
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($reports)): ?>
                            <div class="col-12">
                                <div class="report-card text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada laporan</h5>
                                    <p class="text-muted">Peserta belum mengirimkan laporan kegiatan</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-star me-2"></i>Review Laporan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="review_report">
                        <input type="hidden" name="report_id" id="review_report_id">
                        
                        <div class="mb-3">
                            <label for="rating" class="form-label">Rating</label>
                            <select class="form-select" id="rating" name="rating" required>
                                <option value="">Pilih rating</option>
                                <option value="1">1 - Sangat Kurang</option>
                                <option value="2">2 - Kurang</option>
                                <option value="3">3 - Cukup</option>
                                <option value="4">4 - Baik</option>
                                <option value="5">5 - Sangat Baik</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comment" class="form-label">Komentar</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" 
                                      placeholder="Berikan komentar dan feedback untuk peserta..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"></button>
                        <button type="submit" class="btn btn-primary">Submit Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="drawer-backdrop"></div>
    <button class="btn drawer-toggle floating-toggle" aria-label="Toggle menu" style="position:fixed;bottom:18px;left:18px;z-index:1400;">☰</button>
    <script src="<?= APP_URL ?>/assets/js/drawer.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function reviewReport(reportId) {
            document.getElementById('review_report_id').value = reportId;
            new bootstrap.Modal(document.getElementById('reviewModal')).show();
        }
    </script>
</body>
</html>