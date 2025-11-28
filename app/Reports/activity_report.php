<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// ==============================
// 1. ROLE VALIDASI YANG BENAR
// ==============================
// ======== ALLOW BOTH MBKM & PKL ========
$allowed_roles = ['mahasiswa_mbkm', 'siswa_pkl'];

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>Akses hanya untuk peserta MBKM / PKL.</h3>");
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// ==============================
// 2. AMBIL DATA PARTICIPANT
// ==============================
$participant = fetchOne("
    SELECT p.*, d.name as division_name
    FROM participants p
    JOIN divisions d ON p.division_id = d.id
    WHERE p.user_id = ? AND p.status = 'aktif'
", [$user_id]);

if (!$participant) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>Data peserta aktif tidak ditemukan.</h3>");
}

// ==============================
// 3. PROSES SIMPAN LAPORAN
// ==============================
if ($_POST) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $report_date = $_POST['report_date'] ?? '';

    if ($title && $description && $report_date) {

        $file_path = null;

        // ---------------------------
        // (Opsional) Upload File
        // ---------------------------
        if (!empty($_FILES['file']['name'])) {
            if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {

                $upload_dir = BASE_PATH . 'uploads/reports/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf','doc','docx','txt','jpg','jpeg','png'];

                if (!in_array($ext, $allowed)) {
                    $message = "Format file tidak didukung!";
                    $message_type = 'danger';
                } else {
                    $file_name = 'report_' . $participant['id'] . '_' . time() . '.' . $ext;
                    $file_path = $upload_dir . $file_name;
                    $relative_path = 'uploads/reports/' . $file_name;  // Store relative path in DB

                    move_uploaded_file($_FILES['file']['tmp_name'], $file_path);
                }
            }
        }

        if (!$message) {
            executeQuery("
                INSERT INTO activity_reports
                (participant_id, report_date, title, description, file_path, created_at)
                VALUES (?, ?, ?, ?, ?, GETDATE())
            ", [
                $participant['id'],
                $report_date,
                $title,
                $description,
                $relative_path
            ]);

            $message = "Laporan berhasil disimpan!";
            $message_type = "success";
        }

    } else {
        $message = "Semua field wajib diisi.";
        $message_type = "danger";
    }
}

// ==============================
// 4. AMBIL RIWAYAT LAPORAN
// ==============================
$reports = fetchAll("
    SELECT *
    FROM activity_reports
    WHERE participant_id = ?
    ORDER BY report_date DESC, created_at DESC
", [$participant['id']]);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kegiatan - Aplikasi Pengawasan Magang/PKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
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
                    <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance.php">
                        <i class="fas fa-calendar-check me-2"></i>Absensi Harian
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance_history.php">
                        <i class="fas fa-history me-2"></i>Riwayat Kehadiran
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_request.php">
                        <i class="fas fa-calendar-times me-2"></i>Ajukan Izin
                    </a>
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Reports/activity_report.php">
                        <i class="fas fa-file-alt me-2"></i>Laporan Kegiatan
                    </a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'siswa_pkl'): ?>
                        <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/bimbingan_pkl.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan
                        </a>
                    <?php endif; ?>
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
                            <h2><i class="fas fa-file-alt me-2"></i>Laporan Kegiatan</h2>
                            <p class="text-muted mb-0">Divisi: <?= htmlspecialchars($participant['division_name']) ?></p>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Form Laporan Kegiatan -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="form-card">
                                <h4 class="mb-4">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Buat Laporan Kegiatan
                                </h4>

                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="title" class="form-label">
                                                <i class="fas fa-heading me-2"></i>Judul Laporan
                                            </label>
                                            <input type="text" class="form-control" id="title" name="title"
                                                   placeholder="Contoh: Laporan Harian - Pengembangan Website" required>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="report_date" class="form-label">
                                                <i class="fas fa-calendar me-2"></i>Tanggal Laporan
                                            </label>
                                            <input type="date" class="form-control" id="report_date" name="report_date"
                                                   value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">
                                            <i class="fas fa-align-left me-2"></i>Deskripsi Kegiatan
                                        </label>
                                        <textarea class="form-control" id="description" name="description" rows="6"
                                                  placeholder="Jelaskan kegiatan yang dilakukan hari ini secara detail..." required></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label for="file" class="form-label">
                                            <i class="fas fa-paperclip me-2"></i>Lampiran File (Opsional)
                                        </label>
                                        <input type="file" class="form-control" id="file" name="file"
                                               accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png">
                                        <div class="form-text">
                                            Format yang didukung: PDF, DOC, DOCX, TXT, JPG, PNG (Maksimal 5MB)
                                        </div>

                                        <div class="file-preview" id="file-preview" style="display: none;">
                                            <i class="fas fa-file fa-2x text-muted mb-2"></i>
                                            <div id="file-name"></div>
                                            <small class="text-muted" id="file-size"></small>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-submit">
                                        <i class="fas fa-save me-2"></i>
                                        Simpan Laporan
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-lightbulb me-2"></i>
                                    Tips Laporan Kegiatan
                                </h5>

                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Format Laporan:</h6>
                                    <ul class="mb-0">
                                        <li>Gunakan judul yang jelas dan deskriptif</li>
                                        <li>Jelaskan kegiatan secara detail dan terstruktur</li>
                                        <li>Sertakan hasil atau pencapaian yang diperoleh</li>
                                        <li>Lampirkan file pendukung jika diperlukan</li>
                                    </ul>
                                </div>

                                <div class="alert alert-success">
                                    <h6><i class="fas fa-check-circle me-2"></i>Contoh Isi Laporan:</h6>
                                    <ul class="mb-0">
                                        <li>Apa yang dikerjakan hari ini</li>
                                        <li>Kendala yang dihadapi</li>
                                        <li>Solusi yang diterapkan</li>
                                        <li>Rencana untuk hari berikutnya</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Laporan -->
                    <div class="row">
                        <div class="col-12">
                            <div class="history-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-history me-2"></i>
                                    Riwayat Laporan Kegiatan
                                </h4>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Judul</th>
                                                <th>Deskripsi</th>
                                                <th>File</th>
                                                <th>Rating</th>
                                                <th>Komentar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reports as $report): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= date('d M Y', strtotime($report['report_date'])) ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= date('H:i', strtotime($report['created_at'])) ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($report['title']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="text-truncate d-inline-block" style="max-width: 200px;"
                                                              title="<?= htmlspecialchars($report['description']) ?>">
                                                            <?= htmlspecialchars(substr($report['description'], 0, 100)) ?>
                                                            <?= strlen($report['description']) > 100 ? '...' : '' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($report['file_path']): ?>
                                                            <a href="<?= APP_URL ?>/<?= htmlspecialchars($report['file_path']) ?>"
                                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-download me-1"></i>
                                                                Download
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($report['rating']): ?>
                                                            <div class="rating-stars">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star<?= $i <= $report['rating'] ? '' : '-o' ?>"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <small class="text-muted">(<?= $report['rating'] ?>/5)</small>
                                                        <?php else: ?>
                                                            <span class="text-muted">Belum dinilai</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($report['supervisor_comment']): ?>
                                                            <span class="text-truncate d-inline-block" style="max-width: 150px;"
                                                                  title="<?= htmlspecialchars($report['supervisor_comment']) ?>">
                                                                <?= htmlspecialchars($report['supervisor_comment']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (empty($reports)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                        Belum ada laporan kegiatan
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // File preview
        document.getElementById('file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('file-preview');
            const fileName = document.getElementById('file-name');
            const fileSize = document.getElementById('file-size');

            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>