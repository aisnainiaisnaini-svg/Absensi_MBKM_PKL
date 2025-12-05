<?php
session_start();
require_once __DIR__ . '/../../config/app.php';
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
    <title>Review Laporan - Admin</title>
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
        .bg-warning-gradient {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        .bg-info-gradient {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
        }
        .report-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .btn-review {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-review:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
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

            /* Report cards stack */
            .report-card {
                margin-bottom: 1.5rem;
            }

            /* Stats cards full width */
            .stats-card {
                margin-bottom: 1rem;
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
            <a class="nav-link active" href="<?= APP_URL ?>/app/Reports/reports_review.php">
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
    <!-- MAIN CONTENT -->
    <!-- ========================================= -->
    <div class="main-content p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h2>
                    <i class="fas fa-clipboard-check text-primary me-2"></i>
                    Review Laporan
                </h2>
                <p class="text-muted mb-0">Review dan berikan rating laporan peserta</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="reports-container">
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-primary-gradient">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="mb-1"><?= $total_reports ?></h3>
                        <p class="text-muted mb-0">Total Laporan</p>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-warning-gradient">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="mb-1"><?= $pending_reports ?></h3>
                        <p class="text-muted mb-0">Belum Direview</p>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-success-gradient">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="mb-1"><?= $reviewed_reports ?></h3>
                        <p class="text-muted mb-0">Sudah Direview</p>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card">
                        <div class="stats-icon bg-info-gradient">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3 class="mb-1"><?= number_format($average_rating, 1) ?></h3>
                        <p class="text-muted mb-0">Rating Rata-rata</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports List -->
        <div class="reports-container">
            <h5 class="mb-4">
                <i class="fas fa-list me-2"></i>
                Daftar Laporan (<?= $total_reports ?>)
            </h5>
            
            <?php if (!empty($reports)): ?>
                <div class="row g-4">
                    <?php foreach ($reports as $report): ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="report-card h-100">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($report['participant_name']) ?></h6>
                                        <p class="text-muted small mb-1"><?= htmlspecialchars($report['division_name']) ?></p>
                                        <span class="badge bg-primary">
                                            <?= date('d M Y', strtotime($report['report_date'])) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold"><?= htmlspecialchars($report['title']) ?></h6>
                                    <p class="text-muted small mb-0 lh-sm">
                                        <?= htmlspecialchars(substr($report['description'], 0, 120)) ?>
                                        <?= strlen($report['description']) > 120 ? '...' : '' ?>
                                    </p>
                                </div>
                                
                                <?php if ($report['file_path']): ?>
                                    <div class="mb-3">
                                        <a href="<?= APP_URL ?>/<?= htmlspecialchars($report['file_path']) ?>"
                                           target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                            <i class="fas fa-download me-2"></i>Download File
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($report['rating']): ?>
                                    <div class="border-top pt-3">
                                        <h6 class="mb-2"><i class="fas fa-star text-warning me-1"></i>Rating & Komentar</h6>
                                        <div class="rating-stars mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= $report['rating'] ? '' : '-o' ?>"></i>
                                            <?php endfor; ?>
                                            <span class="ms-2 text-muted small">(<?= $report['rating'] ?>/5)</span>
                                        </div>
                                        <?php if ($report['supervisor_comment']): ?>
                                            <div class="alert alert-info p-2 mb-0 small">
                                                <i class="fas fa-comment me-2"></i>
                                                <?= htmlspecialchars($report['supervisor_comment']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="border-top pt-3">
                                        <button class="btn btn-primary btn-review" 
                                                onclick="reviewReport(<?= $report['id'] ?>)">
                                            <i class="fas fa-star me-2"></i>Review Laporan
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5 class="mb-3">Belum ada laporan</h5>
                    <p class="mb-0">Peserta belum mengirimkan laporan kegiatan</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- REVIEW MODAL - IDENTIK -->
    <!-- ========================================= -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-star me-2"></i>Review Laporan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="review_report">
                        <input type="hidden" name="report_id" id="review_report_id">
                        
                        <div class="mb-3">
                            <label for="rating" class="form-label fw-semibold">Rating</label>
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
                            <label for="comment" class="form-label fw-semibold">Komentar</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" 
                                      placeholder="Berikan komentar dan feedback untuk peserta..." ></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-2"></i>Submit Review
                        </button>
                    </div>
                </form>
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

        // Modal function
        function reviewReport(reportId) {
            document.getElementById('review_report_id').value = reportId;
            new bootstrap.Modal(document.getElementById('reviewModal')).show();
        }
    </script>
</body>
</html>