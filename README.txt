CELENGAN - STARTER DENGAN LOGIN & REGISTRASI
============================================

FITUR
- login.php             Masuk dengan username dan password.
- register.php          Registrasi akun baru.
- logout.php            Logout melalui POST dan token CSRF.
- lupa-password.php     Halaman informasi; reset melalui email belum diaktifkan.
- dashboard.php         Dashboard, transaksi, dan target tabungan.
- riwayat.php            Filter dan daftar riwayat transaksi.
- auth.php              Pemeriksaan sesi, perlindungan halaman, CSRF, dan token Ingat Saya.
- db.php                Koneksi PDO ke MySQL.
- database.sql          Skema database untuk instalasi BARU.
- migrasi_database_lama.sql  Migrasi jika sebelumnya sudah memakai starter tanpa login.
- assets/style.css      Gaya dashboard dan riwayat.
- assets/auth.css       Gaya login dan registrasi.
- assets/script.js      Interaksi formulir dashboard.

CARA MENJALANKAN - INSTALASI BARU
1. Ekstrak folder "celengan_dashboard" ke C:\xampp\htdocs\celengan_dashboard.
2. Nyalakan Apache dan MySQL dari XAMPP.
3. Buka http://localhost/phpmyadmin.
4. Jika database celengan yang lama SUDAH dibuat dari starter sebelumnya, lihat bagian MIGRASI DATABASE LAMA di bawah.
   Jika benar-benar instalasi baru, pilih tab Import dan impor database.sql.
5. Pengaturan default XAMPP ada di db.php: host localhost, database celengan, username root, password kosong.
6. Buka http://localhost/celengan_dashboard/login.php. Klik tab Daftar untuk membuat akun.
7. Setelah pendaftaran berhasil, akun akan langsung masuk ke Dashboard.

MIGRASI DATABASE LAMA (JIKA DATABASE CELENGAN SUDAH BERISI TABEL VERSI SEBELUMNYA)
Penting: lakukan Export/backup database celengan terlebih dahulu.
1. Di phpMyAdmin, pilih database celengan > Import > pilih migrasi_database_lama.sql > Go.
   Jalankan file migrasi ini satu kali saja, karena file ALTER TABLE tidak untuk dijalankan berulang.
2. Buka login.php, masuk ke tab Daftar, lalu buat akun yang akan menjadi pemilik data lama.
3. Di phpMyAdmin, pilih database celengan > tab SQL, lalu ganti GANTI_USERNAME dengan username akun tadi dan jalankan:

   DELETE FROM target_tabungan
   WHERE user_id = (SELECT id FROM users WHERE username = 'GANTI_USERNAME');

   UPDATE transaksi
   SET user_id = (SELECT id FROM users WHERE username = 'GANTI_USERNAME' LIMIT 1)
   WHERE user_id IS NULL;

   UPDATE target_tabungan
   SET user_id = (SELECT id FROM users WHERE username = 'GANTI_USERNAME' LIMIT 1)
   WHERE user_id IS NULL;

   Perintah pertama menghapus target default baru milik akun itu supaya target lama bisa dipasangkan ke akun tanpa melanggar UNIQUE user_id. Transaksi dan target lama kemudian menjadi milik akun tersebut.
4. Buka Dashboard dan periksa data. Jika data lama tidak diperlukan, gunakan instalasi baru setelah membuat backup, jangan menjalankan migrasi.

LOGIKA KEUANGAN
- Pemasukan: kas bertambah.
- Pengeluaran: kas berkurang.
- Setor Tabungan: kas berkurang dan saldo tabungan bertambah.
- Total uang = saldo kas + saldo tabungan, sehingga setor tabungan tidak mengurangi total kepemilikan.
- Target dan transaksi dipisahkan berdasarkan akun yang masuk.
- Setoran dan pengeluaran ditolak jika melebihi saldo kas yang tersedia.
- Progres target dihitung dari saldo tabungan yang tercatat sebagai Setor Tabungan.
- Estimasi waktu menggunakan rata-rata setoran harian sejak setoran pertama tercatat.

KEAMANAN DASAR
- Password disimpan sebagai hash dengan password_hash() dan diperiksa dengan password_verify().
- Query memakai PDO prepared statements.
- Halaman dashboard dan riwayat memerlukan sesi login.
- Token CSRF digunakan pada formulir POST.
- Tombol Ingat Saya membuat token acak yang disimpan dalam bentuk hash dan cookie HttpOnly.
- Ini merupakan proyek pembelajaran lokal. Reset password email belum aktif dan aplikasi belum diaudit untuk penggunaan produksi.
