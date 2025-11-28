<?php
session_start();

// Include configuration to access APP_URL
require_once __DIR__ . '/../config/app.php';

// Function to show logout page with confirmation
function showLogoutPage() {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konfirmasi Logout - Sistem Absensi</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .logout-card {
                background: white;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 450px;
                width: 100%;
            }
            
            .logout-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                color: white;
                font-size: 32px;
            }
            
            .btn-logout {
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
                border: none;
                padding: 12px 30px;
                border-radius: 50px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .btn-logout:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(238, 90, 36, 0.3);
            }
            
            .btn-cancel {
                background: transparent;
                border: 2px solid #e9ecef;
                color: #6c757d;
                padding: 12px 30px;
                border-radius: 50px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .btn-cancel:hover {
                background: #f8f9fa;
                color: #495057;
            }
        </style>
    </head>
    <body>
        <div class="logout-card">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h2 class="mb-3">Konfirmasi Logout</h2>
            <p class="text-muted mb-4">
                Apakah Anda yakin ingin keluar dari sistem? Anda harus login kembali untuk mengakses fitur-fitur sistem.
            </p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="index.php" class="btn btn-cancel">
                    <i class="fas fa-arrow-left me-2"></i>Batal
                </a>
                <a href="?confirm=1" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt me-2"></i>Keluar
                </a>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// Check if logout confirmation is requested
if (!isset($_GET['confirm'])) {
    showLogoutPage();
}

// Destroy all session data
$_SESSION = array();

// If session was started with cookies, delete those too
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . APP_URL . '/public/index.php');
exit();
?>