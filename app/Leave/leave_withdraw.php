<?php
session_start();
require_once __DIR__ . '/../../config/app.php';
require_once BASE_PATH . 'config/database.php';

// Pastikan user login dan role benar
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['mahasiswa_mbkm', 'siswa_pkl'])
) {
    header('Location: ' . APP_URL . '/public/index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Ambil ID request dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request.");
}

$request_id = intval($_GET['id']);

// Ambil data peserta aktif (ini sama seperti leave_request.php)
$participant = fetchOne(
    "SELECT * FROM participants 
     WHERE user_id = ? AND status = 'aktif'",
    [$user_id]
);

if (!$participant) {
    die("Unauthorized.");
}

$participant_id = $participant['id'];

try {
    // Pastikan request milik peserta ini & masih pending
    $leave = fetchOne(
        "SELECT * FROM leave_requests 
         WHERE id = ? AND participant_id = ? AND status = 'pending'",
        [$request_id, $participant_id]
    );

    if (!$leave) {
        die("Pengajuan tidak ditemukan atau tidak bisa di-withdraw.");
    }

    // Update status menjadi withdrawn
    executeQuery(
        "UPDATE leave_requests 
         SET status = 'withdrawn', Withdrawn_at = GETDATE()
         WHERE id = ? AND participant_id = ?",
        [$request_id, $participant_id]
    );

    header("Location: leave_request.php?msg=withdraw_success");
    exit();

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
