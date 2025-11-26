<?php
session_start();
require_once '../config/database.php';

// Hanya untuk admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// ===============================
// Ambil parameter
// ===============================
$user_id = $_GET['user_id'] ?? null;
$role = $_GET['role'] ?? null;

if (!$user_id || !$role) {
    die("Parameter tidak valid!");
}

// ===============================
// Ambil data peserta berdasar user_id
// ===============================
$participant = fetchOne("
    SELECT 
        p.*, 
        u.full_name, 
        u.email,
        u.username,
        u.role AS user_role,
        d.name AS division_name
    FROM participants p
    JOIN users u ON p.user_id = u.id
    JOIN divisions d ON d.id = p.division_id
    WHERE p.user_id = ?
", [$user_id]);

if (!$participant) {
    die("Peserta tidak ditemukan!");
}

// ===============================
// Ambil data semua divisi
// ===============================
$divisions = fetchAll("SELECT id, name FROM divisions ORDER BY name ASC");

// ===============================
// PROSES UPDATE
// ===============================
$message = '';
$message_type = '';

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update') {
    
    $full_name  = $_POST['full_name'];
    $email      = $_POST['email'];
    $school     = $_POST['school'];
    $major      = $_POST['major'];
    $division_id = $_POST['division_id'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $status     = $_POST['status'];

    // PKL only
    $company_supervisor = $_POST['company_supervisor'] ?? null;
    $school_supervisor  = $_POST['school_supervisor'] ?? null;

    // Update ke tabel users
    executeQuery("
        UPDATE users 
        SET full_name = ?, email = ?
        WHERE id = ?
    ", [$full_name, $email, $user_id]);

    // Update ke tabel participants
    executeQuery("
        UPDATE participants
        SET school = ?, major = ?, division_id = ?, start_date = ?, end_date = ?, status = ?,
            company_supervisor = ?, school_supervisor = ?
        WHERE user_id = ?
    ", [
        $school, $major, $division_id, $start_date, $end_date, $status,
        $company_supervisor, $school_supervisor, $user_id
    ]);

    $message = "Data peserta berhasil diperbarui!";
    $message_type = "success";

    // Refresh data
    $participant = fetchOne("
        SELECT 
            p.*, 
            u.full_name, 
            u.email,
            u.username,
            u.role AS user_role,
            d.name AS division_name
        FROM participants p
        JOIN users u ON p.user_id = u.id
        JOIN divisions d ON d.id = p.division_id
        WHERE p.user_id = ?
    ", [$user_id]);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Peserta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f8f9fa">

<div class="container py-4">
    <a href="participants_<?php echo $role === 'mahasiswa_mbkm' ? 'mbkm' : 'pkl'; ?>.php" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>

    <div class="card shadow-sm">
        <div class="card-header">
            <h4 class="mb-0">
                <i class="fas fa-edit me-2"></i>
                Edit Peserta - <?= ucfirst(str_replace("_", " ", $role)) ?>
            </h4>
        </div>
        <div class="card-body">

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="update">

                <div class="row">

                    <!-- Nama & Email -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($participant['full_name']) ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($participant['email']) ?>" required>
                    </div>

                    <!-- Sekolah -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sekolah / Universitas</label>
                        <input type="text" class="form-control" name="school" value="<?= htmlspecialchars($participant['school']) ?>" required>
                    </div>

                    <!-- Jurusan -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jurusan</label>
                        <input type="text" class="form-control" name="major" value="<?= htmlspecialchars($participant['major']) ?>" required>
                    </div>

                    <!-- Divisi -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Divisi</label>
                        <select name="division_id" class="form-control" required>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= $d['id'] ?>" 
                                    <?= $participant['division_id'] == $d['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Periode -->
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Mulai</label>
                        <input type="date" class="form-control" name="start_date" value="<?= $participant['start_date'] ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Selesai</label>
                        <input type="date" class="form-control" name="end_date" value="<?= $participant['end_date'] ?>" required>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="aktif"        <?= $participant['status']=='aktif'?'selected':'' ?>>Aktif</option>
                            <option value="selesai"      <?= $participant['status']=='selesai'?'selected':'' ?>>Selesai</option>
                            <option value="dikeluarkan"  <?= $participant['status']=='dikeluarkan'?'selected':'' ?>>Dikeluarkan</option>
                        </select>
                    </div>

                    <!-- Kolom khusus PKL -->
                    <?php if ($role === 'siswa_pkl'): ?>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pembimbing Perusahaan</label>
                            <input type="text" class="form-control"
                                   name="company_supervisor"
                                   value="<?= htmlspecialchars($participant['company_supervisor'] ?? '') ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pembimbing Sekolah</label>
                            <input type="text" class="form-control"
                                   name="school_supervisor"
                                   value="<?= htmlspecialchars($participant['school_supervisor'] ?? '') ?>">
                        </div>

                    <?php endif; ?>

                </div>

                <button class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Simpan Perubahan
                </button>

            </form>

        </div>
    </div>

</div>
</body>
</html>
