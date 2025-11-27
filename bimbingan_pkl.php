<?php
session_start();
require_once 'config/database.php';

// Hanya untuk siswa PKL
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa_pkl') {
    header('Location: index.php');
    exit();
}

$user_id      = $_SESSION['user_id'];
$full_name    = $_SESSION['full_name'];
$message      = '';
$message_type = '';

// Ambil participant_id dari user yang login
$participant = fetchOne("
    SELECT id 
    FROM participants 
    WHERE user_id = ?
", [$user_id]);

if (!$participant) {
    die("Data peserta PKL tidak ditemukan. Hubungi admin.");
}

$participant_id = $participant['id'];

// PROSES TAMBAH Bimbingan
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_guidance') {
    $title          = trim($_POST['title'] ?? '');
    $preferred_day  = trim($_POST['preferred_day'] ?? '');

    if ($title && $preferred_day) {
        executeQuery("
            INSERT INTO Guidance_PKL (Participant_Id, Title, Preferred_Day, Status)
            VALUES (?, ?, ?, 'pending')
        ", [$participant_id, $title, $preferred_day]);

        $message      = "Permintaan bimbingan berhasil dikirim. Tunggu respon admin.";
        $message_type = "success";
    } else {
        $message      = "Judul dan hari wajib diisi.";
        $message_type = "danger";
    }
}

// Ambil semua bimbingan milik siswa ini
$guidances = fetchAll("
    SELECT 
        Id,
        Title,
        Preferred_Day,
        Admin_Response,
        Status,
        Created_At,
        Responded_At
    FROM Guidance_PKL
    WHERE Participant_Id = ?
    ORDER BY Created_At DESC
", [$participant_id]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bimbingan PKL - Siswa PKL</title>
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
            margin: 5px 10px;
            border-radius: 10px;
            transition: 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: #fff;
            transform: translateX(5px);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .form-card, .history-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.07);
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: #fff;
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        .card-shadow {
            border-radius: 15px;
            box-shadow: 0 7px 18px rgba(0,0,0,0.08);
        }
    </style>
</head>

<body>
<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-md-3 col-lg-2 sidebar p-0">
            <div class="p-3 text-center">
                <h4><i class="fas fa-graduation-cap me-2"></i>Magang / PKL</h4>
                <small>Halo, <?= htmlspecialchars($full_name) ?></small>
            </div>

            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi Harian</a>
                <a class="nav-link" href="attendance_history.php"><i class="fas fa-history me-2"></i>Riwayat Kehadiran</a>
                <a class="nav-link" href="leave_request.php"><i class="fas fa-calendar-times me-2"></i>Ajukan Izin</a>

                <!-- TAMPILKAN HANYA UNTUK siswa_pkl -->
                <?php if ($_SESSION['role'] === 'siswa_pkl'): ?>
                    <a class="nav-link active" href="bimbingan_pkl.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan
                    </a>
                <?php endif; ?>

                <hr class="my-3">
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </nav>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-9 col-lg-10 main-content">
            <div class="p-4">

                <!-- HEADER -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button class="btn drawer-toggle me-2" aria-label="Toggle menu">☰</button>
                        <div>
                            <h2><i class="fas fa-comments me-2"></i>Bimbingan PKL</h2>
                            <p class="text-muted mb-0">Ajukan bimbingan dan lihat respon pembimbing PKL</p>
                        </div>
                    </div>
                </div>

                <!-- MESSAGE -->
                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- FORM BIMBINGAN -->
                <div class="form-card mb-4">
                    <h4 class="mb-4">
                        <i class="fas fa-plus-circle me-2"></i>Ajukan Bimbingan Baru
                    </h4>

                    <form method="POST">
                        <input type="hidden" name="action" value="add_guidance">

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-heading me-2"></i>Judul / Topik</label>
                            <input type="text" class="form-control" name="title" required
                                   placeholder="Contoh: Revisi Laporan Mingguan">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-calendar-day me-2"></i>Jadwal Bimbingan</label>
                            <select class="form-control" name="preferred_day" required>
                                <option value="">Pilih Jadwal Bimbingan</option>
                                <option value="Senin, 09:00">Senin, 09:00</option>
                                <option value="Senin, 14:00">Senin, 14:00</option>
                                <option value="Selasa, 10:00">Selasa, 10:00</option>
                                <option value="Selasa, 15:00">Selasa, 15:00</option>
                                <option value="Rabu, 09:00">Rabu, 09:00</option>
                                <option value="Rabu, 13:00">Rabu, 13:00</option>
                                <option value="Kamis, 10:00">Kamis, 10:00</option>
                                <option value="Kamis, 14:00">Kamis, 14:00</option>
                                <option value="Jumat, 09:00">Jumat, 09:00</option>
                                <option value="Jumat, 13:00">Jumat, 13:00</option>
                            </select>
                        </div>

                        <button class="btn-submit">
                            <i class="fas fa-paper-plane me-2"></i>Kirim Bimbingan
                        </button>
                    </form>
                </div>

                <!-- RIWAYAT BIMBINGAN -->
                <div class="history-card">
                    <h4 class="mb-3">
                        <i class="fas fa-history me-2"></i>Riwayat Bimbingan
                    </h4>

                    <?php if (empty($guidances)): ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                            Belum ada bimbingan.
                        </p>
                    <?php else: ?>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Judul</th>
                                    <th>Hari</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Jawaban</th>
                                </tr>
                                </thead>

                                <tbody>
                                <?php $i = 1; foreach ($guidances as $g): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($g['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($g['preferred_day']) ?></td>
                                        <td><?= date('d M Y H:i', strtotime($g['created_at'])) ?></td>

                                        <td>
                                            <?php
                                            $badge = [
                                                'pending' => 'warning',
                                                'diproses' => 'info',
                                                'selesai' => 'success'
                                            ][$g['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $badge ?>"><?= ucfirst($g['status']) ?></span>
                                        </td>

                                        <td>
                                            <?php if ($g['admin_response']): ?>
                                                <div class="small"><?= nl2br(htmlspecialchars($g['admin_response'])) ?></div>
                                                <?php if ($g['responded_at']): ?>
                                                    <div class="small text-muted mt-1">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= date('d M Y H:i', strtotime($g['responded_at'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Belum ada jawaban</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

</script>
<div class="drawer-backdrop"></div>
<script src="assets/js/drawer.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>