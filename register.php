<?php
require __DIR__ . '/auth.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
$pesan = '';
$namaInput = '';
$usernameInput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_exit();
    $namaInput = trim((string) ($_POST['nama'] ?? ''));
    $usernameInput = strtolower(trim((string) ($_POST['username'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $konfirmasi = (string) ($_POST['konfirmasi_password'] ?? '');

    if ($namaInput === '' || mb_strlen($namaInput) > 100) {
        $pesan = 'Nama wajib diisi dan maksimal 100 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $usernameInput)) {
        $pesan = 'Username harus 3–30 karakter dan hanya boleh berisi huruf, angka, titik, atau garis bawah.';
    } elseif (strlen($password) < 8) {
        $pesan = 'Kata sandi minimal 8 karakter.';
    } elseif ($password !== $konfirmasi) {
        $pesan = 'Konfirmasi kata sandi belum cocok.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (nama, username, password_hash) VALUES (:nama, :username, :password_hash)');
            $stmt->execute([
                ':nama' => $namaInput,
                ':username' => $usernameInput,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();
            $goal = $pdo->prepare('INSERT INTO target_tabungan (user_id, nominal, target_tanggal) VALUES (:user_id, 10000000, :target_tanggal)');
            $goal->execute([':user_id' => $userId, ':target_tanggal' => date('Y-m-d', strtotime('+1 year'))]);
            $pdo->commit();

            // Daftar langsung masuk, supaya alurnya sederhana untuk pengguna.
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $usernameInput;
            $_SESSION['nama'] = $namaInput;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $pesan = 'Username tersebut sudah digunakan. Coba username lain.';
            } else {
                $pesan = 'Pendaftaran gagal. Pastikan database sudah disiapkan dengan database.sql.';
            }
        }
    }
}
function e_register($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar | Celengan</title><link rel="stylesheet" href="assets/auth.css">
</head>
<body>
<header class="auth-topbar"><a class="auth-brand" href="login.php"><span class="brand-icon">🏦</span><span>Celengan</span></a></header>
<main class="auth-page">
  <section class="auth-card" aria-labelledby="heading-register">
    <div class="auth-tabs"><a class="auth-tab" href="login.php">Masuk</a><a class="auth-tab active" href="register.php" aria-current="page">Daftar</a></div>
    <div class="auth-content">
      <h1 id="heading-register">Buat Akun Celengan ✨</h1>
      <p class="auth-subtitle">Mulai catat pemasukan, pengeluaran, dan target tabunganmu.</p>
      <?php if ($pesan !== ''): ?><div class="auth-alert error" role="alert"><?= e_register($pesan) ?></div><?php endif; ?>
      <form method="post" action="register.php" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= e_register($_SESSION['csrf_token']) ?>">
        <label for="nama">Nama Lengkap</label>
        <input id="nama" name="nama" type="text" autocomplete="name" value="<?= e_register($namaInput) ?>" placeholder="Masukkan nama" maxlength="100" required autofocus>
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" value="<?= e_register($usernameInput) ?>" placeholder="Contoh: ahmad_123" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}" required>
        <p class="field-hint">3–30 karakter: huruf, angka, titik, atau garis bawah.</p>
        <label for="password">Kata Sandi</label>
        <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Minimal 8 karakter" minlength="8" required>
        <label for="konfirmasi_password">Konfirmasi Kata Sandi</label>
        <input id="konfirmasi_password" name="konfirmasi_password" type="password" autocomplete="new-password" placeholder="Ulangi kata sandi" minlength="8" required>
        <button class="auth-submit" type="submit">Buat Akun</button>
      </form>
      <p class="auth-bottom-text">Sudah punya akun? <a href="login.php">Masuk di sini</a>.</p>
    </div>
  </section>
</main>
</body></html>
