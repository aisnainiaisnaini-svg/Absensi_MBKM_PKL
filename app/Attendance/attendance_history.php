<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

/*
|--------------------------------------------------------------------------
| 1. Role Check — hanya untuk peserta (MBKM / PKL)
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['mahasiswa_mbkm', 'siswa_pkl'])
) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>Akses hanya untuk Peserta Magang/PKL.</h3>");
}

$user_id = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| 2. Ambil participant aktif
|--------------------------------------------------------------------------
*/
$participant = fetchOne("
    SELECT p.*, d.name AS division_name
    FROM participants p
    JOIN divisions d ON p.division_id = d.id
    WHERE p.user_id = ? AND p.status = 'aktif'
", [$user_id]);

if (!$participant) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>Data peserta tidak ditemukan.</h3>");
}

/*
|--------------------------------------------------------------------------
| 3. Filter bulan (default bulan ini)
|--------------------------------------------------------------------------
*/
$selected_month = $_GET['month'] ?? date('Y-m');
$start_date     = $selected_month . "-01";
$end_date       = date('Y-m-t', strtotime($start_date));

/*
|--------------------------------------------------------------------------
| 4. Ambil riwayat absensi
|--------------------------------------------------------------------------
*/
$attendance_data = fetchAll("
    SELECT
        [date],
        check_in,
        check_out,
        status,
        notes
    FROM attendance
    WHERE participant_id = ?
    AND [date] BETWEEN ? AND ?
    ORDER BY [date] DESC
", [
    $participant['id'],
    $start_date,
    $end_date
]);

/*
|--------------------------------------------------------------------------
| 5. Statistik
|--------------------------------------------------------------------------
*/
$total_days   = count($attendance_data);
$present_days = count(array_filter($attendance_data, fn($a) => $a['status'] === 'hadir'));
$leave_days   = count(array_filter($attendance_data, fn($a) => $a['status'] === 'izin'));
$sick_days    = count(array_filter($attendance_data, fn($a) => $a['status'] === 'sakit'));
$absent_days  = count(array_filter($attendance_data, fn($a) => $a['status'] === 'alpa'));

/*
|--------------------------------------------------------------------------
| 6. Total jam kerja
|--------------------------------------------------------------------------
*/
$total_work_hours = 0;
foreach ($attendance_data as $row) {
    if (!empty($row['check_in']) && !empty($row['check_out'])) {
        $total_work_hours += (strtotime($row['check_out']) - strtotime($row['check_in'])) / 3600;
    }
}

/*
|--------------------------------------------------------------------------
| 7. Daftar bulan tersedia
|--------------------------------------------------------------------------
*/
$available_months = fetchAll("
    SELECT DISTINCT FORMAT([date], 'yyyy-MM') AS month
    FROM attendance
    WHERE participant_id = ?
    ORDER BY month DESC
", [$participant['id']]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Kehadiran - Aplikasi Pengawasan Magang/PKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/custom.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar p-0">
            <div class="p-3">
                <h4 class="text-center mb-4">
                    <i class="fas fa-graduation-cap me-2"></i> Magang/PKL
                </h4>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi Harian</a>
                <a class="nav-link active" href="attendance_history.php"><i class="fas fa-history me-2"></i>Riwayat Kehadiran</a>
                <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_request.php"><i class="fas fa-calendar-times me-2"></i>Ajukan Izin</a>
                <a class="nav-link" href="<?= APP_URL ?>/app/Reports/activity_report.php"><i class="fas fa-file-alt me-2"></i>Laporan Kegiatan</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'siswa_pkl'): ?>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/bimbingan_pkl.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan
                    </a>
                <?php endif; ?>
                <hr class="my-3">
                <a class="nav-link" href="<?= APP_URL ?>/public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 main-content">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-history me-2"></i>Riwayat Kehadiran</h2>
                        <p class="text-muted mb-0">Divisi: <?= htmlspecialchars($participant['division_name']) ?></p>
                    </div>
                </div>

                <!-- Filter Bulan -->
                <div class="filter-card">
                    <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Bulan</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <select class="form-select" name="month" onchange="this.form.submit()">
                                <?php foreach ($available_months as $month): ?>
                                    <option value="<?= $month['month'] ?>" <?= $month['month'] === $selected_month ? 'selected' : '' ?>>
                                        <?= date('F Y', strtotime($month['month'] . '-01')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <small class="text-muted">Menampilkan data kehadiran untuk bulan <?= date('F Y', strtotime($selected_month . '-01')) ?></small>
                        </div>
                    </form>
                </div>

                <!-- History -->
                <div class="history-card">
                    <h5 class="mb-3"><i class="fas fa-list me-2"></i>Detail Kehadiran</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tanggal</th><th>Hari</th><th>Check In</th><th>Check Out</th><th>Durasi</th><th>Status</th><th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendance_data)): ?>
                                    <?php foreach ($attendance_data as $attendance): ?>
                                        <tr>
                                            <td><strong><?= date('d M Y', strtotime($attendance['date'])) ?></strong></td>
                                            <td><span class="text-muted"><?= date('l', strtotime($attendance['date'])) ?></span></td>
                                            <td><?= $attendance['check_in'] ? '<span class="badge bg-success">'.date('H:i', strtotime($attendance['check_in'])).'</span>' : '<span class="text-muted">-</span>' ?></td>
                                            <td><?= $attendance['check_out'] ? '<span class="badge bg-danger">'.date('H:i', strtotime($attendance['check_out'])).'</span>' : '<span class="text-muted">-</span>' ?></td>
                                            <td>
                                                <?php
                                                if ($attendance['check_in'] && $attendance['check_out']) {
                                                    $duration = strtotime($attendance['check_out']) - strtotime($attendance['check_in']);
                                                    $hours = floor($duration / 3600);
                                                    $minutes = floor(($duration % 3600) / 60);
                                                    echo "<span class='badge bg-primary'>{$hours}h {$minutes}m</span>";
                                                } else {
                                                    echo "<span class='text-muted'>-</span>";
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'hadir' => 'success',
                                                    'izin' => 'warning',
                                                    'sakit' => 'info',
                                                    'alpa' => 'danger'
                                                ][$attendance['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $status_class ?>"><?= ucfirst($attendance['status']) ?></span>
                                            </td>
                                            <td><?= $attendance['notes'] ? htmlspecialchars($attendance['notes']) : '<span class="text-muted">-</span>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><br>Tidak ada data kehadiran untuk bulan ini</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
</body>
</html>
