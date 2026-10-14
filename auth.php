<?php
// Bootstrap autentikasi Celengan. Semua halaman privat harus memanggil require_login().
$httpsAktif = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('CELENGANSESSID');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $httpsAktif,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pulihkan sesi dari token "Ingat Saya" jika sesi biasa sudah berakhir.
if (empty($_SESSION['user_id']) && !empty($_COOKIE['celengan_remember'])) {
    $bagian = explode(':', (string) $_COOKIE['celengan_remember'], 2);
    if (count($bagian) === 2 && preg_match('/^[a-f0-9]{24}$/', $bagian[0]) && preg_match('/^[a-f0-9]{64}$/', $bagian[1])) {
        try {
            $stmt = $pdo->prepare(
                'SELECT r.token_hash, r.user_id, u.username, u.nama
                 FROM remember_tokens r
                 JOIN users u ON u.id = r.user_id
                 WHERE r.selector = :selector AND r.expires_at > NOW() LIMIT 1'
            );
            $stmt->execute([':selector' => $bagian[0]]);
            $remember = $stmt->fetch();
            if ($remember && hash_equals($remember['token_hash'], hash('sha256', $bagian[1]))) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $remember['user_id'];
                $_SESSION['username'] = $remember['username'];
                $_SESSION['nama'] = $remember['nama'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $delete = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
                $delete->execute([':selector' => $bagian[0]]);
                setcookie('celengan_remember', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $httpsAktif, 'httponly' => true, 'samesite' => 'Lax']);
            }
        } catch (PDOException $e) {
            // Saat tabel token belum tersedia, halaman tetap menunjukkan pesan setup dari db.php/database.
        }
    }
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function verify_csrf_or_exit(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function simpan_remember_token(PDO $pdo, int $userId): void
{
    global $httpsAktif;
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + (30 * 24 * 60 * 60);
    $stmt = $pdo->prepare('INSERT INTO remember_tokens (selector, user_id, token_hash, expires_at) VALUES (:selector, :user_id, :token_hash, FROM_UNIXTIME(:expires))');
    $stmt->execute([
        ':selector' => $selector,
        ':user_id' => $userId,
        ':token_hash' => hash('sha256', $validator),
        ':expires' => $expires,
    ]);
    setcookie('celengan_remember', $selector . ':' . $validator, [
        'expires' => $expires,
        'path' => '/',
        'secure' => $httpsAktif,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function hapus_remember_cookie(): void
{
    global $httpsAktif;
    setcookie('celengan_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $httpsAktif,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
