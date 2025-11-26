<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
require_once 'config/database.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================
//  ROLE CHECK (PKL + MBKM)
// ============================
$allowed_roles = ['mahasiswa_mbkm', 'siswa_pkl'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>Akses hanya untuk peserta PKL/MBKM.</h3>");
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$role_label = ($user_role === 'mahasiswa_mbkm') ? "Mahasiswa MBKM" : "Siswa PKL";

$today            = date('Y-m-d');
$current_time     = date('H:i:s');
$current_datetime = date('Y-m-d H:i:s');
$message          = '';
$message_type     = '';


// ============================
// AMBIL DATA PESERTA
// ============================
$participant = fetchOne("
    SELECT p.*, d.name AS division_name
    FROM participants p
    JOIN divisions d ON p.division_id = d.id
    WHERE p.user_id = ? AND p.status = 'aktif'
", [$user_id]);

if (!$participant) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>Data peserta aktif tidak ditemukan.</h3>");
}


// ============================
// PROSES CHECK IN / OUT
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $today_attendance = fetchOne("
        SELECT * FROM attendance 
        WHERE participant_id = ? AND [date] = ?
    ", [$participant['id'], $today]);


    // ======= CHECK IN =======
    if ($_POST['action'] === 'check_in') {

        if ($today_attendance && !empty($today_attendance['check_in'])) {
            $message = 'Anda sudah melakukan check-in hari ini!';
            $message_type = 'warning';
        } else {
            if ($today_attendance) {
                executeQuery("
                    UPDATE attendance 
                    SET check_in = ?, status = 'hadir'
                    WHERE participant_id = ? AND [date] = ?
                ", [$current_datetime, $participant['id'], $today]);
            } else {
                executeQuery("
                    INSERT INTO attendance (participant_id, [date], check_in, status)
                    VALUES (?, ?, ?, 'hadir')
                ", [$participant['id'], $today, $current_datetime]);
            }

            // Tambah 30 hari ke depan otomatis (skip weekend)
            for ($i = 1; $i <= 30; $i++) {
                $next_date = date('Y-m-d', strtotime("+$i day", strtotime($today)));
                $day = date('N', strtotime($next_date)); // 1 = Mon ... 7 = Sun

                if ($day >= 6) continue;

                $exist = fetchOne("
                    SELECT id FROM attendance 
                    WHERE participant_id = ? AND [date] = ?
                ", [$participant['id'], $next_date]);

                if (!$exist) {
                    executeQuery("
                        INSERT INTO attendance (participant_id, [date], status)
                        VALUES (?, ?, 'alpa')
                    ", [$participant['id'], $next_date]);
                }
            }

            $message = 'Check-in berhasil!';
            $message_type = 'success';
        }
    }

    // ======= CHECK OUT =======
    if ($_POST['action'] === 'check_out') {

        if (!$today_attendance || empty($today_attendance['check_in'])) {
            $message = 'Anda belum check-in!';
            $message_type = 'warning';
        } elseif (!empty($today_attendance['check_out'])) {
            $message = 'Anda sudah check-out hari ini!';
            $message_type = 'warning';
        } else {
            executeQuery("
                UPDATE attendance
                SET check_out = ?
                WHERE participant_id = ? AND [date] = ?
            ", [$current_datetime, $participant['id'], $today]);

            $message = 'Check-out berhasil!';
            $message_type = 'success';
        }
    }

    header('Location: attendance.php?msg=' . urlencode($message) . '&type=' . urlencode($message_type));
    exit();
}


// ============================
// DATA TAMPILAN
// ============================
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'info';
}

$today_attendance = fetchOne("
    SELECT * FROM attendance 
    WHERE participant_id = ? AND [date] = ?
", [$participant['id'], $today]);

$check_in_disabled  = ($today_attendance && !empty($today_attendance['check_in']));
$check_out_disabled = (!$today_attendance || empty($today_attendance['check_in']) || !empty($today_attendance['check_out']));

$riwayat_bulan_ini = fetchAll("
    SELECT 
        DATENAME(WEEKDAY, a.[date]) AS hari,
        CONVERT(VARCHAR(10), a.[date], 23) AS tanggal,
        a.check_in AS jam_masuk,
        a.check_out AS jam_pulang,
        a.status
    FROM attendance a
    WHERE a.participant_id = ?
    ORDER BY a.[date] ASC
", [$participant['id']]);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Absensi Peserta</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 0;
            min-height: 100vh;
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
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        header h1 {
            font-size: 24px;
            color: #333;
        }
        header a {
            text-decoration: none;
            color: white;
            background: #764ba2;
            padding: 8px 15px;
            border-radius: 10px;
            font-weight: 600;
        }
        .subtext {
            color: #777;
            margin-bottom: 20px;
            font-size: 15px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .message {
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            margin: 15px 0;
            text-align: center;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        th {
            background: #667eea;
            color: #fff;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .section-title {
            margin-top: 25px;
            font-size: 18px;
            color: #333;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar p-0">
            <div class="p-3 text-center">
                <h4><i class="fas fa-graduation-cap me-2"></i>Magang/PKL</h4>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                <a class="nav-link active" href="attendance.php"><i class="fas fa-calendar-check me-2"></i>Absensi Harian</a>
                <a class="nav-link" href="attendance_history.php"><i class="fas fa-history me-2"></i>Riwayat Kehadiran</a>
                <a class="nav-link" href="leave_request.php"><i class="fas fa-calendar-times me-2"></i>Ajukan Izin</a>
                <a class="nav-link" href="activity_report.php"><i class="fas fa-file-alt me-2"></i>Laporan Kegiatan</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'siswa_pkl'): ?>
                    <a class="nav-link" href="bimbingan_pkl.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Bimbingan
                    </a>
                <?php endif; ?>
                <hr class="my-3">
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 main-content">
            <div class="p-4">
                <header>
                    <h1>Absensi Peserta Magang & Pkl</h1>
                    <a href="logout.php">Logout</a>
                </header>

                <h2><?= htmlspecialchars($participant['school']) ?> - <?= htmlspecialchars($participant['major']) ?></h2>
                <div class="subtext">(<?= htmlspecialchars($participant['division_name']) ?>)</div>

                <?php if ($message): ?>
                    <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form method="POST" action="" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                    <button type="submit" name="action" value="check_in" class="btn"
                        <?= $check_in_disabled ? 'disabled' : '' ?> aria-label="Check In">✅ Check In</button>
                    <button type="submit" name="action" value="check_out" class="btn"
                        <?= $check_out_disabled ? 'disabled' : '' ?> aria-label="Check Out">🚪 Check Out</button>
                    <div style="align-self:center;color:#666;font-size:14px">
                        Waktu server: <?= htmlspecialchars($current_time) ?>
                    </div>
                </form>

                <div class="section-title">📅 Ringkasan Hari Ini</div>
                <table>
                    <tr>
                        <th>Tanggal</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                    </tr>
                    <tr>
                        <td><?= htmlspecialchars($today) ?></td>
                        <td><?= !empty($today_attendance['check_in']) ? date('H:i:s', strtotime($today_attendance['check_in'])) : '-' ?></td>
                        <td><?= !empty($today_attendance['check_out']) ? date('H:i:s', strtotime($today_attendance['check_out'])) : '-' ?></td>
                        <td><?= htmlspecialchars($today_attendance['status'] ?? '-') ?></td>
                    </tr>
                </table>

                <!-- 🔹 Tombol toggle untuk rekap -->
                <div class="section-title">📘 Rekap Absensi</div>
                <button id="toggleRekap" class="btn" style="margin-bottom:10px;">Lihat Rekap Absensi ⬇️</button>

                <!-- 🔹 Table rekap absensi disembunyikan dulu -->
                <div id="rekapContainer" style="display:none;">
                    <table>
                        <tr>
                            <th>Hari</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Status</th>
                        </tr>
                        <?php if (!empty($riwayat_bulan_ini)): ?>
                            <?php foreach ($riwayat_bulan_ini as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['hari']) ?></td>
                                    <td><?= htmlspecialchars($row['tanggal']) ?></td>
                                    <td><?= !empty($row['jam_masuk']) ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?></td>
                                    <td><?= !empty($row['jam_pulang']) ? date('H:i', strtotime($row['jam_pulang'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($row['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">Belum ada data absensi bulan ini.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>

            </div> <!-- Tutup inner container -->
        </div> <!-- Tutup main column -->
    </div> <!-- Tutup row -->
</div> <!-- Tutup container-fluid -->

<script>
document.getElementById('toggleRekap').addEventListener('click', function() {
    const rekap = document.getElementById('rekapContainer');
    const btn   = document.getElementById('toggleRekap');
    if (rekap.style.display === 'none') {
        rekap.style.display = 'block';
        btn.textContent     = 'Sembunyikan Rekap Absensi ⬆️';
    } else {
        rekap.style.display = 'none';
        btn.textContent     = 'Lihat Rekap Absensi ⬇️';
    }
});
</script>
</body>
</html>
