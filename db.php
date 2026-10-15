<?php
$host = 'sql112.infinityfree.com';
$dbname = 'if0_43175733_celengan'; 
$username = 'if0_43175733';
$password = 'XyUIyLh6jvJN4nG';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi database.');
}