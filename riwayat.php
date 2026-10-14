<?php
require __DIR__ . '/auth.php';
require_login();
$userId = (int) $_SESSION['user_id'];

function e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function rupiah($number) { return 'Rp ' . number_format((float) $number, 0, ',', '.'); }
function tanggalIndonesia($date) {
    $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

$filterTipe = $_GET['tipe'] ?? 'semua';
$filterTanggal = $_GET['tanggal'] ?? '';
$cari = trim((string) ($_GET['cari'] ?? ''));
$urutan = $_GET['urutan'] ?? 'terbaru';
$where = ['user_id = :user_id'];
$params = [':user_id' => $userId];
if (in_array($filterTipe, ['pemasukan', 'pengeluaran', 'setor_tabungan'], true)) {
    $where[] = 'tipe = :tipe';
    $params[':tipe'] = $filterTipe;
} else { $filterTipe = 'semua'; }
if (is_string($filterTanggal) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTanggal)) {
    $parsed = DateTime::createFromFormat('!Y-m-d', $filterTanggal);
    if ($parsed && $parsed->format('Y-m-d') === $filterTanggal) {
        $where[] = 'tanggal = :tanggal';
        $params[':tanggal'] = $filterTanggal;
    } else { $filterTanggal = ''; }
} else { $filterTanggal = ''; }
if ($cari !== '') { $where[] = 'keterangan LIKE :cari'; $params[':cari'] = '%' . $cari . '%'; }
$orderSql = $urutan === 'terlama' ? ' ORDER BY tanggal ASC, id ASC' : ' ORDER BY tanggal DESC, id DESC';
if (!in_array($urutan, ['terbaru', 'terlama'], true)) $urutan = 'terbaru';
$stmt = $pdo->prepare('SELECT id, tanggal, tipe, nominal, keterangan FROM transaksi WHERE ' . implode(' AND ', $where) . $orderSql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Riwayat | Celengan</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
<header class="topbar">
  <a class="brand" href="dashboard.php"><span class="brand-icon">🏦</span><span>Celengan</span></a>
  <nav class="nav-links" aria-label="Navigasi utama"><a href="dashboard.php">Dashboard</a><a class="active" href="riwayat.php">Riwayat</a></nav>
  <div class="user-menu"><span class="user-avatar">👤</span><span><?= e($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengguna') ?></span><form method="post" action="logout.php" class="logout-form"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><button type="submit" class="logout-button">Logout</button></form></div>
</header>
<main class="container history-container">
  <section class="page-heading"><p class="eyebrow">CATATAN KEUANGAN</p><h1>Riwayat Transaksi</h1><p>Lihat dan kelola seluruh catatan keuanganmu.</p></section>
  <section class="panel filter-panel">
    <form method="get" class="filter-grid">
      <div><label for="filterTipe">Tipe transaksi</label><select id="filterTipe" name="tipe"><option value="semua" <?= $filterTipe === 'semua' ? 'selected' : '' ?>>Semua</option><option value="pemasukan" <?= $filterTipe === 'pemasukan' ? 'selected' : '' ?>>Pemasukan</option><option value="pengeluaran" <?= $filterTipe === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option><option value="setor_tabungan" <?= $filterTipe === 'setor_tabungan' ? 'selected' : '' ?>>Setor Tabungan</option></select></div>
      <div><label for="filterTanggal">Tanggal</label><input type="date" id="filterTanggal" name="tanggal" value="<?= e($filterTanggal) ?>"></div>
      <div><label for="urutan">Urutan</label><select id="urutan" name="urutan"><option value="terbaru" <?= $urutan === 'terbaru' ? 'selected' : '' ?>>Terbaru</option><option value="terlama" <?= $urutan === 'terlama' ? 'selected' : '' ?>>Terlama</option></select></div>
      <div><label for="cari">Pencarian</label><input type="search" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Cari keterangan..."></div>
      <div class="filter-button"><button class="button button-primary" type="submit">Terapkan Filter</button><a class="button button-light" href="riwayat.php">Reset</a></div>
    </form>
  </section>
  <section class="panel journal-panel history-table-panel"><div class="table-wrap"><table><thead><tr><th>No</th><th>Tanggal</th><th>Tipe</th><th>Nominal</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
  <?php if (!$rows): ?><tr><td colspan="6" class="empty-state"><span>🔎</span><strong>Transaksi tidak ditemukan</strong><br>Coba ubah filter pencarianmu.</td></tr>
  <?php else: foreach ($rows as $i => $trx): ?><tr><td><?= $i + 1 ?></td><td><?= e(tanggalIndonesia($trx['tanggal'])) ?></td><td><span class="type-badge type-<?= e($trx['tipe']) ?>"><?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : ($trx['tipe'] === 'pengeluaran' ? 'Pengeluaran' : 'Setor Tabungan') ?></span></td><td class="amount-cell <?= $trx['tipe'] === 'pemasukan' ? 'amount-positive' : 'amount-negative' ?>"><?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?> <?= e(rupiah($trx['nominal'])) ?></td><td><?= e($trx['keterangan']) ?></td><td><a class="button button-light button-small" href="dashboard.php?edit=<?= (int) $trx['id'] ?>#form-transaksi">Edit</a></td></tr><?php endforeach; endif; ?>
  </tbody></table></div><div class="journal-footer"><span><?= count($rows) ?> transaksi ditemukan</span><a class="button button-outline" href="dashboard.php">Kembali ke Dashboard</a></div></section>
  <footer class="site-footer">Celengan · Catat hari ini, capai tujuan nanti.</footer>
</main></body></html>
