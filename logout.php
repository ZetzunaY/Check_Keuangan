<?php
require __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
verify_csrf_or_exit();
if (!empty($_COOKIE['celengan_remember'])) {
    $parts = explode(':', (string) $_COOKIE['celengan_remember'], 2);
    if (count($parts) === 2 && preg_match('/^[a-f0-9]{24}$/', $parts[0])) {
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $stmt->execute([':selector' => $parts[0]]);
    }
}
hapus_remember_cookie();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?: '/',
        'secure' => (bool) $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_destroy();
header('Location: login.php?keluar=berhasil');
exit;
