<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// 🔐 Cek apakah user sudah login dan role peserta (mahasiswa MBKM / siswa PKL)
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['mahasiswa_mbkm', 'siswa_pkl'])
) {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Ambil data peserta aktif
$participant = fetchOne(
    "SELECT p.*, d.name as division_name
     FROM participants p
     JOIN divisions d ON p.division_id = d.id
     WHERE p.user_id = ? AND p.status = 'aktif'",
    [$user_id]
);

if (!$participant) {
    header('Location: ' . APP_URL . '/public/dashboard.php');
    exit();
}

// Proses pengajuan izin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = $_POST['leave_type'] ?? '';
    $reason     = $_POST['reason'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $attachment_path = null;
    $is_valid = true;

    // Validasi dasar
    if (empty($leave_type) || empty($reason) || empty($start_date) || empty($end_date)) {
        $message = 'Semua field (kecuali lampiran) harus diisi!';
        $message_type = 'danger';
        $is_valid = false;
    }
    // Validasi tanggal - konversi ke format yang konsisten untuk perbandingan
    $current_datetime = date('Y-m-d H:i');
    $start_date_time = $start_date; // Input datetime-local dalam format Y-m-d\TH:i
    $end_date_time = $end_date;     // Input datetime-local dalam format Y-m-d\TH:i

    if (strtotime($start_date_time) < strtotime($current_datetime)) {
        $message = 'Waktu mulai tidak boleh kurang dari waktu saat ini!';
        $message_type = 'danger';
        $is_valid = false;
    } elseif (strtotime($end_date_time) < strtotime($start_date_time)) {
        $message = 'Waktu selesai tidak boleh lebih awal dari waktu mulai!';
        $message_type = 'danger';
        $is_valid = false;
    }

    // Proses upload file jika ada
    if ($is_valid && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $upload_dir = BASE_PATH . 'uploads/izin/';

        // Buat direktori jika belum ada
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['size'] > $max_size) {
            $message = 'Ukuran file tidak boleh lebih dari 5MB!';
            $message_type = 'danger';
            $is_valid = false;
        } elseif (!in_array($file_ext, $allowed_types)) {
            $message = 'Jenis file tidak diizinkan! Hanya PDF, DOC, DOCX, JPG, PNG.';
            $message_type = 'danger';
            $is_valid = false;
        } else {
            // Buat nama file unik
            $new_filename = 'izin_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            $relative_path = 'uploads/izin/' . $new_filename;  // Store relative path in DB

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $attachment_path = $relative_path;
            } else {
                $message = 'Gagal mengupload file lampiran.';
                $message_type = 'danger';
                $is_valid = false;
            }
        }
    }

    // Jika semua valid, simpan ke database
    if ($is_valid) {
        try {
            // Konversi format tanggal dari datetime-local (Y-m-d\TH:i) ke format SQL Server yang kompatibel
            // Kita hanya ambil bagian tanggalnya saja karena kolom di database adalah DATE
            $start_date_for_db = date('Y-m-d', strtotime($start_date));
            $end_date_for_db = date('Y-m-d', strtotime($end_date));

            executeQuery(
                "INSERT INTO leave_requests
                    (participant_id, request_date, leave_type, reason, start_date, end_date, status, File_Path)
                 VALUES
                    (?, GETDATE(), ?, ?, ?, ?, 'pending', ?)",
                [$participant['id'], $leave_type, $reason, $start_date_for_db, $end_date_for_db, $attachment_path]
            );

            $message = 'Pengajuan izin berhasil dikirim! Menunggu persetujuan pembimbing.';
            $message_type = 'success';

        } catch (PDOException $e) {
            $message = 'Terjadi kesalahan saat menyimpan pengajuan izin: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Ambil riwayat pengajuan izin
$leave_requests = fetchAll(
    "SELECT *
     FROM leave_requests
     WHERE participant_id = ?
     ORDER BY created_at DESC",
    [$participant['id']]
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Izin - Aplikasi Pengawasan Magang/PKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <link rel="stylesheet" href="../../assets/css/drawer.css">
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
                    <a class="nav-link active" href="leave_request.php">
                        <i class="fas fa-calendar-times me-2"></i>Ajukan Izin
                    </a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/activity_report.php">
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
                            <h2><i class="fas fa-calendar-times me-2"></i>Ajukan Izin</h2>
                            <p class="text-muted mb-0">Divisi: <?= htmlspecialchars($participant['division_name']) ?></p>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Form Pengajuan Izin -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="form-card">
                                <h4 class="mb-4">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Form Pengajuan Izin
                                </h4>

                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="leave_type" class="form-label">
                                                <i class="fas fa-tag me-2"></i>Jenis Izin
                                            </label>
                                            <select class="form-select" id="leave_type" name="leave_type" required>
                                                <option value="">Pilih jenis izin</option>
                                                <option value="sakit">Sakit</option>
                                                <option value="izin">Izin Pribadi</option>
                                                <option value="izin_akademik">Izin Akademik</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="start_date" class="form-label">
                                                <i class="fas fa-calendar me-2"></i>Tanggal Mulai
                                            </label>
                                            <input type="datetime-local" class="form-control" id="start_date" name="start_date"
                                                   min="<?= date('Y-m-d\TH:i') ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="end_date" class="form-label">
                                                <i class="fas fa-calendar me-2"></i>Tanggal Selesai
                                            </label>
                                            <input type="datetime-local" class="form-control" id="end_date" name="end_date"
                                                   min="<?= date('Y-m-d\TH:i') ?>" required>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-info-circle me-2"></i>Durasi Izin
                                            </label>
                                            <div class="form-control-plaintext" id="duration_display">
                                                <span class="text-muted">Pilih tanggal untuk melihat durasi</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="reason" class="form-label">
                                            <i class="fas fa-comment me-2"></i>Alasan Izin
                                        </label>
                                        <textarea class="form-control" id="reason" name="reason" rows="4"
                                                  placeholder="Jelaskan alasan izin secara detail..." required></textarea>
                                    </div>

                                    <div class="mb-4">
                                        <label for="attachment" class="form-label">
                                            <i class="fas fa-paperclip me-2"></i>Lampiran (Opsional)
                                        </label>
                                        <input type="file" class="form-control" id="attachment" name="attachment">
                                        <div class="form-text">
                                            Jenis file yang diizinkan: PDF, DOC, DOCX, JPG, PNG. Ukuran maks: 5MB.
                                        </div>

                                    <button type="submit" class="btn btn-primary btn-submit">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        Ajukan Izin
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Informasi Penting
                                </h5>

                                <div class="alert alert-info">
                                    <h6><i class="fas fa-lightbulb me-2"></i>Tips Pengajuan Izin:</h6>
                                    <ul class="mb-0">
                                        <li>Ajukan izin minimal 1 hari sebelumnya</li>
                                        <li>Berikan alasan yang jelas dan detail</li>
                                        <li>Lampirkan surat keterangan jika sakit</li>
                                        <li>Pastikan tanggal yang dipilih sudah benar</li>
                                    </ul>
                                </div>

                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Perhatian:</h6>
                                    <ul class="mb-0">
                                        <li>Izin akan diproses oleh pembimbing</li>
                                        <li>Status akan diupdate melalui notifikasi</li>
                                        <li>Izin yang disetujui akan mempengaruhi absensi</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Pengajuan Izin -->
                    <div class="row">
                        <div class="col-12">
                            <div class="history-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-history me-2"></i>
                                    Riwayat Pengajuan Izin
                                </h5>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tanggal Pengajuan</th>
                                                <th>Jenis Izin</th>
                                                <th>Periode</th>
                                                <th>Alasan</th>
                                                <th>Lampiran</th>
                                                <th>Status</th>
                                                <th>Tanggapan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($leave_requests as $request): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= isset($request['request_date']) ? date('d M Y', strtotime($request['request_date'])) : (isset($request['Request_Date']) ? date('d M Y', strtotime($request['Request_Date'])) : 'Tanggal Tidak Tersedia') ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $type_labels = [
                                                            'sakit'              => 'Sakit',
                                                            'izin'               => 'Izin Pribadi',
                                                            'keperluan_mendesak' => 'Izin Akademik',
                                                            'izin_akademik'      => 'Izin Akademik'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-info">
                                                            <?= $type_labels[$request['leave_type']] ?? $request['leave_type'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong><?= date('d M Y H:i', strtotime($request['start_date'])) ?></strong>
                                                        <?php if ($request['start_date'] !== $request['end_date']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                s/d <?= date('d M Y H:i', strtotime($request['end_date'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="text-truncate d-inline-block" style="max-width: 200px;"
                                                              title="<?= htmlspecialchars($request['reason']) ?>">
                                                            <?= htmlspecialchars($request['reason']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($request['file_path'])): ?>
                                                            <a href="<?= APP_URL ?>/<?= htmlspecialchars($request['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-download me-1"></i> Lihat
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Tidak ada</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status_class = '';
                                                        $status_icon  = '';
                                                        switch ($request['status']) {
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
                                                            default:
                                                                $status_class = 'secondary';
                                                                $status_icon  = 'question-circle';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?= $status_class ?> status-badge">
                                                            <i class="fas fa-<?= $status_icon ?> me-1"></i>
                                                            <?= ucfirst($request['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($request['status'] !== 'pending'): ?>
                                                            <small class="text-muted">
                                                                <?= $request['approved_at'] ? date('d M Y H:i', strtotime($request['approved_at'])) : '' ?>
                                                            </small>
                                                            <?php if ($request['notes']): ?>
                                                                <br>
                                                                <span class="text-truncate d-inline-block" style="max-width: 150px;"
                                                                      title="<?= htmlspecialchars($request['notes']) ?>">
                                                                    <?= htmlspecialchars($request['notes']) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Menunggu</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (empty($leave_requests)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                        Belum ada pengajuan izin
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

    <div class="drawer-backdrop"></div>
    <button class="btn drawer-toggle floating-toggle" aria-label="Toggle menu" style="position:fixed;bottom:18px;left:18px;z-index:1400;">☰</button>
    <script src="../../assets/js/drawer.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update durasi izin
        function updateDuration() {
            const startDate = document.getElementById('start_date').value;
            const endDate   = document.getElementById('end_date').value;
            const durationDisplay = document.getElementById('duration_display');

            if (startDate && endDate) {
                const start = new Date(startDate);
                const end   = new Date(endDate);
                const diffTime = Math.abs(end - start);
                const diffHours = Math.ceil(diffTime / (1000 * 60 * 60));

                if (diffHours === 1) {
                    durationDisplay.innerHTML = '<span class="badge bg-primary">1 jam</span>';
                } else {
                    durationDisplay.innerHTML = '<span class="badge bg-primary">' + diffHours + ' jam</span>';
                }
            } else {
                durationDisplay.innerHTML = '<span class="text-muted">Pilih waktu untuk melihat durasi</span>';
            }
        }

        document.getElementById('start_date').addEventListener('change', updateDuration);
        document.getElementById('end_date').addEventListener('change', updateDuration);

        // Set minimum end date based on start date
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate   = this.value;
            const endDateInput = document.getElementById('end_date');
            if (startDate) {
                endDateInput.min = startDate;
            }
        });
    </script>
</body>
</html>
