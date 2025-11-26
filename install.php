<?php
// File instalasi untuk Aplikasi Pengawasan Magang/PKL
// Jalankan file ini sekali untuk setup database dan data awal

require_once 'config/database.php';

$step = $_GET['step'] ?? 1;
$message = '';
$message_type = '';

// Step 1: Cek koneksi database
if ($step == 1) {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $message = 'Koneksi database berhasil!';
        $message_type = 'success';
    } catch(PDOException $e) {
        $message = 'Koneksi database gagal: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Step 2: Buat database dan tabel
if ($step == 2) {
    try {
        // Baca dan eksekusi schema SQL
        $sql = file_get_contents('database/schema.sql');
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                executeQuery($statement);
            }
        }
        
        $message = 'Database dan tabel berhasil dibuat!';
        $message_type = 'success';
    } catch(Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Step 3: Buat folder uploads
if ($step == 3) {
    try {
        if (!is_dir('uploads/reports')) {
            mkdir('uploads/reports', 0777, true);
        }
        
        $message = 'Folder uploads berhasil dibuat!';
        $message_type = 'success';
    } catch(Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi - Aplikasi Pengawasan Magang/PKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .install-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .step-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: 10px 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }
        .step-card:hover {
            transform: translateY(-2px);
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        .btn-install {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        .btn-install:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card install-card">
                    <div class="install-header">
                        <i class="fas fa-cogs fa-3x mb-3"></i>
                        <h3>Instalasi Aplikasi</h3>
                        <p class="mb-0">Pengawasan Magang & PKL</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Step 1: Cek Koneksi Database -->
                        <div class="step-card">
                            <div class="d-flex align-items-center">
                                <div class="step-number">1</div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">Cek Koneksi Database</h5>
                                    <p class="text-muted mb-0">Memverifikasi koneksi ke database MySQL</p>
                                </div>
                                <div>
                                    <?php if ($step >= 1): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Selesai
                                        </span>
                                    <?php else: ?>
                                        <a href="?step=1" class="btn btn-primary btn-sm">Cek</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Buat Database dan Tabel -->
                        <div class="step-card">
                            <div class="d-flex align-items-center">
                                <div class="step-number">2</div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">Setup Database</h5>
                                    <p class="text-muted mb-0">Membuat database dan tabel yang diperlukan</p>
                                </div>
                                <div>
                                    <?php if ($step >= 2): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Selesai
                                        </span>
                                    <?php elseif ($step == 1): ?>
                                        <a href="?step=2" class="btn btn-primary btn-sm">Setup</a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Menunggu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Buat Folder Uploads -->
                        <div class="step-card">
                            <div class="d-flex align-items-center">
                                <div class="step-number">3</div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">Setup Folder Uploads</h5>
                                    <p class="text-muted mb-0">Membuat folder untuk menyimpan file upload</p>
                                </div>
                                <div>
                                    <?php if ($step >= 3): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Selesai
                                        </span>
                                    <?php elseif ($step == 2): ?>
                                        <a href="?step=3" class="btn btn-primary btn-sm">Setup</a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Menunggu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 4: Selesai -->
                        <?php if ($step >= 3): ?>
                            <div class="step-card">
                                <div class="d-flex align-items-center">
                                    <div class="step-number">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1 text-success">Instalasi Selesai!</h5>
                                        <p class="text-muted mb-0">Aplikasi siap digunakan</p>
                                    </div>
                                    <div>
                                        <a href="index.php" class="btn btn-success btn-install">
                                            <i class="fas fa-rocket me-2"></i>Mulai Aplikasi
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <h6><i class="fas fa-info-circle me-2"></i>Informasi Login:</h6>
                                <ul class="mb-0">
                                    <li><strong>Admin:</strong> admin / password</li>
                                    <li><strong>Pembimbing:</strong> pembimbing1 / password</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Informasi Sistem -->
                        <div class="mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Informasi Sistem:</h6>
                            <ul class="list-unstyled text-muted">
                                <li><i class="fas fa-server me-2"></i>PHP Version: <?= PHP_VERSION ?></li>
                                <li><i class="fas fa-database me-2"></i>Database: MySQL</li>
                                <li><i class="fas fa-folder me-2"></i>Uploads: <?= is_dir('uploads') ? 'Ready' : 'Not Ready' ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>