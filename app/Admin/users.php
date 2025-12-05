<?php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . 'config/database.php';

// Cek login admin (sama seperti sebelumnya)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$message = '';
$message_type = '';

// === PROSES TAMBAH USER === (sama seperti kode kamu)
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    // ... (logika add_user kamu tetap sama)
    // saya biarkan utuh agar tidak terpotong
    $username   = $_POST['username'] ?? '';
    $email      = $_POST['email'] ?? '';
    $full_name  = $_POST['full_name'] ?? '';
    $role       = $_POST['role'] ?? '';
    $password   = $_POST['password'] ?? '';

    if ($username && $email && $full_name && $role && $password) {
        if (!in_array($role, ['admin', 'mahasiswa_mbkm', 'siswa_pkl'], true)) {
            $message = 'Role tidak valid.';
            $message_type = 'danger';
        } else {
            $existing_user = fetchOne('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email]);
            if ($existing_user) {
                $message = 'Username atau email sudah digunakan!';
                $message_type = 'danger';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                executeQuery(
                    "INSERT INTO users (username, email, full_name, role, password) VALUES (?, ?, ?, ?, ?)",
                    [$username, $email, $full_name, $role, $hashed_password]
                );
                $message = 'User berhasil ditambahkan!';
                $message_type = 'success';
            }
        }
    } else {
        $message = 'Semua field wajib diisi!';
        $message_type = 'danger';
    }
}

$users = fetchAll('SELECT * FROM users ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; margin: 0; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 10px;
            margin: 5px 15px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.2);
            transform: translateX(8px);
        }
        .main-content {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
        }
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        /* Mobile: sidebar jadi offcanvas */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .offcanvas-backdrop::before {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                content: "";
                z-index: 999;
            }
        /* ==========================================
   RESPONSIVE — MOBILE (≤ 576px)
   ========================================== */
@media (max-width: 576px) {

    /* Sidebar offscreen */
    .sidebar {
        width: 240px;
        transform: translateX(-100%);
        position: fixed;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    /* Main content full width */
    .main-content {
        margin-left: 0 !important;
        padding: 20px !important;
    }

    /* Tombol hamburger */
    #sidebarToggle {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 2000;
    }

    /* Tabel scroll */
    .table-responsive {
        overflow-x: auto;
    }
}


/* ==========================================
   RESPONSIVE — TABLET (577px – 991px)
   ========================================== */
@media (min-width: 577px) and (max-width: 991px) {

    /* Sidebar jadi hidden awal */
    .sidebar {
        width: 260px;
        transform: translateX(-100%);
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0 !important;
        padding: 30px;
    }

    #sidebarToggle {
        display: block !important;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 2000;
    }
}


/* ==========================================
   RESPONSIVE — DESKTOP (≥ 992px)
   ========================================== */
@media (min-width: 992px) {

    /* Sidebar tetap tampil */
    .sidebar {
        transform: none !important;
        width: 260px;
    }

    .main-content {
        margin-left: 260px;
        padding: 40px;
    }

    /* Tombol hamburger tidak muncul */
    #sidebarToggle {
        display: none !important;
    }
}
        }
    </style>
</head>
<body>

    <!-- Hamburger Button (hanya muncul di mobile) -->
    <div class="d-lg-none position-fixed top-0 start-0 p-3 z-3" style="z-index:1050;">
        <button class="btn btn-primary shadow-lg rounded-circle p-3" type="button" id="sidebarToggle">
            <i class="fas fa-bars fa-lg"></i>
        </button>
    </div>

    <!-- Sidebar (fixed di desktop, offcanvas di mobile) -->
    <div class="sidebar d-flex flex-column" id="adminSidebar">
        <div class="p-4 text-center border-bottom border-light border-opacity-25">
            <h4 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Admin Panel</h4>
        </div>
        <nav class="nav flex-column flex-grow-1 px-3 py-3">
            <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link active" href="<?= APP_URL ?>/app/Admin/users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola Peserta</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola MBKM</a>
            <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
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
        <h2 class="mb-4"><i class="fas fa-users-cog me-2"></i>Kelola User</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-card">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <h4 class="mb-0">Daftar Pengguna Sistem (<?= count($users) ?> user)</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>Tambah User
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Nama Lengkap</th>
                            <th>Role</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php $no = 1; foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td>
                                        <?php
                                        $badge = match($user['role']) {
                                            'admin' => 'danger',
                                            'mahasiswa_mbkm' => 'warning',
                                            'siswa_pkl' => 'info',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $badge ?>">
                                            <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d M Y H:i', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?>', '<?= $user['role'] ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">Belum ada user</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit User (copy dari kode lama kamu) -->
    <!-- ... -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar di mobile
        document.getElementById('sidebarToggle').addEventListener('click', function () {
            document.getElementById('adminSidebar').classList.toggle('show');
        });

        // Tutup sidebar saat klik di luar (mobile)
        document.addEventListener('click', function (e) {
            const sidebar = document.getElementById('adminSidebar');
            const toggle = document.getElementById('sidebarToggle');
            if (window.innerWidth <= 992 &&
                sidebar.classList.contains('show') &&
                !sidebar.contains(e.target) &&
                !toggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });

        // Fungsi editUser & deleteUser (copy dari kode lama kamu)
        function editUser(id, username, email, full_name, role) {
            // ... kode modal edit kamu
        }
        function deleteUser(id, username) {
            if (confirm(`Hapus user "${username}"?`)) {
                // kirim form delete atau AJAX
            }
        }
    </script>
</body>
</html>