<?php
require __DIR__ . '/auth.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Lupa Password | Celengan</title><link rel="stylesheet" href="assets/auth.css"></head><body>
<header class="auth-topbar"><a class="auth-brand" href="login.php"><span class="brand-icon">🏦</span><span>Celengan</span></a></header>
<main class="auth-page"><section class="auth-card"><div class="auth-content"><h1>Lupa Password?</h1><p class="auth-subtitle">Fitur reset password melalui email belum diaktifkan pada versi lokal ini.</p><div class="auth-alert success">Untuk saat ini, hubungi pengelola aplikasi untuk bantuan pemulihan akun. Jangan membagikan kata sandi kepada siapa pun.</div><a class="auth-submit auth-submit-link" href="login.php">Kembali ke Masuk</a></div></section></main>
</body></html>
