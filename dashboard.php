<?php
require __DIR__ . '/auth.php';
require_login();
$userId = (int) $_SESSION['user_id'];

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah($number)
{
    return 'Rp ' . number_format((float) $number, 0, ',', '.');
}

function tanggalIndonesia($date, $includeYear = true)
{
    if (!$date) return '-';
    $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $timestamp = strtotime($date);
    $result = date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)];
    return $includeYear ? $result . ' ' . date('Y', $timestamp) : $result;
}

function saldoKas(PDO $pdo, int $userId, $excludeId = null)
{
    $sql = "SELECT COALESCE(SUM(CASE
                WHEN tipe = 'pemasukan' THEN nominal
                WHEN tipe IN ('pengeluaran', 'setor_tabungan') THEN -nominal
                ELSE 0 END), 0) AS saldo
            FROM transaksi WHERE user_id = :user_id";
    if ($excludeId !== null) $sql .= ' AND id <> :id';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($excludeId !== null) $stmt->bindValue(':id', (int) $excludeId, PDO::PARAM_INT);
    $stmt->execute();
    return (float) $stmt->fetchColumn();
}

function saldoTabungan(PDO $pdo, int $userId)
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(nominal), 0) FROM transaksi WHERE user_id = :user_id AND tipe = 'setor_tabungan'");
    $stmt->execute([':user_id' => $userId]);
    return (float) $stmt->fetchColumn();
}

function tanggalValid($date)
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

$pesan = $_SESSION['flash_message'] ?? '';
$jenisPesan = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_exit();

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_transaction') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $id = $id && $id > 0 ? (int) $id : null;
            $tanggal = $_POST['tanggal'] ?? '';
            $tipe = $_POST['tipe'] ?? '';
            $nominalInput = str_replace(',', '.', trim((string) ($_POST['nominal'] ?? '')));
            $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
            $nominal = is_numeric($nominalInput) ? (float) $nominalInput : 0;
            $tipeValid = ['pemasukan', 'pengeluaran', 'setor_tabungan'];

            if (!tanggalValid($tanggal)) {
                throw new RuntimeException('Tanggal transaksi tidak valid.');
            }
            if (!in_array($tipe, $tipeValid, true)) {
                throw new RuntimeException('Pilih jenis transaksi yang tersedia.');
            }
            if ($nominal <= 0 || $nominal > 999999999999999) {
                throw new RuntimeException('Nominal harus lebih besar dari nol.');
            }
            if ($keterangan === '' || mb_strlen($keterangan) > 255) {
                throw new RuntimeException('Keterangan wajib diisi dan maksimal 255 karakter.');
            }

            if ($id !== null) {
                $check = $pdo->prepare('SELECT id FROM transaksi WHERE id = :id AND user_id = :user_id');
                $check->execute([':id' => $id, ':user_id' => $userId]);
                if (!$check->fetch()) throw new RuntimeException('Transaksi yang ingin diedit tidak ditemukan.');
            }

            // Pengeluaran dan setoran mengambil uang dari kas. Saat edit, transaksi lama dikecualikan dari hitungan.
            if (in_array($tipe, ['pengeluaran', 'setor_tabungan'], true)) {
                $kasTersedia = saldoKas($pdo, $userId, $id);
                if ($nominal > $kasTersedia) {
                    throw new RuntimeException('Saldo kas tidak cukup. Saldo kas yang tersedia: ' . rupiah($kasTersedia) . '.');
                }
            }

            if ($id !== null) {
                $stmt = $pdo->prepare('UPDATE transaksi SET tanggal = :tanggal, tipe = :tipe, nominal = :nominal, keterangan = :keterangan WHERE id = :id AND user_id = :user_id');
                $stmt->execute([':tanggal' => $tanggal, ':tipe' => $tipe, ':nominal' => $nominal, ':keterangan' => $keterangan, ':id' => $id, ':user_id' => $userId]);
                $_SESSION['flash_message'] = 'Transaksi berhasil diperbarui.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO transaksi (user_id, tanggal, tipe, nominal, keterangan) VALUES (:user_id, :tanggal, :tipe, :nominal, :keterangan)');
                $stmt->execute([':user_id' => $userId, ':tanggal' => $tanggal, ':tipe' => $tipe, ':nominal' => $nominal, ':keterangan' => $keterangan]);
                $_SESSION['flash_message'] = 'Transaksi berhasil disimpan.';
            }
            $_SESSION['flash_type'] = 'success';
            header('Location: dashboard.php');
            exit;
        }

        if ($action === 'delete_transaction') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new RuntimeException('ID transaksi tidak valid.');
            $stmt = $pdo->prepare('DELETE FROM transaksi WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $id, ':user_id' => $userId]);
            $_SESSION['flash_message'] = $stmt->rowCount() ? 'Transaksi berhasil dihapus.' : 'Transaksi tidak ditemukan.';
            $_SESSION['flash_type'] = 'success';
            header('Location: dashboard.php');
            exit;
        }

        if ($action === 'save_goal') {
            $targetInput = str_replace(',', '.', trim((string) ($_POST['target_nominal'] ?? '')));
            $targetNominal = is_numeric($targetInput) ? (float) $targetInput : 0;
            $targetTanggal = $_POST['target_tanggal'] ?? '';
            if ($targetNominal <= 0 || $targetNominal > 999999999999999) {
                throw new RuntimeException('Nominal target harus lebih besar dari nol.');
            }
            if (!tanggalValid($targetTanggal)) throw new RuntimeException('Tanggal target tidak valid.');
            if ($targetTanggal < date('Y-m-d')) throw new RuntimeException('Tanggal target tidak boleh di masa lalu.');

            $stmt = $pdo->prepare('INSERT INTO target_tabungan (user_id, nominal, target_tanggal)
                VALUES (:user_id, :nominal, :target_tanggal)
                ON DUPLICATE KEY UPDATE nominal = VALUES(nominal), target_tanggal = VALUES(target_tanggal)');
            $stmt->execute([':user_id' => $userId, ':nominal' => $targetNominal, ':target_tanggal' => $targetTanggal]);
            $_SESSION['flash_message'] = 'Target tabungan berhasil disimpan.';
            $_SESSION['flash_type'] = 'success';
            header('Location: dashboard.php');
            exit;
        }
        throw new RuntimeException('Aksi tidak dikenali.');
    } catch (RuntimeException $ex) {
        $_SESSION['flash_message'] = $ex->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php' . (!empty($_POST['id']) && $action === 'save_transaction' ? '?edit=' . (int) $_POST['id'] : ''));
        exit;
    } catch (PDOException $ex) {
        $_SESSION['flash_message'] = 'Data gagal diproses. Periksa koneksi database dan struktur tabel.';
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php');
        exit;
    }
}

$stmtTarget = $pdo->prepare('SELECT nominal, target_tanggal FROM target_tabungan WHERE user_id = :user_id LIMIT 1');
$stmtTarget->execute([':user_id' => $userId]);
$target = $stmtTarget->fetch();
if (!$target) {
    $target = ['nominal' => 10000000, 'target_tanggal' => date('Y-m-d', strtotime('+1 year'))];
    $stmtTargetInsert = $pdo->prepare('INSERT INTO target_tabungan (user_id, nominal, target_tanggal) VALUES (:user_id, :nominal, :target_tanggal)');
    $stmtTargetInsert->execute([':user_id' => $userId, ':nominal' => $target['nominal'], ':target_tanggal' => $target['target_tanggal']]);
}

$kas = saldoKas($pdo, $userId);
$tabungan = saldoTabungan($pdo, $userId);
$totalUang = $kas + $tabungan;
$targetNominal = (float) $target['nominal'];
$sisaTarget = max(0, $targetNominal - $tabungan);
$progres = $targetNominal > 0 ? min(100, ($tabungan / $targetNominal) * 100) : 0;

$statStmt = $pdo->prepare("SELECT COALESCE(SUM(nominal), 0) AS total_setor, MIN(tanggal) AS setoran_pertama FROM transaksi WHERE user_id = :user_id AND tipe = 'setor_tabungan'");
$statStmt->execute([':user_id' => $userId]);
$stat = $statStmt->fetch();
$estimasiHari = null;
if ($sisaTarget <= 0) {
    $estimasiHari = 0;
} elseif ((float) $stat['total_setor'] > 0 && !empty($stat['setoran_pertama'])) {
    $hariBerjalan = max(1, (int) floor((strtotime(date('Y-m-d')) - strtotime($stat['setoran_pertama'])) / 86400) + 1);
    $rataRataHarian = (float) $stat['total_setor'] / $hariBerjalan;
    if ($rataRataHarian > 0) $estimasiHari = (int) ceil($sisaTarget / $rataRataHarian);
}
$hariMenujuTarget = max(0, (int) floor((strtotime($target['target_tanggal']) - strtotime(date('Y-m-d'))) / 86400));
$statusTarget = 'Mulai menabung';
if ($sisaTarget <= 0) $statusTarget = 'Target tercapai';
elseif ($estimasiHari !== null) $statusTarget = $estimasiHari <= $hariMenujuTarget ? 'On Track' : 'Perlu dipercepat';

$terbaruStmt = $pdo->prepare('SELECT id, tanggal, tipe, nominal, keterangan FROM transaksi WHERE user_id = :user_id ORDER BY tanggal DESC, id DESC LIMIT 5');
$terbaruStmt->execute([':user_id' => $userId]);
$transaksiTerbaru = $terbaruStmt->fetchAll();
$editData = null;
if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, tanggal, tipe, nominal, keterangan FROM transaksi WHERE id = :id AND user_id = :user_id');
    $stmt->execute([':id' => (int) $_GET['edit'], ':user_id' => $userId]);
    $editData = $stmt->fetch() ?: null;
}
$nilaiTanggalForm = $editData['tanggal'] ?? date('Y-m-d');
$nilaiTipeForm = $editData['tipe'] ?? 'pemasukan';
$nilaiNominalForm = isset($editData['nominal']) ? (string) (float) $editData['nominal'] : '';
$nilaiKeteranganForm = $editData['keterangan'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Celengan</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="dashboard.php"><span class="brand-icon">🏦</span><span>Celengan</span></a>
    <nav class="nav-links" aria-label="Navigasi utama">
        <a class="active" href="dashboard.php">Dashboard</a>
        <a href="riwayat.php">Riwayat</a>
    </nav>
    <div class="user-menu"><span class="user-avatar">👤</span><span><?= e($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengguna') ?></span><form method="post" action="logout.php" class="logout-form"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><button type="submit" class="logout-button">Logout</button></form></div>
</header>

<main class="container">
    <section class="page-heading">
        <p class="eyebrow">RUANG KEUANGAN PRIBADI</p>
        <h1>Selamat Datang di Celengan! 👋</h1>
        <p>Pantau target tabungan dan keuangan harianmu di sini.</p>
    </section>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= $jenisPesan === 'error' ? 'alert-error' : 'alert-success' ?>" role="status"><?= e($pesan) ?></div>
    <?php endif; ?>

    <section class="stats-grid" aria-label="Ringkasan keuangan">
        <article class="stat-card">
            <div class="stat-heading"><span class="stat-icon green">💵</span><span>Total Saldo Kas</span></div>
            <p class="stat-value"><?= e(rupiah($kas)) ?></p>
            <p class="stat-note">Uang kas yang tersedia saat ini</p>
        </article>
        <article class="stat-card goal-card">
            <div class="stat-heading"><span class="stat-icon gold">🎯</span><span>Target Tabungan</span><button type="button" class="button button-outline button-small" id="openTarget">✎ Target-ku</button></div>
            <p class="stat-value"><?= e(rupiah($targetNominal)) ?></p>
            <p class="stat-note">Terkumpul: <strong><?= e(rupiah($tabungan)) ?></strong></p>
        </article>
        <article class="stat-card">
            <div class="stat-heading"><span class="stat-icon blue">📈</span><span>Progres Target</span></div>
            <p class="stat-value"><?= number_format($progres, 1, ',', '.') ?>%</p>
            <div class="progress-track" role="progressbar" aria-label="Progres target tabungan" aria-valuenow="<?= (int) round($progres) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-fill" style="width: <?= e($progres) ?>%"></div></div>
            <p class="stat-note">Sisa <?= e(rupiah($sisaTarget)) ?> lagi</p>
        </article>
        <article class="stat-card">
            <div class="stat-heading"><span class="stat-icon purple">🕒</span><span>Estimasi Waktu</span></div>
            <p class="stat-value stat-value-sm"><?php if ($sisaTarget <= 0): ?>Target tercapai 🎉<?php elseif ($estimasiHari !== null): ?><?= number_format($estimasiHari, 0, ',', '.') ?> hari lagi<?php else: ?>Belum tersedia<?php endif; ?></p>
            <p class="stat-note"><span class="status-pill <?= $statusTarget === 'On Track' || $statusTarget === 'Target tercapai' ? 'status-good' : 'status-neutral' ?>"><?= e($statusTarget) ?></span></p>
        </article>
    </section>

    <section class="mini-summary">
        <div><span class="mini-dot dot-green"></span><span>Total uang (kas + tabungan)</span><strong><?= e(rupiah($totalUang)) ?></strong></div>
        <p>Setor tabungan mengurangi kas, tetapi menambah tabungan. Total uang milikmu tidak berkurang karena pemindahan tersebut.</p>
    </section>

    <section class="dashboard-grid">
        <article class="panel" id="form-transaksi">
            <div class="panel-heading">
                <div><p class="eyebrow">CATAT ARUS UANG</p><h2><?= $editData ? 'Edit Transaksi' : 'Tambah Transaksi' ?></h2></div>
                <span class="heading-icon">＋</span>
            </div>
            <form method="post" action="dashboard.php" class="form-stack" id="transactionForm">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="save_transaction">
                <input type="hidden" name="id" value="<?= e($editData['id'] ?? '') ?>">
                <label for="tipe">Jenis Transaksi</label>
                <select name="tipe" id="tipe" required>
                    <option value="pemasukan" <?= $nilaiTipeForm === 'pemasukan' ? 'selected' : '' ?>>Pemasukan</option>
                    <option value="pengeluaran" <?= $nilaiTipeForm === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option>
                    <option value="setor_tabungan" <?= $nilaiTipeForm === 'setor_tabungan' ? 'selected' : '' ?>>Setor Tabungan</option>
                </select>
                <label for="tanggal">Tanggal</label>
                <input type="date" name="tanggal" id="tanggal" value="<?= e($nilaiTanggalForm) ?>" required>
                <label for="nominal">Nominal (Rp)</label>
                <input type="number" name="nominal" id="nominal" min="1" step="1" value="<?= e($nilaiNominalForm) ?>" placeholder="Contoh: 50000" required>
                <label for="keterangan">Keterangan</label>
                <input type="text" name="keterangan" id="keterangan" maxlength="255" value="<?= e($nilaiKeteranganForm) ?>" placeholder="Contoh: Nabung buat laptop" required>
                <p class="form-hint" id="typeHint">Pemasukan akan menambah saldo kas.</p>
                <button type="submit" class="button button-primary button-full"><?= $editData ? 'Simpan Perubahan' : 'Simpan Transaksi' ?></button>
                <?php if ($editData): ?><a href="dashboard.php#form-transaksi" class="cancel-link">Batal mengedit</a><?php endif; ?>
            </form>
        </article>

        <article class="panel journal-panel" id="jurnal">
            <div class="panel-heading journal-heading">
                <div><p class="eyebrow">AKTIVITAS TERBARU</p><h2>Jurnal Transaksi</h2></div>
                <a class="text-link" href="riwayat.php">Lihat semua <span aria-hidden="true">→</span></a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>No</th><th>Tanggal</th><th>Jenis</th><th>Nominal</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if (!$transaksiTerbaru): ?>
                        <tr><td colspan="6" class="empty-state"><span>🧾</span><strong>Belum ada transaksi</strong><br>Catat pemasukan, pengeluaran, atau setoran pertamamu.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transaksiTerbaru as $index => $trx): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e(tanggalIndonesia($trx['tanggal'])) ?></td>
                                <td><span class="type-badge type-<?= e($trx['tipe']) ?>"><?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : ($trx['tipe'] === 'pengeluaran' ? 'Pengeluaran' : 'Setor Tabungan') ?></span></td>
                                <td class="amount-cell <?= $trx['tipe'] === 'pemasukan' ? 'amount-positive' : 'amount-negative' ?>"><?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?> <?= e(rupiah($trx['nominal'])) ?></td>
                                <td><?= e($trx['keterangan']) ?></td>
                                <td><div class="action-buttons"><a class="button button-light button-small" href="dashboard.php?edit=<?= (int) $trx['id'] ?>#form-transaksi">Edit</a><form method="post" action="dashboard.php" class="inline-form" data-confirm="Hapus transaksi ini? Saldo akan dihitung ulang."><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="delete_transaction"><input type="hidden" name="id" value="<?= (int) $trx['id'] ?>"><button type="submit" class="button button-danger-light button-small">Hapus</button></form></div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="journal-footer"><span>Menampilkan <?= count($transaksiTerbaru) ?> transaksi terbaru</span><a href="riwayat.php" class="button button-outline">Buka Riwayat</a></div>
        </article>
    </section>
    <footer class="site-footer">Celengan · Catat hari ini, capai tujuan nanti.</footer>
</main>

<dialog class="modal" id="targetDialog" aria-labelledby="targetTitle">
    <form method="post" action="dashboard.php" class="modal-form">
        <div class="modal-heading"><div><p class="eyebrow">RENCANA KEUANGAN</p><h2 id="targetTitle">Atur Target Tabungan</h2></div><button type="button" class="icon-button" id="closeTarget" aria-label="Tutup">×</button></div>
        <p class="modal-copy">Tentukan jumlah tabungan yang ingin kamu capai dan tanggal targetnya.</p>
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="save_goal">
        <label for="target_nominal">Target Tabungan (Rp)</label>
        <input type="number" name="target_nominal" id="target_nominal" min="1" step="1" value="<?= e((float) $targetNominal) ?>" required>
        <label for="target_tanggal">Target Waktu</label>
        <input type="date" name="target_tanggal" id="target_tanggal" min="<?= e(date('Y-m-d')) ?>" value="<?= e($target['target_tanggal']) ?>" required>
        <div class="modal-actions"><button type="button" class="button button-light" id="cancelTarget">Batal</button><button type="submit" class="button button-primary">Simpan Target</button></div>
    </form>
</dialog>
</body>
</html>
