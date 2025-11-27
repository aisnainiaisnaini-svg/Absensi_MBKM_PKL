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
// PROSES CHECK IN / OUT (dengan validasi lokasi berbasis radius)
// ============================
// Koordinat pusat PT Krakatau Tirta Industri (sesuaikan dengan titik sebenarnya)
$KTI_LAT =-6.014745; // contoh latitude - ganti dengan nilai sebenarnya
$KTI_LNG = 106.022208; // contoh longitude - ganti dengan nilai sebenarnya
$KTI_RADIUS_M = 700; // radius dalam meter (sesuaikan)

function haversine_distance_m($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // meter
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Periksa apakah client mengirimkan koordinat (wajib)
    if (!isset($_POST['latitude']) || !isset($_POST['longitude'])) {
        $message = 'Aktifkan GPS/Location pada perangkat Anda lalu coba lagi.';
        $message_type = 'warning';
        header('Location: attendance.php?msg=' . urlencode($message) . '&type=' . urlencode($message_type));
        exit();
    }

    $lat = floatval($_POST['latitude']);
    $lng = floatval($_POST['longitude']);
    $distance_m = haversine_distance_m($lat, $lng, $KTI_LAT, $KTI_LNG);

    if ($distance_m > $KTI_RADIUS_M) {
        $message = 'Anda berada di luar KTI (jarak ' . round($distance_m) . ' m). Absensi tidak diizinkan.';
        $message_type = 'warning';
        header('Location: attendance.php?msg=' . urlencode($message) . '&type=' . urlencode($message_type));
        exit();
    }

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
    <link rel="stylesheet" href="assets/css/drawer.css">
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
                    <button class="btn drawer-toggle me-2" aria-label="Toggle menu">☰</button>
                    <h1>Absensi Peserta Magang & Pkl</h1>
                    <a href="logout.php">Logout</a>
                </header>

                <h2><?= htmlspecialchars($participant['school']) ?> - <?= htmlspecialchars($participant['major']) ?></h2>
                <div class="subtext">(<?= htmlspecialchars($participant['division_name']) ?>)</div>

                <?php if ($message): ?>
                    <div class="message <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <form id="attendanceForm" method="POST" action="" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">
                    <input type="hidden" name="accuracy" id="accuracy">
                    <input type="hidden" name="action" id="actionInput">
                    <button type="button" id="btnCheckIn" onclick="attemptAttendance('check_in')" class="btn" <?= $check_in_disabled ? 'disabled' : '' ?> aria-label="Check In">✅ Check In</button>
                    <button type="button" id="btnCheckOut" onclick="attemptAttendance('check_out')" class="btn" <?= $check_out_disabled ? 'disabled' : '' ?> aria-label="Check Out">🚪 Check Out</button>
                    <div style="align-self:center;color:#666;font-size:14px">
                        Waktu server: <?= htmlspecialchars($current_time) ?>
                    </div>
                </form>

                <div id="location-details" style="padding: 12px; border-radius: 10px; background: #e9ecef; margin-bottom: 18px; display: none;">
                    <h6 style="margin-bottom: 8px;">📍 Info Lokasi Anda</h6>
                    <div id="location-content"></div>
                </div>

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
// Toggle rekap
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

// Konfigurasi radius (diambil dari PHP agar konsisten)
const KTI_LAT = <?php echo json_encode($KTI_LAT); ?>;
const KTI_LNG = <?php echo json_encode($KTI_LNG); ?>;
const KTI_RADIUS_M = <?php echo json_encode($KTI_RADIUS_M); ?>;

function haversineMeters(lat1, lon1, lat2, lon2) {
    function toRad(x) { return x * Math.PI / 180; }
    const R = 6371000; // meters
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function attemptAttendance(action) {
    // Periksa izin & platform terlebih dahulu
    checkAndHandlePermission(action);
}
</script>
<!-- Permission modal (custom, minimal) -->
<div id="permissionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;z-index:1200;">
    <div style="background:#fff;padding:20px;border-radius:10px;max-width:520px;width:90%;box-shadow:0 6px 20px rgba(0,0,0,0.3);">
        <h5 id="permissionModalTitle">Izin Lokasi</h5>
        <div id="permissionModalBody" style="margin-top:12px;color:#333"></div>
        <div style="text-align:right;margin-top:14px;"><button class="btn" onclick="hidePermissionModal()">Tutup</button></div>
    </div>
</div>

<!-- Location confirm modal -->
<div id="locationConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;z-index:1300;">
    <div style="background:#fff;padding:18px;border-radius:10px;max-width:520px;width:92%;box-shadow:0 6px 20px rgba(0,0,0,0.3);">
        <h5 id="locationConfirmTitle">Konfirmasi Lokasi</h5>
        <div id="locationConfirmBody" style="margin-top:10px;color:#333"></div>
        <div style="text-align:right;margin-top:14px;display:flex;gap:8px;justify-content:flex-end;">
            <button class="btn" onclick="hideLocationConfirmModal()">Batal</button>
            <button class="btn" id="confirmLocationBtn">Konfirmasi & Kirim</button>
        </div>
    </div>
</div>

<script>
// Permission helper functions
function isMobilePlatform() {
    const ua = navigator.userAgent || navigator.vendor || window.opera;
    return /android/i.test(ua) || /iPad|iPhone|iPod/.test(ua);
}
function showPermissionModal(title, htmlContent) {
    let modal = document.getElementById('permissionModal');
    if (!modal) return alert(title + '\n\n' + htmlContent.replace(/<[^>]+>/g, '\n'));
    document.getElementById('permissionModalTitle').innerText = title;
    document.getElementById('permissionModalBody').innerHTML = htmlContent;
    modal.style.display = 'flex';
}
function hidePermissionModal() { const modal = document.getElementById('permissionModal'); if (modal) modal.style.display = 'none'; }

function getLocationAndSubmit(action) {
    const btn = (action === 'check_in') ? document.getElementById('btnCheckIn') : document.getElementById('btnCheckOut');
    if (btn.disabled) return;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Mencari lokasi...';

    navigator.geolocation.getCurrentPosition(function(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        const acc = pos.coords.accuracy || 0;
        const dist = haversineMeters(lat, lng, KTI_LAT, KTI_LNG);

        const distanceRounded = Math.round(dist);

        if (dist > KTI_RADIUS_M) {
            alert('Anda berada di luar wilayah PT Krakatau Tirta Industri (jarak ' + distanceRounded + ' m). Absensi tidak diizinkan.');
            btn.textContent = originalText;
            btn.disabled = false;
            return;
        }

        // Tampilkan modal konfirmasi lokasi sebelum submit
        const body = `
            <p>Lokasi terdeteksi:</p>
            <ul>
                <li><strong>Latitude:</strong> ${lat}</li>
                <li><strong>Longitude:</strong> ${lng}</li>
                <li><strong>Akurasi:</strong> ${acc} meter</li>
                <li><strong>Jarak ke KTI:</strong> ${distanceRounded} meter (maks ${KTI_RADIUS_M} m)</li>
            </ul>
            <p>Tekan <strong>Konfirmasi & Kirim</strong> untuk melanjutkan absensi.</p>
        `;
        showLocationConfirmModal(body, function confirmHandler() {
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('accuracy').value = acc;
            document.getElementById('actionInput').value = action;
            document.getElementById('attendanceForm').submit();
        }, function cancelHandler() {
            btn.textContent = originalText;
            btn.disabled = false;
        });
    }, function(err) {
        let msg = 'Gagal mendapatkan lokasi. Pastikan GPS/Location ON dan izinkan akses lokasi.';
        if (err && err.code === 1) msg = 'Izin lokasi ditolak. Aktifkan izin lokasi dan coba lagi.';
        alert(msg);
        btn.textContent = originalText;
        btn.disabled = false;
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
}

function checkAndHandlePermission(action) {
    // Deteksi apakah origin aman (HTTPS) atau localhost/127.0.0.1
    const isLocalhost = (location.hostname === 'localhost' || location.hostname === '127.0.0.1');
    const isSecure = (location.protocol === 'https:') || isLocalhost;

    // Jika tidak aman (HTTP) beri tahu user dan tawarkan fallback percobaan
    if (!isSecure) {
        const instr = `
            <p>Halaman ini dimuat lewat <strong>HTTP</strong>. Banyak browser memblokir akses lokasi pada koneksi tidak aman.</p>
            <p>Solusi yang direkomendasikan:</p>
            <ul>
                <li>Gunakan HTTPS (pasang SSL / Let's Encrypt) atau akses melalui <code>localhost</code>.</li>
                <li>Gunakan tunnel seperti <code>ngrok</code> untuk membuat HTTPS sementara selama pengujian.</li>
                <li>Atau jalankan sebagai WebView native / aplikasi jika ingin di ponsel.</li>
            </ul>
            <p>Kami akan mencoba meminta lokasi sekarang, tetapi jika browser menolak maka Anda harus menggunakan salah satu solusi di atas.</p>
        `;
        showPermissionModal('Halaman Tidak Aman (HTTP)', instr + `<p style="margin-top:8px;"><button class="btn" onclick="hidePermissionModal(); getLocationAndSubmit('`+action+`')">Coba Minta Lokasi Sekarang</button></p>`);
        return;
    }

    // jika API geolocation tidak ada
    if (!navigator.geolocation) {
        showPermissionModal('Location Unavailable', '<p>GPS/Location API tidak tersedia di browser ini.</p>');
        return;
    }

    // Periksa status izin bila Permissions API tersedia
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(function(result) {
            if (result.state === 'granted' || result.state === 'prompt') {
                getLocationAndSubmit(action);
            } else { // denied
                const desktopInstr = `
                    <p>Izin lokasi untuk browser Anda diblokir.</p>
                    <p>Untuk mengaktifkan kembali, buka pengaturan situs pada browser Anda dan izinkan Location untuk situs ini. Contoh:</p>
                    <ul>
                        <li>Chrome: klik ikon gembok di bilah alamat → Site settings → Location → Allow.</li>
                        <li>Firefox: Preferences → Privacy & Security → Permissions → Location → Settings.</li>
                        <li>Edge: klik ikon gembok → Site permissions → Location → Allow.</li>
                    </ul>
                    <p>Setelah mengubah, muat ulang halaman lalu coba lagi.</p>
                `;
                showPermissionModal('Izin Lokasi Diblokir', desktopInstr);
            }
        }).catch(function() {
            // jika Permissions API error, fallback ke meminta lokasi langsung
            getLocationAndSubmit(action);
        });
    } else {
        // fallback: langsung minta lokasi (browser akan prompt jika perlu)
        getLocationAndSubmit(action);
    }
}

// Location confirm modal helpers (tampilkan modal konfirmasi sebelum submit)
function showLocationConfirmModal(htmlContent, onConfirm, onCancel) {
    const modal = document.getElementById('locationConfirmModal');
    if (!modal) {
        if (onConfirm) onConfirm();
        return;
    }
    document.getElementById('locationConfirmBody').innerHTML = htmlContent;
    modal.style.display = 'flex';
    const btn = document.getElementById('confirmLocationBtn');
    btn.disabled = false;
    btn.onclick = function() {
        modal.style.display = 'none';
        if (onConfirm) onConfirm();
    };
    // store cancel handler so hideLocationConfirmModal can call it
    window._locationConfirmCancel = onCancel || null;
}

function hideLocationConfirmModal() {
    const modal = document.getElementById('locationConfirmModal');
    if (modal) modal.style.display = 'none';
    if (window._locationConfirmCancel) {
        try { window._locationConfirmCancel(); } catch (e) {}
        window._locationConfirmCancel = null;
    }
}

//  새로 추가된 스크립트: 페이지 로드 시 위치 정보 업데이트
document.addEventListener('DOMContentLoaded', function() {
    const locationDetails = document.getElementById('location-details');
    const locationContent = document.getElementById('location-content');

    if (!locationDetails || !locationContent) return;

    locationDetails.style.display = 'block';
    locationContent.innerHTML = '<p>Mencari lokasi Anda...</p>';

    if (!navigator.geolocation) {
        locationContent.innerHTML = '<p style="color: red;">Geolocation tidak didukung oleh browser ini.</p>';
        return;
    }

    navigator.geolocation.getCurrentPosition(function(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        const dist = haversineMeters(lat, lng, KTI_LAT, KTI_LNG);
        const distanceRounded = Math.round(dist);

        let statusMessage = '';
        if (dist > KTI_RADIUS_M) {
            statusMessage = `<p style="color: red; font-weight: bold;">Anda berada di luar radius absensi (${distanceRounded} m).</p>`;
        } else {
            statusMessage = `<p style="color: green; font-weight: bold;">Anda berada di dalam radius absensi (${distanceRounded} m).</p>`;
        }

        locationContent.innerHTML = `
            ${statusMessage}
            <ul>
                <li><strong>Jarak ke KTI:</strong> ${distanceRounded} meter (maks ${KTI_RADIUS_M} m)</li>
                <li><strong>Koordinat Anda:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</li>
            </ul>
        `;
    }, function(err) {
        let errorMessage = 'Gagal mendapatkan lokasi. Pastikan GPS aktif dan izin lokasi diberikan.';
        if (err.code === 1) {
            errorMessage = 'Izin lokasi ditolak. Aktifkan di pengaturan browser Anda.';
        }
        locationContent.innerHTML = `<p style="color: red;">${errorMessage}</p>`;
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
});
</script>
<div class="drawer-backdrop"></div>
<script src="assets/js/drawer.js"></script>
</body>
</html>
