<?php
require __DIR__ . '/auth.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
$pesan = '';
$jenisPesan = 'error';
$usernameInput = '';
if (isset($_GET['keluar']) && $_GET['keluar'] === 'berhasil') {
    $pesan = 'Kamu berhasil keluar dari Celengan.';
    $jenisPesan = 'success';
}
if (isset($_GET['daftar']) && $_GET['daftar'] === 'berhasil') {
    $pesan = 'Akun berhasil dibuat. Silakan masuk.';
    $jenisPesan = 'success';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_exit();
    $usernameInput = strtolower(trim((string) ($_POST['username'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    try {
        $stmt = $pdo->prepare('SELECT id, nama, username, password_hash FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $usernameInput]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $pesan = 'Username atau kata sandi tidak cocok.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            if (!empty($_POST['ingat_saya'])) {
                // Bersihkan token kadaluwarsa sebelum membuat token baru.
                $pdo->exec('DELETE FROM remember_tokens WHERE expires_at < NOW()');
                simpan_remember_token($pdo, (int) $user['id']);
            }
            header('Location: dashboard.php');
            exit;
        }
    } catch (PDOException $e) {
        $pesan = 'Gagal masuk. Pastikan database sudah disiapkan dengan database.sql.';
    }
}
function e_login($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk | Celengan</title><link rel="stylesheet" href="assets/auth.css">
</head>
<body>
<header class="auth-topbar"><a class="auth-brand" href="login.php"><span class="brand-icon">🏦</span><span>Celengan</span></a></header>
<main class="auth-page">
  <section class="auth-card" aria-labelledby="heading-login">
    <div class="auth-tabs"><a class="auth-tab active" href="login.php" aria-current="page">Masuk</a><a class="auth-tab" href="register.php">Daftar</a></div>
    <div class="auth-content">
      <h1 id="heading-login">Selamat Datang Kembali! 👋</h1>
      <p class="auth-subtitle">Silakan masuk untuk memantau target tabunganmu.</p>
      <?php if ($pesan !== ''): ?><div class="auth-alert <?= $jenisPesan === 'success' ? 'success' : 'error' ?>" role="status"><?= e_login($pesan) ?></div><?php endif; ?>
      <form method="post" action="login.php" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= e_login($_SESSION['csrf_token']) ?>">
        <label for="username">Username / Nama Pengguna</label>
        <input id="username" name="username" type="text" autocomplete="username" value="<?= e_login($usernameInput) ?>" placeholder="Masukkan username" minlength="3" maxlength="30" required autofocus>
        <label for="password">Kata Sandi / Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required>
        <div class="auth-options"><label class="remember-label"><input type="checkbox" name="ingat_saya" value="1"> Ingat Saya</label><a href="lupa-password.php">Lupa Password?</a></div>
        <button class="auth-submit" type="submit">Masuk ke Dashboard</button>
      </form>
      <p class="auth-bottom-text">Belum punya akun? Klik tab <a href="register.php">Daftar</a> di atas untuk membuat akun baru.</p>
    </div>
  </section>
</main>
</body></html>
