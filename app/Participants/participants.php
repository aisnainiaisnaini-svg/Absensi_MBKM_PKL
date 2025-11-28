<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// Cek apakah user sudah login dan role pembimbing
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pembimbing') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$supervisor_id = $_SESSION['user_id'];

// Ambil data peserta yang dibimbing
$participants = fetchAll("SELECT p.*, u.full_name, u.email, d.name AS division_name 
                          FROM participants p 
                          JOIN users u ON p.user_id = u.id 
                          JOIN divisions d ON p.division_id = d.id 
                          WHERE p.supervisor_id = ? AND p.status = 'aktif' 
                          ORDER BY u.full_name", [$supervisor_id]);

// Hitung statistik
$total_participants = count($participants);

$pending_leaves = fetchOne("SELECT COUNT(*) AS count 
                            FROM leave_requests lr 
                            JOIN participants p ON lr.participant_id = p.id 
                            WHERE p.supervisor_id = ? AND lr.status = 'pending'", [$supervisor_id]);

$pending_reports = fetchOne("SELECT COUNT(*) AS count 
                             FROM activity_reports ar 
                             JOIN participants p ON ar.participant_id = p.id 
                             WHERE p.supervisor_id = ? AND ar.rating IS NULL", [$supervisor_id]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Peserta - Pembimbing</title>
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
        .bg-primary-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-warning-gradient { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-info-gradient { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .participant-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }
        .participant-card:hover { transform: translateY(-2px); }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
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
                    <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Participants/participants.php"><i class="fas fa-users me-2"></i>Data Peserta</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan Izin</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review Laporan</a>
                    <hr class="my-3">
                    <a class="nav-link" href="<?= APP_URL ?>/public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2><i class="fas fa-users me-2"></i>Data Peserta</h2>
                            <p class="text-muted mb-0">Peserta yang Anda bimbing</p>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-primary-gradient"><i class="fas fa-users"></i></div>
                                <h3 class="mb-1"><?= $total_participants ?></h3>
                                <p class="text-muted mb-0">Total Peserta</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-warning-gradient"><i class="fas fa-clock"></i></div>
                                <h3 class="mb-1"><?= $pending_leaves['count'] ?></h3>
                                <p class="text-muted mb-0">Izin Pending</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stats-card">
                                <div class="stats-icon bg-info-gradient"><i class="fas fa-file-alt"></i></div>
                                <h3 class="mb-1"><?= $pending_reports['count'] ?></h3>
                                <p class="text-muted mb-0">Laporan Belum Dinilai</p>
                            </div>
                        </div>
                    </div>

                    <!-- Participants List -->
                    <div class="row">
                        <?php if (!empty($participants)): ?>
                            <?php foreach ($participants as $participant): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="participant-card">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-primary-gradient rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="mb-1"><?= htmlspecialchars($participant['full_name']) ?></h5>
                                                <p class="text-muted mb-0"><?= htmlspecialchars($participant['division_name']) ?></p>
                                            </div>
                                            <span class="badge bg-success status-badge"><?= ucfirst($participant['status']) ?></span>
                                        </div>
                                        <small class="text-muted d-block mb-2"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($participant['email']) ?></small>
                                        <a href="<?= APP_URL ?>/app/Participants/participant_detail.php?id=<?= $participant['id'] ?>" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-eye me-1"></i>Detail
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada peserta yang dibimbing</h5>
                                <p class="text-muted">Hubungi admin untuk mendapatkan peserta bimbingan</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<div class="drawer-backdrop"></div>
<button class="btn drawer-toggle floating-toggle" aria-label="Toggle menu" style="position:fixed;bottom:18px;left:18px;z-index:1400;">☰</button>
<script src="<?= APP_URL ?>/assets/js/drawer.js"></script>
</body>
</html>
