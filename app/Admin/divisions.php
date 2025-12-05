<?php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . 'config/database.php';

// Hanya untuk admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$message = '';
$message_type = '';

// Proses tambah divisi
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_division') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';

    if ($name) {
        $existing_division = fetchOne('SELECT id FROM divisions WHERE name = ?', [$name]);

        if ($existing_division) {
            $message = 'Nama divisi sudah digunakan!';
            $message_type = 'danger';
        } else {
            executeQuery('INSERT INTO divisions (name, description) VALUES (?, ?)', [$name, $description]);
            $message = 'Divisi berhasil ditambahkan!';
            $message_type = 'success';
        }
    } else {
        $message = 'Nama divisi harus diisi!';
        $message_type = 'danger';
    }
}

// Proses edit divisi
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit_division') {
    $division_id = $_POST['division_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';

    if ($division_id && $name) {
        $existing_division = fetchOne('SELECT id FROM divisions WHERE name = ? AND id != ?', [$name, $division_id]);

        if ($existing_division) {
            $message = 'Nama divisi sudah digunakan!';
            $message_type = 'danger';
        } else {
            executeQuery('UPDATE divisions SET name = ?, description = ? WHERE id = ?', [$name, $description, $division_id]);
            $message = 'Divisi berhasil diupdate!';
            $message_type = 'success';
        }
    } else {
        $message = 'Nama divisi harus diisi!';
        $message_type = 'danger';
    }
}

// Proses hapus divisi
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_division') {
    $division_id = $_POST['division_id'] ?? '';

    if ($division_id) {
        $participants_count = fetchOne('SELECT COUNT(*) as count FROM participants WHERE division_id = ?', [$division_id]);

        if ($participants_count['count'] > 0) {
            $message = 'Tidak dapat menghapus divisi yang masih digunakan oleh peserta!';
            $message_type = 'danger';
        } else {
            executeQuery('DELETE FROM divisions WHERE id = ?', [$division_id]);
            $message = 'Divisi berhasil dihapus!';
            $message_type = 'success';
        }
    }
}

// Ambil semua divisi
$divisions = fetchAll("SELECT d.*, 
                     (SELECT COUNT(*) FROM participants p WHERE p.division_id = d.id) as participant_count
                     FROM divisions d 
                     ORDER BY d.name");

// Hitung statistik
$total_divisions = count($divisions);
$total_participants = array_sum(array_column($divisions, 'participant_count'));
$active_divisions = count(array_filter($divisions, fn($d) => $d['participant_count'] > 0));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Divisi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }

        /* ========================================= */
        /* SIDEBAR - IDENTIK DENGAN admin_participants */
        /* ========================================= */
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
            font-weight: 500;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* ========================================= */
        /* MAIN CONTENT - IDENTIK DENGAN admin_participants */
        /* ========================================= */
        .main-content {
            margin-left: 260px;
            transition: margin-left 0.3s ease-in-out;
            min-height: 100vh;
            padding: 20px 0;
        }

        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        /* Stats cards */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            transition: transform 0.3s;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .stats-card:hover { 
            transform: translateY(-5px); 
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
        .bg-primary-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        .bg-info-gradient {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        /* Header buttons */
        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Custom buttons */
        .btn-purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-purple:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .badge-purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* ========================================= */
        /* MOBILE & TABLET - IDENTIK DENGAN admin_participants */
        /* ========================================= */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
                padding: 70px 15px 20px !important;
            }
            .sidebar-overlay {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1030;
                display: none;
            }
            .sidebar-overlay.show { display: block; }

            .header-actions {
                justify-content: center;
                width: 100%;
                margin-top: 15px;
            }
        }

        @media (min-width: 992px) {
            .main-content {
                padding: 40px;
            }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            opacity: 0.5;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

    <!-- ========================================= -->
    <!-- HAMBURGER BUTTON - IDENTIK -->
    <!-- ========================================= -->
    <button class="btn btn-primary rounded-circle shadow-lg d-lg-none position-fixed top-0 start-0 m-3" 
            style="z-index: 1050; width: 50px; height: 50px;" id="sidebarToggle">
        <i class="fas fa-bars fa-lg"></i>
    </button>

    <!-- ========================================= -->
    <!-- OVERLAY - IDENTIK -->
    <!-- ========================================= -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ========================================= -->
    <!-- SIDEBAR - IDENTIK -->
    <!-- ========================================= -->
    <div class="sidebar d-flex flex-column" id="divisionsSidebar">
        <div class="p-4 text-center border-bottom border-light border-opacity-25">
            <h4 class="mb-0">
                <i class="fas fa-graduation-cap me-2"></i>
                Admin Panel
            </h4>
        </div>
        <nav class="nav flex-column flex-grow-1 px-2 py-3">
            <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Admin/users.php">
                <i class="fas fa-users-cog me-2"></i>Kelola User
            </a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php">
                <i class="fas fa-user-graduate me-2"></i>Kelola Peserta
            </a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_mbkm.php">
                <i class="fas fa-user me-2"></i>Kelola MBKM
            </a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php">
                <i class="fas fa-user me-2"></i>Kelola PKL
            </a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/admin_bimbingan_pkl.php">
                <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL
            </a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php">
                <i class="fas fa-check-circle me-2"></i>Persetujuan Izin
            </a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Reports/reports_review.php">
                <i class="fas fa-clipboard-check me-2"></i>Review Laporan
            </a>
            <a class="nav-link active" href="<?= APP_URL ?>/app/Admin/divisions.php">
                <i class="fas fa-building me-2"></i>Kelola Divisi
            </a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Reports/admin_reports.php">
                <i class="fas fa-chart-bar me-2"></i>Laporan Sistem
            </a>
            <hr class="my-4 opacity-25">
            <a class="nav-link text-danger" href="<?= APP_URL ?>/public/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>

    <!-- ========================================= -->
    <!-- MAIN CONTENT -->
    <!-- ========================================= -->
    <div class="main-content p-4 p-lg-5">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-building text-primary me-2"></i>
                    Kelola Divisi
                </h2>
                <p class="text-muted mb-0">Manajemen divisi perusahaan</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-purple" data-bs-toggle="modal" data-bs-target="#addDivisionModal">
                    <i class="fas fa-plus me-2"></i>Tambah Divisi
                </button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="table-card">
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-4">
                    <div class="stats-card">
                        <div class="stats-icon bg-primary-gradient">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="mb-1"><?= $total_divisions ?></h3>
                        <p class="text-muted mb-0">Total Divisi</p>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="stats-card">
                        <div class="stats-icon bg-success-gradient">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="mb-1"><?= $total_participants ?></h3>
                        <p class="text-muted mb-0">Total Peserta</p>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <div class="stats-card">
                        <div class="stats-icon bg-info-gradient">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3 class="mb-1"><?= $active_divisions ?></h3>
                        <p class="text-muted mb-0">Divisi Aktif</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <h5 class="mb-4">
                <i class="fas fa-list me-2"></i>
                Daftar Divisi (<?= $total_divisions ?>)
            </h5>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="8%">ID</th>
                            <th>Nama Divisi</th>
                            <th>Deskripsi</th>
                            <th width="12%">Peserta</th>
                            <th width="12%">Dibuat</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($divisions)): ?>
                            <?php foreach ($divisions as $d): ?>
                                <tr>
                                    <td><strong>#<?= $d['id'] ?></strong></td>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($d['name']) ?></strong>
                                    </td>
                                    <td class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($d['description']) ?>">
                                        <?php if ($d['description']): ?>
                                            <?= htmlspecialchars($d['description']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-purple">
                                            <?= $d['participant_count'] ?> peserta
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <?= date('d M Y', strtotime($d['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-warning" 
                                                    onclick="editDivision(<?= $d['id'] ?>,'<?= addslashes(htmlspecialchars($d['name'])) ?>','<?= addslashes(htmlspecialchars($d['description'])) ?>')"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($d['participant_count'] == 0): ?>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="deleteDivision(<?= $d['id'] ?>,'<?= addslashes(htmlspecialchars($d['name'])) ?>')"
                                                        title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Tidak dapat dihapus (masih digunakan)">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="fas fa-building"></i>
                                        <h5 class="mb-3">Belum ada divisi</h5>
                                        <p class="mb-0">Tambahkan divisi pertama Anda</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- ADD DIVISION MODAL -->
    <!-- ========================================= -->
    <div class="modal fade" id="addDivisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Tambah Divisi Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_division">
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Nama Divisi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   placeholder="Contoh: Divisi IT">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Deskripsi singkat tentang divisi ini..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Tambah Divisi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- EDIT DIVISION MODAL -->
    <!-- ========================================= -->
    <div class="modal fade" id="editDivisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Divisi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_division">
                        <input type="hidden" name="division_id" id="edit_division_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label fw-semibold">Nama Divisi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Update Divisi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- DELETE DIVISION MODAL -->
    <!-- ========================================= -->
    <div class="modal fade" id="deleteDivisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_division">
                        <input type="hidden" name="division_id" id="delete_division_id">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Apakah Anda yakin ingin menghapus divisi <strong id="delete_division_name"></strong>?
                        </div>
                        <p class="text-danger mb-0"><strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Hapus Divisi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ========================================= -->
    <!-- JAVASCRIPT - IDENTIK DENGAN admin_participants */
    <!-- ========================================= -->
    <script>
        const sidebar = document.getElementById('divisionsSidebar');
        const overlay  = document.getElementById('sidebarOverlay');
        const toggle   = document.getElementById('sidebarToggle');

        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        // Tutup saat klik overlay
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        // Tutup saat klik link di dalam sidebar
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 991) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                }
            });
        });

        // Modal functions
        function editDivision(id, name, description) {
            document.getElementById('edit_division_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_description').value = description;
            new bootstrap.Modal(document.getElementById('editDivisionModal')).show();
        }

        function deleteDivision(id, name) {
            document.getElementById('delete_division_id').value = id;
            document.getElementById('delete_division_name').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteDivisionModal')).show();
        }
    </script>
</body>
</html>