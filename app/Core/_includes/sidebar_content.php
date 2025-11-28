<?php
// Ensure the role variable is defined from session
$role = $_SESSION['role'] ?? '';

// Determine path based on current script location using $_SERVER variables
$current_script = $_SERVER['SCRIPT_NAME'] ?? '';

if (strpos($current_script, '/public/') !== false) {
    // If current page is in public directory (e.g., public/dashboard.php)
    $relPath = '';
} elseif (strpos($current_script, '/app/Admin/') !== false) {
    $relPath = '../../';
} elseif (strpos($current_script, '/app/Attendance/') !== false) {
    $relPath = '../../';
} elseif (strpos($current_script, '/app/Leave/') !== false) {
    $relPath = '../../';
} elseif (strpos($current_script, '/app/Reports/') !== false) {
    $relPath = '../../';
} elseif (strpos($current_script, '/app/Guidance/') !== false) {
    $relPath = '../../';
} elseif (strpos($current_script, '/app/Participants/') !== false) {
    $relPath = '../../';
} elseif (strpos($current_script, '/app/') !== false) {
    // General app directory
    $relPath = '../';
} else {
    // Default fallback
    $relPath = '../';
}

// Determine the current page to set the active class properly
$script_name = basename($_SERVER['SCRIPT_NAME']);
$is_dashboard = $script_name === 'dashboard.php' && strpos($current_script, '/public/') !== false;
?>
<nav class="nav flex-column">
    <div class="sidebar-header p-3 pb-2">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-graduation-cap me-2"></i>
            Magang/PKL
        </h5>
        <small class="text-light opacity-75">Sistem Absensi</small>
    </div>

    <div class="nav-item px-3 mt-1">
        <a class="nav-link <?= $is_dashboard ? 'active' : '' ?>" href="<?= APP_URL ?>/public/dashboard.php">
            <i class="fas fa-tachometer-alt me-3"></i>Dashboard
        </a>
    </div>

    <!-- Role-based navigation sections -->
    <?php if ($role === 'mahasiswa_mbkm'): ?>
        <div class="sidebar-section px-3 mt-2">
            <h6 class="text-uppercase text-light opacity-75 small fw-bold mb-2">
                <i class="fas fa-user-graduate me-1"></i> Peserta MBKM
            </h6>
            <div class="nav flex-column">
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance.php">
                        <i class="fas fa-calendar-check me-3"></i>Absensi Harian
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance_history.php">
                        <i class="fas fa-history me-3"></i>Riwayat Kehadiran
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_request.php">
                        <i class="fas fa-calendar-times me-3"></i>Ajukan Izin
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/activity_report.php">
                        <i class="fas fa-file-alt me-3"></i>Laporan Kegiatan
                    </a>
                </div>
            </div>
        </div>

    <?php elseif ($role === 'siswa_pkl'): ?>
        <div class="sidebar-section px-3 mt-2">
            <h6 class="text-uppercase text-light opacity-75 small fw-bold mb-2">
                <i class="fas fa-user-graduate me-1"></i> Peserta PKL
            </h6>
            <div class="nav flex-column">
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance.php">
                        <i class="fas fa-calendar-check me-3"></i>Absensi Harian
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance_history.php">
                        <i class="fas fa-history me-3"></i>Riwayat Kehadiran
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_request.php">
                        <i class="fas fa-calendar-times me-3"></i>Ajukan Izin
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/activity_report.php">
                        <i class="fas fa-file-alt me-3"></i>Laporan Kegiatan
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/bimbingan_pkl.php">
                        <i class="fas fa-chalkboard-teacher me-3"></i>Bimbingan
                    </a>
                </div>
            </div>
        </div>

    <?php elseif ($role === 'admin'): ?>
        <div class="sidebar-section px-3 mt-2">
            <h6 class="text-uppercase text-light opacity-75 small fw-bold mb-2">
                <i class="fas fa-users-cog me-1"></i> Manajemen
            </h6>
            <div class="nav flex-column">
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/users.php">
                        <i class="fas fa-users-cog me-3"></i>Kelola User
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/participants.php">
                        <i class="fas fa-user-graduate me-3"></i>Kelola Peserta
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/participants_mbkm.php">
                        <i class="fas fa-user me-3"></i>Kelola MBKM
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/participants_pkl.php">
                        <i class="fas fa-user me-3"></i>Kelola PKL
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/admin_bimbingan_pkl.php">
                        <i class="fas fa-chalkboard-teacher me-3"></i>Bimbingan PKL
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
                        <i class="fas fa-check-circle me-3"></i>Persetujuan Izin
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/reports_review.php">
                        <i class="fas fa-clipboard-check me-3"></i>Review Laporan
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/divisions.php">
                        <i class="fas fa-building me-3"></i>Kelola Divisi
                    </a>
                </div>
                <div class="nav-item">
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/reports.php">
                        <i class="fas fa-chart-bar me-3"></i>Laporan Sistem
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="sidebar-footer px-3 mt-auto pt-3 mt-4">
        <hr class="my-2 bg-light opacity-25">
        <div class="nav-item">
            <a class="nav-link text-danger" href="<?= APP_URL ?>/public/logout.php">
                <i class="fas fa-sign-out-alt me-3"></i>Logout
            </a>
        </div>
    </div>
</nav>