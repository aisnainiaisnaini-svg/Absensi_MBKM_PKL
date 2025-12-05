<?php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . 'config/database.php';

// Hanya admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

// ================ FILTER & SEARCH ================
$keyword = $_GET['search'] ?? '';
$filter_division = $_GET['division'] ?? '';

$params = [];
$where = "WHERE u.role = 'siswa_pkl'";

if (!empty($keyword)) {
    $where .= ' AND u.full_name LIKE ?';
    $params[] = "%$keyword%";
}
if (!empty($filter_division)) {
    $where .= ' AND p.division_id = ?';
    $params[] = $filter_division;
}

// Query peserta PKL
$participants = fetchAll("
    SELECT 
        p.school, p.major, p.start_date, p.end_date, p.status, p.user_id,
        p.company_supervisor, p.school_supervisor,
        u.full_name,
        d.name AS division_name
    FROM participants p
    JOIN users u ON p.user_id = u.id
    JOIN divisions d ON p.division_id = d.id
    $where
    ORDER BY u.full_name
", $params);

$divisions = fetchAll('SELECT id, name FROM divisions ORDER BY name ASC');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa PKL - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }

        /* Sidebar */
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
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(8px);
        }

        /* Main content */
        .main-content {
            margin-left: 260px;
            transition: margin-left 0.3s ease-in-out;
            min-height: 100vh;
        }

        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        /* Mobile & Tablet */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }

            .sidebar-overlay {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1030;
                display: none;
            }
            .sidebar-overlay.show { display: block; }
        }
    </style>
</head>
<body>

    <!-- Hamburger Button (hanya di mobile/tablet) -->
    <button class="btn btn-primary rounded-circle shadow-lg d-lg-none position-fixed top-0 start-0 m-3" 
            style="z-index:1050; width:50px; height:50px;" id="sidebarToggle">
        <i class="fas fa-bars fa-lg"></i>
    </button>

    <!-- Overlay saat sidebar terbuka -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column" id="adminSidebar">
        <div class="p-4 text-center border-bottom border-light border-opacity-25">
            <h4 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Admin Panel</h4>
        </div>
        <nav class="nav flex-column flex-grow-1 px-2 py-3">
            <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Admin/users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola Peserta</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola MBKM</a>
            <a class="nav-link active" href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/admin_bimbingan_pkl.php"><i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan Izin</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Reports/reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review Laporan</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Admin/divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Reports/admin_reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>
            <hr class="my-4 opacity-25">
            <a class="nav-link text-danger" href="<?= APP_URL ?>/public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content p-4 p-lg-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <h2 class="mb-0"><i class="fas fa-users me-2"></i>Data Siswa PKL</h2>
            <a href="<?= APP_URL ?>/app/Participants/admin_add_pkl.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tambah Siswa PKL
            </a>
        </div>

        <!-- Notifikasi sukses -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>Siswa PKL berhasil ditambahkan!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabel -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama Lengkap</th>
                            <th>Asal Sekolah</th>
                            <th>Jurusan</th>
                            <th>Divisi</th>
                            <th>Status</th>
                            <th>Periode</th>
                            <th>Pembimbing Perusahaan</th>
                            <th>Pembimbing Sekolah</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($participants)): ?>
                            <?php $no = 1; foreach ($participants as $p): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($p['school']) ?></td>
                                    <td><?= htmlspecialchars($p['major']) ?></td>
                                    <td><?= htmlspecialchars($p['division_name']) ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= ucfirst($p['status']) ?></span>
                                    </td>
                                    <td class="text-nowrap">
                                        <?= date('d M Y', strtotime($p['start_date'])) ?>
                                        – <?= date('d M Y', strtotime($p['end_date'])) ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['company_supervisor'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['school_supervisor'] ?? '-') ?></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/app/Participants/admin_edit_participant_pkl.php?user_id=<?= $p['user_id'] ?>"
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Kelola
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    Tidak ada data siswa PKL
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('adminSidebar');
        const overlay  = document.getElementById('sidebarOverlay');
        const toggle   = document.getElementById('sidebarToggle');

        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        // Tutup otomatis saat klik menu (mobile)
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