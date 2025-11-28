<?php
session_start();
require_once __DIR__ . '/../../config/app.php'; // Include the main configuration file first
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
        // Cek apakah nama divisi sudah ada
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
        // Cek apakah nama divisi sudah ada (kecuali divisi yang sedang diedit)
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
        // Cek apakah ada peserta yang menggunakan divisi ini
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

// Ambil data divisi untuk edit
$edit_division = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_division = fetchOne('SELECT * FROM divisions WHERE id = ?', [$_GET['edit']]);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Divisi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 12px 20px;
            margin: 6px 10px;
            border-radius: 12px;
            transition: all .25s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            transform: translateX(6px);
        }

        /* Main content */
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }

        /* Cards */
        .form-card,
        .stats-card,
        .table-card {
            background: #fff;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            margin: 0 auto 10px;
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

        table.table-hover tbody tr:hover {
            background: #f2f4ff !important;
        }

        .btn-purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
        }

        .btn-purple:hover {
            opacity: .9;
            color: #fff;
        }

        .badge-purple {
            background: #667eea;
        }
    </style>
</head>

<body>

    <div class="container-fluid">
        <div class="row">

            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3">
                    <h4 class="text-center mb-3">
                        <i class="fas fa-cogs me-2"></i>Admin Panel
                    </h4>
                </div>

                <nav class="nav flex-column">
                    <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola Peserta</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola MBKM</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Guidance/admin_bimbingan_pkl.php"><i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan Izin</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review Laporan</a>
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Admin/divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/admin_reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>

                    <hr class="my-3">
                    <a class="nav-link" href="<?= APP_URL ?>/public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">

                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-0"><i class="fas fa-building me-2"></i>Kelola Divisi</h2>
                            <p class="text-muted">Manajemen divisi perusahaan</p>
                        </div>

                        <button class="btn btn-purple px-4" data-bs-toggle="modal" data-bs-target="#addDivisionModal">
                            <i class="fas fa-plus me-2"></i>Tambah Divisi
                        </button>
                    </div>

                    <!-- Alerts -->
                    <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                        <i class="fas fa-info-circle me-2"></i><?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="row mb-4">

                        <div class="col-md-4 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-primary-gradient"><i class="fas fa-building"></i></div>
                                <h3><?= count($divisions) ?></h3>
                                <p class="text-muted mb-0">Total Divisi</p>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-success-gradient"><i class="fas fa-users"></i></div>
                                <h3><?= array_sum(array_column($divisions, 'participant_count')) ?></h3>
                                <p class="text-muted mb-0">Total Peserta</p>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon bg-info-gradient"><i class="fas fa-chart-pie"></i></div>
                                <h3><?= count(array_filter($divisions, fn($d) => $d['participant_count'] > 0)) ?></h3>
                                <p class="text-muted mb-0">Divisi Aktif</p>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-card">
                        <h5 class="mb-3"><i class="fas fa-list me-2"></i>Daftar Divisi</h5>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Divisi</th>
                                        <th>Deskripsi</th>
                                        <th>Peserta</th>
                                        <th>Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($divisions as $d): ?>
                                    <tr>
                                        <td><?= $d['id'] ?></td>

                                        <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>

                                        <td>
                                            <?php if ($d['description']): ?>
                                            <span class="d-inline-block text-truncate" style="max-width:200px"
                                                title="<?= htmlspecialchars($d['description']) ?>">
                                                <?= htmlspecialchars($d['description']) ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="badge badge-purple"><?= $d['participant_count'] ?>
                                                peserta</span>
                                        </td>

                                        <td><?= date('d M Y', strtotime($d['created_at'])) ?></td>

                                        <td>
                                            <button class="btn btn-sm btn-warning me-1"
                                                onclick="editDivision(<?= $d['id'] ?>,'<?= htmlspecialchars($d['name']) ?>','<?= htmlspecialchars($d['description']) ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <?php if ($d['participant_count'] == 0): ?>
                                            <button class="btn btn-sm btn-danger"
                                                onclick="deleteDivision(<?= $d['id'] ?>,'<?= htmlspecialchars($d['name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <i class="fas fa-lock text-muted" title="Tidak dapat dihapus"></i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($divisions)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                            Belum ada divisi
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

    <!-- Add Division Modal -->
    <div class="modal fade" id="addDivisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Tambah Divisi Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_division">

                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Divisi</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Tambah Divisi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Division Modal -->
    <div class="modal fade" id="editDivisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Divisi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_division">
                        <input type="hidden" name="division_id" id="edit_division_id">

                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Nama Divisi</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Update Divisi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Division Modal -->
    <div class="modal fade" id="deleteDivisionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_division">
                        <input type="hidden" name="division_id" id="delete_division_id">
                        <p>Apakah Anda yakin ingin menghapus divisi <strong id="delete_division_name"></strong>?</p>
                        <p class="text-danger">Tindakan ini tidak dapat dibatalkan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus Divisi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
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
