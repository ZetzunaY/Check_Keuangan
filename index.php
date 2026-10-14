<?php
// Titik masuk utama: pengguna yang sudah login menuju Dashboard, lainnya ke halaman Masuk.
require __DIR__ . '/auth.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
