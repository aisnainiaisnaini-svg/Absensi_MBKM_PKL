<?php
session_start();
require_once __DIR__ . '/../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

// Pastikan user login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/public/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $sql = "SELECT u.Full_Name AS nama, a.[date] AS tanggal,
                   DATENAME(WEEKDAY, a.[date]) AS hari,
                   a.check_in AS jam_masuk, a.check_out AS jam_pulang,
                   a.status
            FROM attendance a
            INNER JOIN participants p ON p.Id = a.Participant_Id
            INNER JOIN users u ON u.Id = p.User_Id
            WHERE p.User_Id = :user_id
            ORDER BY a.[date] DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal mengambil data absensi: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Absensi</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #f3f4f6;
    margin: 0;
    padding: 30px;
}
.container {
    max-width: 1200px;
    margin: auto;
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px 30px;
}
h2 {
    text-align: center;
    color: #333;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: center;
}
th {
    background: #4CAF50;
    color: white;
}
tr:nth-child(even) {
    background: #f9f9f9;
}
.status-hadir {
    color: green;
    font-weight: bold;
}
.status-izin {
    color: orange;
    font-weight: bold;
}
.status-tidak {
    color: red;
    font-weight: bold;
}
</style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="container">
    <h2>📋 Rekap Absensi Bulanan</h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Hari</th>
                <th>Tanggal</th>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($absensi): $no = 1; ?>
                <?php foreach ($absensi as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama']) ?></td>
                        <td><?= htmlspecialchars($row['hari']) ?></td>
                        <td><?= htmlspecialchars($row['tanggal']) ?></td>
                        <td><?= $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?></td>
                        <td><?= $row['jam_pulang'] ? date('H:i', strtotime($row['jam_pulang'])) : '-' ?></td>
                        <td>
                            <?php
                            if ($row['status'] == 'hadir') {
                                echo "<span class='status-hadir'>Hadir</span>";
                            } elseif ($row['status'] == 'izin') {
                                echo "<span class='status-izin'>Izin</span>";
                            } else {
                                echo "<span class='status-tidak'>Tidak Hadir</span>";
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">Belum ada data absensi.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>