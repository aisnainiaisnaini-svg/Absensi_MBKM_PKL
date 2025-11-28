<?php
echo "<h1>Halaman Data Peserta (Admin)</h1>";
?>

<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

$base_path = '../'; // For files in subdirectories
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
<?php $title = 'Data Peserta - Pembimbing'; include BASE_PATH . 'app/Core/_includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3 text-center">
                    <h4><i class="fas fa-graduation-cap me-2"></i>Magang/PKL</h4>
                </div>
                <?php include BASE_PATH . 'app/Core/_includes/sidebar_content.php'; ?>
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
                                        <a href="participant_detail.php?id=<?= $participant['id'] ?>" class="btn btn-primary btn-sm w-100">
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
<?php include BASE_PATH . 'app/Core/_includes/footer.php'; ?>