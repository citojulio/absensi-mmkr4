<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isAdminLoggedIn()) {
    redirect('laporan.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        setFlash('error', 'Username dan password wajib diisi.');
        redirect('login.php');
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id, username, password, nama_lengkap FROM admin WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_nama'] = $admin['nama_lengkap'] ?: $admin['username'];
            redirect('laporan.php');
        }

        setFlash('error', 'Username atau password salah.');
        redirect('login.php');

    } catch (Throwable $e) {
        error_log('login error: ' . $e->getMessage());
        setFlash('error', 'Terjadi kesalahan sistem. Silakan coba lagi.');
        redirect('login.php');
    }
}

$pageTitle = 'Login Admin';
$activeNav = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-wrap">
    <div class="card">
        <h2 style="margin-bottom:4px;">Login Admin</h2>
        <p class="text-muted text-sm" style="margin-bottom:20px;">Khusus pengurus/sekretaris MM KR4 untuk mengelola laporan, statistik, dan data anggota.</p>
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Masuk</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
