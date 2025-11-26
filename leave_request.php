<?php
session_start();
require_once 'config/database.php';

// 🔐 Cek apakah user sudah login dan role peserta (mahasiswa MBKM / siswa PKL)
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['mahasiswa_mbkm', 'siswa_pkl'])
) {
    header('Location: index.php');
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
    header('Location: dashboard.php');
    exit();
}

// Proses pengajuan izin
if ($_POST) {
    $leave_type = $_POST['leave_type'] ?? '';
    $reason     = $_POST['reason'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    
    if ($leave_type && $reason && $start_date && $end_date) {

        // Validasi tanggal (tanggal mulai tidak boleh sebelum hari ini)
        if (strtotime($start_date) < strtotime(date('Y-m-d'))) {
            $message = 'Tanggal mulai tidak boleh lebih dari hari ini!';
            $message_type = 'danger';

        } elseif (strtotime($end_date) < strtotime($start_date)) {
            $message = 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai!';
            $message_type = 'danger';

        } else {
            // ✅ INSERT ke SQL Server + set Status = 'pending'
            try {
                executeQuery(
                    "INSERT INTO leave_requests 
                        (participant_id, request_date, leave_type, reason, start_date, end_date, status) 
                     VALUES 
                        (?, GETDATE(), ?, ?, ?, ?, 'pending')",
                    [$participant['id'], $leave_type, $reason, $start_date, $end_date]
                );

                $message = 'Pengajuan izin berhasil dikirim! Menunggu persetujuan pembimbing.';
                $message_type = 'success';

            } catch (PDOException $e) {
                // Optional: log error internal kalau mau
                $message = 'Terjadi kesalahan saat menyimpan pengajuan izin.';
                $message_type = 'danger';
            }
        }
    } else {
        $message = 'Semua field harus diisi!';
        $message_type = 'danger';
    }
}

// Ambil riwayat pengajuan izin
$leave_requests = fetchAll(
    "SELECT * FROM leave_requests 
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
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        .history-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
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
                <div class="p-3">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Magang/PKL
                    </h4>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="attendance.php">
                        <i class="fas fa-calendar-check me-2"></i>Absensi Harian
                    </a>
                    <a class="nav-link" href="attendance_history.php">
                        <i class="fas fa-history me-2"></i>Riwayat Kehadiran
                    </a>
                    <a class="nav-link active" href="leave_request.php">
                        <i class="fas fa-calendar-times me-2"></i>Ajukan Izin
                    </a>
                    <a class="nav-link" href="activity_report.php">
                        <i class="fas fa-file-alt me-2"></i>Laporan Kegiatan
                    </a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'siswa_pkl'): ?>
                        <a class="nav-link" href="bimbingan_pkl.php">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan
                        </a>
                    <?php endif; ?>
                    <hr class="my-3">
                    <a class="nav-link" href="logout.php">
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
                                
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="leave_type" class="form-label">
                                                <i class="fas fa-tag me-2"></i>Jenis Izin
                                            </label>
                                            <select class="form-select" id="leave_type" name="leave_type" required>
                                                <option value="">Pilih jenis izin</option>
                                                <option value="sakit">Sakit</option>
                                                <option value="izin">Izin Pribadi</option>
                                                <option value="keperluan_mendesak">Keperluan Mendesak</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="start_date" class="form-label">
                                                <i class="fas fa-calendar me-2"></i>Tanggal Mulai
                                            </label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                                   min="<?= date('Y-m-d') ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="end_date" class="form-label">
                                                <i class="fas fa-calendar me-2"></i>Tanggal Selesai
                                            </label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                                   min="<?= date('Y-m-d') ?>" required>
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
                                                <th>Status</th>
                                                <th>Tanggapan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($leave_requests as $request): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= date('d M Y', strtotime($request['request_date'])) ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $type_labels = [
                                                            'sakit'              => 'Sakit',
                                                            'izin'               => 'Izin Pribadi',
                                                            'keperluan_mendesak' => 'Keperluan Mendesak'
                                                        ];
                                                        ?>
                                                        <span class="badge bg-info">
                                                            <?= $type_labels[$request['leave_type']] ?? $request['leave_type'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong><?= date('d M Y', strtotime($request['start_date'])) ?></strong>
                                                        <?php if ($request['start_date'] !== $request['end_date']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                s/d <?= date('d M Y', strtotime($request['end_date'])) ?>
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
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                
                if (diffDays === 1) {
                    durationDisplay.innerHTML = '<span class="badge bg-primary">1 hari</span>';
                } else {
                    durationDisplay.innerHTML = '<span class="badge bg-primary">' + diffDays + ' hari</span>';
                }
            } else {
                durationDisplay.innerHTML = '<span class="text-muted">Pilih tanggal untuk melihat durasi</span>';
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
