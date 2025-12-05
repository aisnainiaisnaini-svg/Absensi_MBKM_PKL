<?php
session_start();
require_once __DIR__ . '/../config/app.php'; // Include the main configuration file first
require_once BASE_PATH . 'config/database.php';

$error = '';
$message = '';
$message_type = '';

// Login processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        // Query user from database
        $user = fetchOne("SELECT u.*, p.division_id
                         FROM users u
                         LEFT JOIN participants p ON u.id = p.user_id
                         WHERE u.username = ?", [$username]);

        if (!$user) {
            $error = 'Username tidak ditemukan!';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Password salah!';
        } else {
            // Since there's no status column, assume all users are active
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['division_id'] = $user['division_id'] ?? null;

            // Set user's full name in session
            $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: dashboard.php');
                    exit();
                case 'pembimbing':
                    // Redirect to admin dashboard which handles pembimbing role
                    header('Location: dashboard.php');
                    exit();
                case 'mahasiswa_mbkm':
                case 'siswa_pkl':
                    // Both participant roles use the same dashboard
                    header('Location: dashboard.php');
                    exit();
                default:
                    $error = 'Role pengguna tidak valid!';
            }
        }
    }
}

$base_path = '../'; // Adjusted for public directory structure
$title = 'Login - Aplikasi Absensi MBKM/PKL';
include BASE_PATH . 'app/Core/_includes/header.php';
?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="login-header">
                        <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                        <h3>Aplikasi Pengawasan</h3>
                        <p class="mb-0">Magang & PKL</p>
                    </div>
                    <div class="card-body p-4">

                        <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                        <div class="alert alert-<?= htmlspecialchars($message_type) ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>

                        <!-- FORM LOGIN -->
                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>

                        <div class="mt-4 text-center">
                            <small class="text-muted">
                                <strong>Demo Login:</strong><br>
                                Admin: admin / password<br>
                                <!-- Pembimbing: pembimbing1 / password -->
                            </small>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
<?php include BASE_PATH . 'app/Core/_includes/footer.php'; ?>
