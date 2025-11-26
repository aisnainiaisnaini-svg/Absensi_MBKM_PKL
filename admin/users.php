<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$message = '';
$message_type = '';

// Proses tambah user
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $email && $full_name && $role && $password) {
        // Validasi role
        if (!in_array($role, ['admin', 'mahasiswa_mbkm', 'siswa_pkl'], true)) {
            $message = 'Role tidak valid. Pilih admin / mahasiswa_mbkm / siswa_pkl.';
            $message_type = 'danger';
        } else {
            // Cek apakah username atau email sudah ada
            $existing_user = fetchOne('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email]);

            if ($existing_user) {
                $message = 'Username atau email sudah digunakan! Silakan gunakan username/email lain.';
                $message_type = 'danger';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                executeQuery(
                    "INSERT INTO users (username, email, full_name, role, password) 
                 VALUES (?, ?, ?, ?, ?)",
                    [$username, $email, $full_name, $role, $hashed_password],
                );

                $message = 'User ' . htmlspecialchars($username) . ' berhasil ditambahkan!';
                $message_type = 'success';
            }
        }
    } else {
        $message = 'Semua field harus diisi! Pastikan username, email, nama lengkap, role, dan password sudah diisi.';
        $message_type = 'danger';
    }
}

// Proses edit user
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $user_id = $_POST['user_id'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($user_id && $username && $email && $full_name && $role) {
        if (!in_array($role, ['admin', 'mahasiswa_mbkm', 'siswa_pkl'], true)) {
            $message = 'Role tidak valid. Pilih admin / mahasiswa_mbkm / siswa_pkl.';
            $message_type = 'danger';
        } else {
            // Cek apakah username atau email sudah ada (kecuali user yang sedang diedit)
            $existing_user = fetchOne('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?', [$username, $email, $user_id]);

            if ($existing_user) {
                $message = 'Username atau email sudah digunakan! Silakan gunakan username/email lain.';
                $message_type = 'danger';
            } else {
                // Cek apakah user sedang mengedit dirinya sendiri dan mengubah role
                if ($user_id == $_SESSION['user_id']) {
                    $current_user = fetchOne('SELECT role FROM users WHERE id = ?', [$user_id]);
                    if ($current_user['role'] !== $role) {
                        $message = 'Tidak dapat mengubah role sendiri! Untuk keamanan, admin tidak dapat mengubah role sendiri.';
                        $message_type = 'danger';
                    } else {
                        // Update tanpa mengubah role
                        if ($password) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            executeQuery(
                                "UPDATE users SET username = ?, email = ?, full_name = ?, password = ? 
                                     WHERE id = ?",
                                [$username, $email, $full_name, $hashed_password, $user_id],
                            );
                        } else {
                            executeQuery(
                                "UPDATE users SET username = ?, email = ?, full_name = ? 
                                     WHERE id = ?",
                                [$username, $email, $full_name, $user_id],
                            );
                        }
                        $message = 'User ' . htmlspecialchars($username) . ' berhasil diupdate!';
                        $message_type = 'success';
                    }
                } else {
                    // Update user lain
                    if ($password) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        executeQuery(
                            "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, password = ? 
                                 WHERE id = ?",
                            [$username, $email, $full_name, $role, $hashed_password, $user_id],
                        );
                    } else {
                        executeQuery(
                            "UPDATE users SET username = ?, email = ?, full_name = ?, role = ? 
                                 WHERE id = ?",
                            [$username, $email, $full_name, $role, $user_id],
                        );
                    }
                    $message = 'User ' . htmlspecialchars($username) . ' berhasil diupdate!';
                    $message_type = 'success';
                }
            }
        }
    } else {
        $message = 'Semua field wajib harus diisi! Pastikan username, email, nama lengkap, dan role sudah diisi.';
        $message_type = 'danger';
    }
}

// Proses hapus user
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = $_POST['user_id'] ?? '';

    if ($user_id && $user_id != $_SESSION['user_id']) {
        executeQuery('DELETE FROM users WHERE id = ?', [$user_id]);
        $deleted_user = fetchOne('SELECT username FROM users WHERE id = ?', [$user_id]);
        $message = 'User ' . htmlspecialchars($deleted_user['username']) . ' berhasil dihapus!';
        $message_type = 'success';
    } else {
        $message = 'Tidak dapat menghapus user sendiri! Untuk keamanan, admin tidak dapat menghapus akun sendiri.';
        $message_type = 'danger';
    }
}

// Ambil semua users
$users = fetchAll('SELECT * FROM users ORDER BY created_at DESC');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin Panel</title>
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

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
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

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }

        .form-control:focus,
        .form-select:focus {
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

        .table-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .role-badge {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 15px;
        }

        .btn-group .btn {
            margin-right: 2px;
        }

        .btn-group .btn:last-child {
            margin-right: 0;
        }

        .form-control:disabled,
        .form-select:disabled {
            background-color: #f8f9fa;
            opacity: 0.6;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-2px);
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
                        <i class="fas fa-cogs me-2"></i>
                        Admin Panel
                    </h4>
                </div>

                <nav class="nav flex-column">
                    <a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link active" href="users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
                    <a class="nav-link" href="participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola
                        Peserta</a>
                    <a class="nav-link" href="participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola MBKM</a>
                    <a class="nav-link" href="participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
                    <a class="nav-link" href="bimbingan_pkl.php"><i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan
                        PKL</a>
                    <a class="nav-link" href="../leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan
                        Izin</a>
                    <a class="nav-link" href="../reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review
                        Laporan</a>
                    <a class="nav-link" href="divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
                    <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>
                    <hr class="my-3">
                    <a class="nav-link" href="../logout.php">
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
                            <h2><i class="fas fa-users-cog me-2"></i>Kelola User</h2>
                            <p class="text-muted mb-0">Manajemen akun pengguna sistem (<?= count($users) ?> user)</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus me-2"></i>Tambah User
                        </button>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                        <i
                            class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="text-primary mb-2">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                    <h5 class="card-title"><?= count($users) ?></h5>
                                    <p class="card-text text-muted">Total User</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="text-danger mb-2">
                                        <i class="fas fa-user-shield fa-2x"></i>
                                    </div>
                                    <h5 class="card-title"><?= count(
                                        array_filter($users, function ($u) {
                                            return $u['role'] === 'admin';
                                        }),
                                    ) ?></h5>
                                    <p class="card-text text-muted">Admin</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="text-warning mb-2">
                                        <i class="fas fa-user-tie fa-2x"></i>
                                    </div>
                                    <h5 class="card-title">
                                        <?= count(
                                            array_filter($users, function ($u) {
                                                return $u['role'] === 'mahasiswa_mbkm';
                                            }),
                                        ) ?>
                                    </h5>
                                    <p class="card-text text-muted">Mahasiswa MBKM</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <div class="text-info mb-2">
                                        <i class="fas fa-user-graduate fa-2x"></i>
                                    </div>
                                    <h5 class="card-title">
                                        <?= count(
                                            array_filter($users, function ($u) {
                                                return $u['role'] === 'siswa_pkl';
                                            }),
                                        ) ?>
                                    </h5>
                                    <p class="card-text text-muted">Siswa PKL</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="table-card">
                        <h5 class="mb-3">
                            <i class="fas fa-list me-2"></i>
                            Daftar User
                        </h5>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Nama Lengkap</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                        <td>
                                            <?php
                                            $role_class = '';
                                            switch ($user['role']) {
                                                case 'admin':
                                                    $role_class = 'danger';
                                                    break;
                                                case 'mahasiswa_mbkm':
                                                    $role_class = 'warning';
                                                    break;
                                                case 'siswa_pkl':
                                                    $role_class = 'info';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?= $role_class ?> role-badge">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>
                                                Aktif
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= date('d M Y', strtotime($user['created_at'])) ?></strong>
                                            </div>
                                            <small class="text-muted">
                                                <?= date('H:i', strtotime($user['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Edit User"
                                                    onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['full_name']) ?>', '<?= $user['role'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Hapus User"
                                                    onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-users fa-2x mb-2"></i><br>
                                            Belum ada user
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Tambah User Baru
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Pilih role</option>
                                <option value="admin">Admin</option>
                                <option value="mahasiswa_mbkm">Mahasiswa MBKM</option>
                                <option value="siswa_pkl">Siswa PKL</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Tambah User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">

                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="">Pilih role</option>
                                <option value="admin">Admin</option>
                                <option value="mahasiswa_mbkm">Mahasiswa MBKM</option>
                                <option value="siswa_pkl">Siswa PKL</option>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Tidak dapat mengubah role sendiri untuk keamanan
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Password Baru (Kosongkan jika tidak ingin
                                mengubah)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="editSpinner"></span>
                            <i class="fas fa-save me-2"></i>
                            <span id="editBtnText">Update User</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
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
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Apakah Anda yakin ingin menghapus user <strong id="delete_username"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan dan akan menghapus semua
                            data terkait user tersebut, termasuk:
                            <ul class="mb-0 mt-2">
                                <li>Data peserta (jika ada)</li>
                                <li>Riwayat kehadiran</li>
                                <li>Pengajuan izin</li>
                                <li>Laporan kegiatan</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Apakah Anda benar-benar yakin ingin menghapus user ini? Tindakan ini tidak dapat dibatalkan!')">
                            <i class="fas fa-trash me-2"></i>Hapus User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        function editUser(userId, username, email, fullName, role) {
            console.log('Edit user clicked:', {
                userId,
                username,
                email,
                fullName,
                role
            });

            // Konfirmasi sebelum edit
            if (!confirm('Apakah Anda yakin ingin mengedit user "' + username +
                    '"?\n\nData yang akan diubah:\n- Username: ' + username + '\n- Email: ' + email + '\n- Nama: ' +
                    fullName + '\n- Role: ' + role)) {
                return;
            }

            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = '';

            // Disable role field jika user sedang mengedit dirinya sendiri
            var currentUserId = <?= $_SESSION['user_id'] ?>;
            var roleField = document.getElementById('edit_role');
            if (userId == currentUserId) {
                roleField.disabled = true;
                roleField.title = 'Tidak dapat mengubah role sendiri';
            } else {
                roleField.disabled = false;
                roleField.title = '';
            }

            console.log('Opening edit modal');
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function deleteUser(userId, username) {
            // Konfirmasi sebelum hapus
            if (!confirm('Apakah Anda yakin ingin menghapus user "' + username +
                    '"?\n\nTindakan ini tidak dapat dibatalkan dan akan menghapus semua data terkait user tersebut.')) {
                return;
            }

            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }

        function showLoadingState() {
            document.getElementById('editSpinner').classList.remove('d-none');
            document.getElementById('editBtnText').textContent = 'Updating...';
            document.getElementById('editSubmitBtn').disabled = true;
        }

        function resetButtonState() {
            document.getElementById('editSpinner').classList.add('d-none');
            document.getElementById('editBtnText').textContent = 'Update User';
            document.getElementById('editSubmitBtn').disabled = false;
        }

        // Handle form submission
        document.querySelector('#editUserModal form').addEventListener('submit', function(e) {
            console.log('Form submit event triggered');

            var username = document.getElementById('edit_username').value;
            var email = document.getElementById('edit_email').value;
            var fullName = document.getElementById('edit_full_name').value;
            var role = document.getElementById('edit_role').value;

            console.log('Form values:', {
                username,
                email,
                fullName,
                role
            });

            // Validate form
            if (!username || !email || !fullName || !role) {
                e.preventDefault();
                alert(
                    'Semua field wajib harus diisi!\n\nPastikan:\n- Username sudah diisi\n- Email sudah diisi\n- Nama lengkap sudah diisi\n- Role sudah dipilih'
                );
                return false;
            }

            console.log('Form validation passed, showing loading state');

            // Show loading state
            showLoadingState();

            console.log('Form will submit now');

            // Allow form to submit
            return true;
        });

        // Reset button state when modal is hidden
        document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function() {
            resetButtonState();
        });

        // Reset button state when modal is shown
        document.getElementById('editUserModal').addEventListener('show.bs.modal', function() {
            resetButtonState();
        });

        // Reset button state on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up event listeners');
            resetButtonState();

            // Debug: Check if form exists
            var form = document.querySelector('#editUserModal form');
            if (form) {
                console.log('Edit form found:', form);
            } else {
                console.error('Edit form not found!');
            }
        });
    </script>
</body>

</html>
