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

// =====================================
// PROSES UPDATE BIMBINGAN
// =====================================
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_guidance') {
    $guidance_id = $_POST['guidance_id'] ?? null;
    $participant_id = $_POST['participant_id'] ?? null;
    $status = $_POST['status'] ?? 'pending';
    $admin_response = trim($_POST['admin_response'] ?? '');
    $company_supervisor = trim($_POST['company_supervisor'] ?? '');
    $school_supervisor = trim($_POST['school_supervisor'] ?? '');
    $preferred_day = trim($_POST['preferred_day'] ?? '');

    if ($guidance_id && $participant_id) {
        // Update tabel Guidance_PKL (termasuk Preferred_Day jika diset oleh admin)
        executeQuery(
            "UPDATE Guidance_PKL
            SET Preferred_Day = COALESCE(NULLIF(?,''), Preferred_Day),
                Status = ?,
                Admin_Response = ?,
                Responded_At = CASE
                    WHEN ? <> '' THEN SYSDATETIME()
                    ELSE Responded_At
                END
            WHERE Id = ?",
            [$preferred_day, $status, $admin_response, $admin_response, $guidance_id]
        );

        // Opsional: update pembimbing di tabel Participants
        executeQuery(
            "
            UPDATE Participants
            SET Company_Supervisor = ?,
                School_Supervisor  = ?
            WHERE Id = ?
        ",
            [$company_supervisor ?: null, $school_supervisor ?: null, $participant_id],
        );

        // Redirect agar tidak resubmit POST
        header('Location: ' . APP_URL . '/app/Guidance/admin_bimbingan_pkl.php?updated=1');
        exit();
    } else {
        $message = 'Data bimbingan tidak valid.';
        $message_type = 'danger';
    }
}

// =====================================
// FILTER & PENCARIAN
// =====================================
$keyword = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';

$params = [];
$where = "WHERE u.role = 'siswa_pkl'";

if (!empty($keyword)) {
    $where .= ' AND u.full_name LIKE ?';
    $params[] = "%{$keyword}%";
}

if (!empty($filter_status)) {
    $where .= ' AND g.Status = ?';
    $params[] = $filter_status;
}

// =====================================
// AMBIL DATA BIMBINGAN
// =====================================
$guidances = fetchAll(
    "
    SELECT
        g.Id              AS guidance_id,
        g.Title           AS title,
        g.Question_Text   AS question_text,
        g.Admin_Response  AS admin_response,
        g.Status          AS status,
        g.Created_At      AS created_at,
        g.Responded_At    AS responded_at,

        p.Id              AS participant_id,
        p.School          AS school,
        p.Major           AS major,
        g.Preferred_Day   AS preferred_day,
        p.Company_Supervisor AS company_supervisor,
        p.School_Supervisor  AS school_supervisor,

        u.Full_Name       AS full_name,
        d.Name            AS division_name
    FROM Guidance_PKL g
    JOIN Participants p ON g.Participant_Id = p.Id
    JOIN Users u        ON p.User_Id       = u.Id
    JOIN Divisions d    ON p.Division_Id   = d.Id
    $where
    ORDER BY g.Created_At DESC
",
    $params,
);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Bimbingan PKL - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }

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
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }

        .table-card {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .badge-status {
            text-transform: capitalize;
        }

        .truncate-text {
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- SIDEBAR -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3 text-center">
                    <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="<?= APP_URL ?>/public/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/users.php"><i class="fas fa-users-cog me-2"></i>Kelola User</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants.php"><i class="fas fa-user-graduate me-2"></i>Kelola Peserta</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_mbkm.php"><i class="fas fa-user me-2"></i>Kelola MBKM</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Participants/admin_participants_pkl.php"><i class="fas fa-user me-2"></i>Kelola PKL</a>
                    <a class="nav-link active" href="<?= APP_URL ?>/app/Guidance/admin_bimbingan_pkl.php"><i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan PKL</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Leave/leave_approval.php"><i class="fas fa-check-circle me-2"></i>Persetujuan Izin</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/reports_review.php"><i class="fas fa-clipboard-check me-2"></i>Review Laporan</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Admin/divisions.php"><i class="fas fa-building me-2"></i>Kelola Divisi</a>
                    <a class="nav-link" href="<?= APP_URL ?>/app/Reports/admin_reports.php"><i class="fas fa-chart-bar me-2"></i>Laporan Sistem</a>
                    <hr class="my-3">
                    <a class="nav-link" href="<?= APP_URL ?>/public/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>

            <!-- MAIN CONTENT -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-0">
                                <i class="fas fa-comments me-2"></i>Bimbingan PKL (Admin)
                            </h2>
                            <p class="text-muted mb-0">
                                Kelola permintaan bimbingan dari siswa PKL dan tetapkan pembimbing.
                            </p>
                        </div>
                    </div>

                    <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Data bimbingan berhasil diperbarui.
                    </div>
                    <?php elseif ($message): ?>
                    <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Siswa</th>
                                        <th>Sekolah</th>
                                        <th>Divisi</th>
                                        <th>Judul Bimbingan</th>
                                        <th>Waktu</th>
                                        <th>Status</th>
                                        <th>Pembimbing</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($guidances)): ?>
                                    <?php $no = 1; foreach ($guidances as $g): ?>
                                    <?php
                                    $badge_class = 'secondary';
                                    if ($g['status'] === 'pending') {
                                        $badge_class = 'warning';
                                    }
                                    if ($g['status'] === 'diproses') {
                                        $badge_class = 'info';
                                    }
                                    if ($g['status'] === 'selesai') {
                                        $badge_class = 'success';
                                    }
                                    if ($g['status'] === 'withdrawn') {
                                        $badge_class = 'dark';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($g['full_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($g['major']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($g['school']) ?></td>
                                        <td><?= htmlspecialchars($g['division_name']) ?></td>
                                        <td>
                                            <div class="truncate-text" title="<?= htmlspecialchars($g['title']) ?>">
                                                <?= htmlspecialchars($g['title']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($g['preferred_day'])): ?>
                                                <?= htmlspecialchars($g['preferred_day']) ?>
                                            <?php else: ?>
                                                <?= date('d M Y H:i', strtotime($g['created_at'])) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $badge_class ?> badge-status">
                                                <?= htmlspecialchars($g['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <strong>PT:</strong>
                                                <?= htmlspecialchars($g['company_supervisor'] ?? '-') ?><br>
                                                <strong>Sekolah:</strong>
                                                <?= htmlspecialchars($g['school_supervisor'] ?? '-') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#manageGuidance<?= $g['guidance_id'] ?>">
                                                <i class="fas fa-edit me-1"></i>Kelola
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- MODAL KELOLA BIMBINGAN -->
                                    <div class="modal fade" id="manageGuidance<?= $g['guidance_id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="update_guidance">
                                                    <input type="hidden" name="guidance_id"
                                                        value="<?= $g['guidance_id'] ?>">
                                                    <input type="hidden" name="participant_id"
                                                        value="<?= $g['participant_id'] ?>">

                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-comments me-2"></i>
                                                            Kelola Bimbingan - <?= htmlspecialchars($g['full_name']) ?>
                                                        </h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>

                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Judul / Topik</label>
                                                            <input type="text" class="form-control"
                                                                value="<?= htmlspecialchars($g['title']) ?>" disabled>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Pertanyaan / Bahan
                                                                Bimbingan</label>
                                                            <textarea class="form-control" rows="4" disabled><?= htmlspecialchars($g['question_text']) ?></textarea>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Waktu yang diajukan Siswa</label>
                                                            <select name="preferred_day" class="form-select">
                                                                <option value="" <?= empty($g['preferred_day']) ? 'selected' : '' ?>>-- (biarkan) --</option>
                                                                <option value="Senin, 09:00" <?= ($g['preferred_day'] ?? '') === 'Senin, 09:00' ? 'selected' : '' ?>>Senin, 09:00</option>
                                                                <option value="Senin, 14:00" <?= ($g['preferred_day'] ?? '') === 'Senin, 14:00' ? 'selected' : '' ?>>Senin, 14:00</option>
                                                                <option value="Selasa, 10:00" <?= ($g['preferred_day'] ?? '') === 'Selasa, 10:00' ? 'selected' : '' ?>>Selasa, 10:00</option>
                                                                <option value="Selasa, 15:00" <?= ($g['preferred_day'] ?? '') === 'Selasa, 15:00' ? 'selected' : '' ?>>Selasa, 15:00</option>
                                                                <option value="Rabu, 09:00" <?= ($g['preferred_day'] ?? '') === 'Rabu, 09:00' ? 'selected' : '' ?>>Rabu, 09:00</option>
                                                                <option value="Rabu, 13:00" <?= ($g['preferred_day'] ?? '') === 'Rabu, 13:00' ? 'selected' : '' ?>>Rabu, 13:00</option>
                                                                <option value="Kamis, 10:00" <?= ($g['preferred_day'] ?? '') === 'Kamis, 10:00' ? 'selected' : '' ?>>Kamis, 10:00</option>
                                                                <option value="Kamis, 14:00" <?= ($g['preferred_day'] ?? '') === 'Kamis, 14:00' ? 'selected' : '' ?>>Kamis, 14:00</option>
                                                                <option value="Jumat, 09:00" <?= ($g['preferred_day'] ?? '') === 'Jumat, 09:00' ? 'selected' : '' ?>>Jumat, 09:00</option>
                                                                <option value="Jumat, 13:00" <?= ($g['preferred_day'] ?? '') === 'Jumat, 13:00' ? 'selected' : '' ?>>Jumat, 13:00</option>
                                                            </select>
                                                            <div class="small text-muted mt-1">Biarkan kosong untuk mempertahankan waktu saat ini.</div>
                                                        </div>

                                                        <hr>

                                                        <div class="row mb-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Status Bimbingan</label>
                                                                <select name="status" class="form-select" required>
                                                                    <option value="pending"
                                                                        <?= $g['status'] === 'pending' ? 'selected' : '' ?>>
                                                                        Pending</option>
                                                                    <option value="diproses"
                                                                        <?= $g['status'] === 'diproses' ? 'selected' : '' ?>>
                                                                        Diproses</option>
                                                                    <option value="selesai"
                                                                        <?= $g['status'] === 'selesai' ? 'selected' : '' ?>>
                                                                        Selesai</option>
                                                                    <option value="withdrawn"
                                                                        <?= $g['status'] === 'withdrawn' ? 'selected' : '' ?>>
                                                                        Withdrawn</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label">Jawaban / Catatan
                                                                    Admin</label>
                                                                <textarea name="admin_response" class="form-control" rows="3"
                                                                    placeholder="Tuliskan respon bimbingan di sini..."><?= htmlspecialchars($g['admin_response'] ?? '') ?></textarea>
                                                                <?php if (!empty($g['responded_at'])): ?>
                                                                <div class="small text-muted mt-1">
                                                                    <i class="fas fa-clock me-1"></i>
                                                                    Terakhir dibalas:
                                                                    <?= date('d M Y H:i', strtotime($g['responded_at'])) ?>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>

                                                        <hr>

                                                        <h6 class="mb-2">Penetapan Pembimbing</h6>
                                                        <div class="row mb-2">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Pembimbing di
                                                                    Perusahaan</label>
                                                                <input type="text" name="company_supervisor"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($g['company_supervisor'] ?? '') ?>"
                                                                    placeholder="Nama pembimbing di perusahaan">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Pembimbing di Sekolah</label>
                                                                <input type="text" name="school_supervisor"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($g['school_supervisor'] ?? '') ?>"
                                                                    placeholder="Nama pembimbing di sekolah">
                                                            </div>
                                                        </div>

                                                        <div class="alert alert-info small mt-3">
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Perubahan pembimbing akan tersimpan di data peserta PKL
                                                            terkait.
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">
                                                            Batal
                                                        </button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-save me-1"></i>Simpan Perubahan
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- END MODAL -->
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">
                                            Belum ada data bimbingan PKL.
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>
