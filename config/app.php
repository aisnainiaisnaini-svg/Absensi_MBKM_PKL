<?php
// Konfigurasi Absensi Magang_PKL

// Nama aplikasi
define('APP_NAME', 'Absensi Magang_PKL');
define('APP_VERSION', '1.0.0');
define('APP_DESCRIPTION', 'Sistem pengelolaan dan pengawasan kegiatan magang dan PKL');

// URL aplikasi (sesuaikan dengan domain Anda)
define('APP_URL', 'http://localhost/Absensi_MBKM_PKL');

// Base path for file system operations
define('BASE_PATH', __DIR__ . '/../');

// Konfigurasi email (untuk notifikasi)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your-email@gmail.com');
define('MAIL_PASSWORD', 'your-app-password');
define('MAIL_FROM_NAME', 'Absensi Magang_PKL');

// Konfigurasi upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png']);
define('UPLOAD_PATH', 'uploads/reports/');

// Konfigurasi keamanan
define('SESSION_TIMEOUT', 3600); // 1 jam
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);

// Konfigurasi pagination
define('ITEMS_PER_PAGE', 10);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi environment
define('APP_ENV', 'development'); // development atau production

// Error reporting (disable di production)
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Fungsi helper
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd M Y H:i') {
    return date($format, strtotime($datetime));
}

function formatTime($time, $format = 'H:i') {
    return date($format, strtotime($time));
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

function isImage($filename) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $imageExtensions);
}

function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    switch ($extension) {
        case 'pdf':
            return 'fas fa-file-pdf text-danger';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word text-primary';
        case 'txt':
            return 'fas fa-file-alt text-secondary';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'fas fa-file-image text-success';
        default:
            return 'fas fa-file text-muted';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function calculateWorkHours($check_in, $check_out) {
    if (!$check_in || !$check_out) {
        return 0;
    }

    $start = strtotime($check_in);
    $end = strtotime($check_out);
    $hours = ($end - $start) / 3600;

    return round($hours, 2);
}

function getAttendanceStatus($status) {
    $statuses = [
        'hadir' => ['class' => 'success', 'icon' => 'check-circle', 'label' => 'Hadir'],
        'izin' => ['class' => 'warning', 'icon' => 'calendar-times', 'label' => 'Izin'],
        'sakit' => ['class' => 'info', 'icon' => 'user-injured', 'label' => 'Sakit'],
        'alpa' => ['class' => 'danger', 'icon' => 'times-circle', 'label' => 'Alpa']
    ];

    return $statuses[$status] ?? ['class' => 'secondary', 'icon' => 'question', 'label' => 'Unknown'];
}

function getLeaveTypeLabel($type) {
    $types = [
        'sakit' => 'Sakit',
        'izin' => 'Izin Pribadi',
        'keperluan_mendesak' => 'Izin Akademik',
        'izin_akademik'      => 'Izin Akademik'
    ];

    return $types[$type] ?? ucfirst($type);
}

function getRoleLabel($role) {
    $roles = [
        'admin' => 'Administrator',
        'pembimbing' => 'Pembimbing',
        'peserta' => 'Peserta'
    ];

    return $roles[$role] ?? ucfirst($role);
}

function getStatusLabel($status) {
    $statuses = [
        'aktif' => 'Aktif',
        'selesai' => 'Selesai',
        'dikeluarkan' => 'Dikeluarkan'
    ];

    return $statuses[$status] ?? ucfirst($status);
}
?>