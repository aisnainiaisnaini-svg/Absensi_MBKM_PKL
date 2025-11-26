<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Ambil semua data peserta
$participants = fetchAll("SELECT p.*, u.full_name, u.email, d.name AS division_name, u.role 
                          FROM participants p
                          JOIN users u ON p.user_id = u.id
                          JOIN divisions d ON p.division_id = d.id
                          ORDER BY u.full_name");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Peserta - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
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
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar p-0">
            <div class="p-3 text-center">
                <h4><i class="fas fa-graduation-cap me-2"></i>Admin Panel</h4>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link" href="../dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                <a class="nav-link active" href="participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola Peserta</a>
                <a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i>Kelola User</a>
                <a class="nav-link" href="divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
                <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>
                <hr class="my-3">
                <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 p-4">
            <h2 class="mb-4"><i class="fas fa-user-graduate me-2"></i>Data Peserta Magang</h2>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Divisi</th>
                                <th>Status</th>
                                <th>Periode</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($participants)): ?>
                                <?php $no = 1; foreach ($participants as $p): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($p['full_name']) ?></td>
                                        <td><?= htmlspecialchars($p['email']) ?></td>
                                        <td><?= htmlspecialchars($p['division_name']) ?></td>
                                        <td><span class="badge bg-success"><?= ucfirst($p['status']) ?></span></td>
                                        <td><?= date('d M Y', strtotime($p['start_date'])) ?> - <?= date('d M Y', strtotime($p['end_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center text-muted">Tidak ada data peserta</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
