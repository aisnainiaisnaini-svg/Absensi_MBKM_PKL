<?php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . 'config/database.php';

// Hanya untuk siswa PKL
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa_pkl') {
    header('Location: index.php');
    exit();
}

$user_id      = $_SESSION['user_id'];
$full_name    = $_SESSION['full_name'];
$message      = '';
$message_type = '';

// === LOGIKA TETAP SAMA (tidak diubah) ===
$participant = fetchOne("SELECT id FROM participants WHERE user_id = ?", [$user_id]);
if (!$participant) die("Data peserta PKL tidak ditemukan. Hubungi admin.");
$participant_id = $participant['id'];

// Proses tambah bimbingan
if ($_POST && $_POST['action'] ?? '' === 'add_guidance') {
    $title         = trim($_POST['title'] ?? '');
    $preferred_day = trim($_POST['preferred_day'] ?? '');
    $question_text = trim($_POST['question_text'] ?? '');

    if ($title && $preferred_day && $question_text) {
        executeQuery(
            "INSERT INTO Guidance_PKL (Participant_Id, Title, Preferred_Day, Question_Text, Status) VALUES (?, ?, ?, ?, 'pending')",
            [$participant_id, $title, $preferred_day, $question_text]
        );
        $message = "Permintaan bimbingan berhasil dikirim. Tunggu respon admin.";
        $message_type = "success";
    } else {
        $message = "Semua field wajib diisi.";
        $message_type = "danger";
    }
}

// Proses withdraw
if ($_POST && $_POST['action'] ?? '' === 'withdraw_guidance') {
    $guidance_id = $_POST['guidance_id'] ?? 0;
    if ($guidance_id) {
        $current = fetchOne("SELECT Status FROM Guidance_PKL WHERE Id = ? AND Participant_Id = ?", [$guidance_id, $participant_id]);
        if ($current && $current['Status'] === 'pending') {
            executeQuery("UPDATE Guidance_PKL SET Status = 'withdrawn' WHERE Id = ? AND Participant_Id = ?", [$guidance_id, $participant_id]);
            $message = "Permintaan bimbingan berhasil ditarik.";
            $message_type = "success";
        } else {
            $message = "Hanya permintaan 'pending' yang bisa ditarik.";
            $message_type = "danger";
        }
    }
}

$guidances = fetchAll("
    SELECT g.*, p.Company_Supervisor, p.School_Supervisor
    FROM Guidance_PKL g
    JOIN participants p ON g.Participant_Id = p.id
    WHERE g.Participant_Id = ?
    ORDER BY g.Created_At DESC
", [$participant_id]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bimbingan PKL - Siswa PKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; margin: 0; }

        /* SIDEBAR */
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
            transform: translateX(0); /* Desktop: selalu muncul */
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 12px 20px;
            border-radius: 10px;
            margin: 4px 12px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(8px);
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            padding: 20px;
            transition: margin-left 0.3s ease-in-out;
        }

        .form-card, .history-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.07);
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px;
        }
        .form-control:focus, .form-select:focus {
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
        }

        /* OVERLAY */
        .sidebar-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1030;
            display: none;
        }
        .sidebar-overlay.show { display: block; }

        /* RESPONSIVE - Mobile & Tablet */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>

    <!-- HAMBURGER BUTTON - PASTI KELIHATAN! -->
    <button class="btn btn-primary rounded-circle shadow-lg d-lg-none position-fixed" 
            style="top: 12px; left: 12px; z-index: 9999; width: 56px; height: 56px; font-size: 1.5rem;"
            id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Overlay gelap saat sidebar buka -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->
    <div class="sidebar d-flex flex-column" id="adminSidebar">
        <div class="p-4 text-center border-bottom border-light border-opacity-25">
            <h4 class="mb-1"><i class="fas fa-graduation-cap me-2"></i>Magang / PKL</h4>
            <small>Halo, <?= htmlspecialchars($full_name) ?></small>
        </div>

        <nav class="nav flex-column flex-grow-1 px-2 py-3">
            <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi Harian</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Attendance/attendance_history.php"><i class="fas fa-history me-2"></i>Riwayat Kehadiran</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_request.php"><i class="fas fa-calendar-times me-2"></i>Ajukan Izin</a>

            <?php if ($_SESSION['role'] === 'siswa_pkl'): ?>
                <a class="nav-link active" href="<?= APP_URL ?>/app/Guidance/bimbingan_pkl.php">
                    <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan
                </a>
            <?php endif; ?>

            <hr class="my-4 opacity-25">
            <a class="nav-link text-danger" href="<?= APP_URL ?>/public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="container-fluid">

            <h2 class="mb-2"><i class="fas fa-comments me-2"></i>Bimbingan PKL</h2>
            <p class="text-muted mb-4">Ajukan bimbingan dan lihat respon pembimbing</p>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Form Ajukan Bimbingan -->
            <div class="form-card mb-4">
                <h4 class="mb-4"><i class="fas fa-plus-circle me-2"></i>Ajukan Bimbingan Baru</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="add_guidance">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Judul / Topik</label>
                            <input type="text" class="form-control" name="title" required placeholder="Contoh: Revisi Laporan Mingguan">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Jadwal Bimbingan</label>
                            <select class="form-select" name="preferred_day" required>
                                <option value="">Pilih Hari & Jam</option>
                                <option>Senin, 09:00</option><option>Senin, 14:00</option>
                                <option>Selasa, 10:00</option><option>Selasa, 15:00</option>
                                <option>Rabu, 09:00</option><option>Rabu, 13:00</option>
                                <option>Kamis, 10:00</option><option>Kamis, 14:00</option>
                                <option>Jumat, 09:00</option><option>Jumat, 13:00</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Pertanyaan / Ringkasan</label>
                            <textarea class="form-control" name="question_text" rows="4" required placeholder="Jelaskan singkat permasalahan atau bahan yang ingin dibimbing..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane me-2"></i>Kirim Bimbingan
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Riwayat Bimbingan -->
            <div class="history-card">
                <h4 class="mb-3"><i class="fas fa-history me-2"></i>Riwayat Bimbingan</h4>
                <?php if (empty($guidances)): ?>
                    <p class="text-center text-muted py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i><br>Belum ada riwayat bimbingan.
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
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($guidances as $g): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($g['Title']) ?></strong></td>
                                        <td><?= htmlspecialchars($g['Preferred_Day']) ?></td>
                                        <td><?= date('d M Y H:i', strtotime($g['Created_At'])) ?></td>
                                        <td>
                                            <?php
                                            $badge = match(strtolower($g['Status'])) {
                                                'pending' => 'warning',
                                                'diproses','bimbing' => 'info',
                                                'selesai' => 'success',
                                                'withdrawn' => 'secondary',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $badge ?>"><?= ucfirst($g['Status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($g['Admin_Response']): ?>
                                                <small><?= nl2br(htmlspecialchars($g['Admin_Response'])) ?></small>
                                                <?php if ($g['Responded_At']): ?>
                                                    <div class="text-muted small mt-1">
                                                        <?= date('d M Y H:i', strtotime($g['Responded_At'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Menunggu jawaban</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($g['Status'] === 'pending'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menarik permintaan ini?')">
                                                    <input type="hidden" name="action" value="withdraw_guidance">
                                                    <input type="hidden" name="guidance_id" value="<?= $g['Id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Withdraw</button>
                                                </form>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('adminSidebar');
        const overlay  = document.getElementById('sidebarOverlay');
        const toggle   = document.getElementById('sidebarToggle');

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        // Tutup otomatis setelah klik menu di mobile
        document.querySelectorAll('#adminSidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 991) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>