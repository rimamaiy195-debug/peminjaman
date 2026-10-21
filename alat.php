
<?php
session_start();
include 'koneksi.php';
include 'catat_aktivitas.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$admin = $_SESSION['level'] == 'admin';
$aksi = $_GET['aksi'] ?? '';
$pesan = "";

// TAMBAH ALAT
if (isset($_POST['tambah']) && $admin) {
    $kategori_id = (int) $_POST['kategori_id'];
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_alat']);
    $kode = mysqli_real_escape_string($koneksi, $_POST['kode_alat']);
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $stok = (int) $_POST['stok'];
    $kondisi = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    $lokasi = mysqli_real_escape_string($koneksi, $_POST['lokasi']);

    $query = "INSERT INTO alat(kategori_id, nama_alat, kode_alat, deskripsi, stok, kondisi, lokasi)VALUES('$kategori_id', '$nama', '$kode', '$deskripsi', '$stok', '$kondisi', '$lokasi')";

    if (mysqli_query($koneksi, $query)) {
        $alat_id = mysqli_insert_id($koneksi);

        catatAktivitas($koneksi, $_SESSION['user_id'], 'Menambahkan alat', "Menambahkan alat $nama dengan kode $kode (ID: $alat_id)");
        header("Location: alat.php");
        exit;
    } else {
        $pesan = "Gagal menambahkan alat. Periksa kode alat dan kategori!";
        $aksi = 'tambah';
    }
}

// EDIT ALAT
if (isset($_POST['update']) && $admin) {
    $id = (int) $_POST['alat_id'];
    $kategori_id = (int) $_POST['kategori_id'];
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_alat']);
    $kode = mysqli_real_escape_string($koneksi, $_POST['kode_alat']);
    $deskripsi = mysqli_real_escape_string($koneksi, $_POST['deskripsi']);
    $stok = (int) $_POST['stok'];
    $kondisi = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    $lokasi = mysqli_real_escape_string($koneksi, $_POST['lokasi']);

    $query = "UPDATE alat SET kategori_id='$kategori_id', nama_alat='$nama', kode_alat='$kode', deskripsi='$deskripsi', stok='$stok', kondisi='$kondisi', lokasi='$lokasi' WHERE alat_id='$id'";

    if (mysqli_query($koneksi, $query)) {
        catatAktivitas($koneksi, $_SESSION['user_id'], 'Mengedit alat', "Mengubah data alat $nama dengan kode $kode (ID: $id)" );
        header("Location: alat.php");
        exit;
    } else {
        $pesan = "Gagal mengubah data alat!";
        $aksi = 'edit';
        $_GET['id'] = $id;
    }
}

// HAPUS ALAT
if (isset($_GET['hapus']) && $admin) {
    $id = (int) $_GET['hapus'];

    // Ambil nama alat sebelum dihapus
    $data_hapus = mysqli_query($koneksi, "SELECT nama_alat, kode_alat FROM alat WHERE alat_id='$id'");
    $alat_hapus = mysqli_fetch_assoc($data_hapus);

    if (mysqli_query($koneksi, "DELETE FROM alat WHERE alat_id='$id'")) {
        if ($alat_hapus) {
            catatAktivitas($koneksi, $_SESSION['user_id'], 'Menghapus alat', "Menghapus alat {$alat_hapus['nama_alat']} dengan kode {$alat_hapus['kode_alat']} (ID: $id)");
        }
        header("Location: alat.php");
        exit;
    } else {
        $pesan = "Alat tidak bisa dihapus karena sudah tercatat dalam peminjaman!";
    }
}

// DATA ALAT UNTUK EDIT
$data_edit = null;

if ($aksi == 'edit' && $admin && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $hasil = mysqli_query($koneksi, "SELECT * FROM alat WHERE alat_id='$id'");
    $data_edit = mysqli_fetch_assoc($hasil);

    if (!$data_edit) {
        header("Location: alat.php");
        exit;
    }
}

// DATA KATEGORI
$kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// DATA ALAT
$alat = mysqli_query($koneksi, "SELECT alat.*, kategori.nama_kategori FROM alat LEFT JOIN kategori ON alat.kategori_id = kategori.kategori_id ORDER BY alat.alat_id DESC");
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Alat | Peminjaman Sekolah</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

<nav class="navbar navbar-dark">
    <div class="container">
        <a href="dashboard.php" class="navbar-brand">
            ▦ Peminjaman Alat
        </a>

        <div class="d-flex align-items-center gap-3">
            <span class="text-white">
                <?= htmlspecialchars($_SESSION['nama']) ?>
            </span>
            <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container main-content">

<?php if ($admin && ($aksi == 'tambah' || $aksi == 'edit')) { ?>

    <!-- FORM TAMBAH / EDIT -->
    <div class="mb-4">
        <a href="alat.php" class="text-decoration-none small">
            ← Kembali ke Data Alat
        </a>

        <h2 class="mt-3">
            <?= $aksi == 'tambah' ? 'Tambah Alat Baru' : 'Edit Data Alat' ?>
        </h2>

        <p class="page-subtitle">
            Lengkapi informasi alat sekolah di bawah ini.
        </p>
    </div>

    <?php if ($pesan != "") { ?>
        <div class="alert alert-warning">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php } ?>

    <div class="card">
        <div class="card-header">
            Informasi Alat
        </div>

        <div class="card-body">
            <form method="POST">

                <?php if ($aksi == 'edit') { ?>
                    <input type="hidden" name="alat_id"
                        value="<?= $data_edit['alat_id'] ?>">
                <?php } ?>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Nama Alat</label>
                        <input type="text" name="nama_alat" class="form-control" value="<?= htmlspecialchars($data_edit['nama_alat'] ?? $_POST['nama_alat'] ?? '') ?>" placeholder="Contoh: Proyektor Epson" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Kode Alat</label>
                        <input type="text" name="kode_alat" class="form-control"
                            value="<?= htmlspecialchars($data_edit['kode_alat'] ?? $_POST['kode_alat'] ?? '') ?>" placeholder="Contoh: ALT001" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>

                            <?php
                            $kategori_terpilih = $data_edit['kategori_id'] ?? $_POST['kategori_id'] ?? '';

                            while ($k = mysqli_fetch_assoc($kategori)) {
                            ?>
                                <option value="<?= $k['kategori_id'] ?>"
                                    <?= $kategori_terpilih == $k['kategori_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kategori']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Lokasi</label>
                        <input type="text" name="lokasi" class="form-control" value="<?= htmlspecialchars($data_edit['lokasi'] ?? $_POST['lokasi'] ?? '') ?>" placeholder="Contoh: Ruang Multimedia" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Stok</label>
                        <input type="number" name="stok" class="form-control" min="0" value="<?= htmlspecialchars($data_edit['stok'] ?? $_POST['stok'] ?? '0') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Kondisi Alat</label>

                        <?php $kondisi_terpilih = $data_edit['kondisi'] ?? $_POST['kondisi'] ?? 'baik'; ?>

                        <select name="kondisi" class="form-select" required>
                            <option value="baik" <?= $kondisi_terpilih == 'baik' ? 'selected' : '' ?>>
                                Baik
                            </option>
                            <option value="rusak ringan" <?= $kondisi_terpilih == 'rusak ringan' ? 'selected' : '' ?>>
                                Rusak Ringan
                            </option>
                            <option value="rusak berat" <?= $kondisi_terpilih == 'rusak berat' ? 'selected' : '' ?>>
                                Rusak Berat
                            </option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Deskripsi Alat</label>
                        <textarea name="deskripsi" class="form-control" rows="4"
                            placeholder="Tambahkan keterangan alat jika diperlukan"><?= htmlspecialchars($data_edit['deskripsi'] ?? $_POST['deskripsi'] ?? '') ?></textarea>
                    </div>

                </div>

                <hr class="my-4">

                <?php if ($aksi == 'tambah') { ?>
                    <button type="submit" name="tambah" class="btn btn-primary">
                        Simpan Alat
                    </button>
                <?php } else { ?>
                    <button type="submit" name="update" class="btn btn-primary">
                        Simpan Perubahan
                    </button>
                <?php } ?>

                <a href="alat.php" class="btn btn-secondary">Batal</a>

            </form>
        </div>
    </div>

<?php } else { ?>

    <!-- TABEL DATA ALAT -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2>Data Alat</h2>
            <p class="page-subtitle mb-0">
                Informasi inventaris sarana dan prasarana sekolah.
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-secondary">
            ← Kembali ke Dashboard
        </a>
    </div>

    <?php if ($pesan != "") { ?>
        <div class="alert alert-warning">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php } ?>

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="mb-1">Daftar Inventaris</h5>
                <small class="text-secondary">Data alat yang tercatat dalam sistem</small>
            </div>

            <?php if ($admin) { ?>
                <a href="alat.php?aksi=tambah" class="btn btn-primary">
                    + Tambah Alat
                </a>
            <?php } ?>
        </div>

        <div class="table-responsive">
            <input type="text" id="search" placeholder="🔍 Cari alat..." onkeyup="searchTable()">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Alat</th>
                        <th>Kode</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Kondisi</th>
                        <th>Lokasi</th>
                        <?php if ($admin) { ?>
                            <th>Aksi</th>
                        <?php } ?>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $no = 1;

                    while ($a = mysqli_fetch_assoc($alat)) {
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($a['nama_alat']) ?></td>
                        <td>
                            <span class="text-secondary">
                                <?= htmlspecialchars($a['kode_alat']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($a['nama_kategori'] ?? '-') ?></td>
                        <td><span class="fw-bold"><?= $a['stok'] ?></span></td>

                        <td>
                            <?php
                            if ($a['kondisi'] == 'baik') {
                                $warna = 'success';
                            } elseif ($a['kondisi'] == 'rusak ringan') {
                                $warna = 'warning text-dark';
                            } else {
                                $warna = 'danger';
                            }
                            ?>

                            <span class="badge bg-<?= $warna ?>">
                                <?= htmlspecialchars(ucfirst($a['kondisi'])) ?>
                            </span>
                        </td>

                        <td><?= htmlspecialchars($a['lokasi']) ?></td>

                        <?php if ($admin) { ?>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="alat.php?aksi=edit&id=<?= $a['alat_id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="alat.php?hapus=<?= $a['alat_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus alat ini?')">Hapus</a>
                                </div>
                            </td>
                        <?php } ?>
                    </tr>
                    <?php } ?>

                    <?php if (mysqli_num_rows($alat) == 0) { ?>
                    <tr>
                        <td colspan="<?= $admin ? 8 : 7 ?>" class="text-center py-5">
                            <div class="fs-2 mb-2">📦</div>
                            <div class="fw-semibold">Belum ada data alat</div>
                            <small class="text-secondary">
                                Data alat akan muncul di sini setelah ditambahkan.
                            </small>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

<?php } ?>

    <div class="text-center mt-5 text-secondary">
        <small>© <?= date('Y') ?> Sistem Peminjaman Sarana dan Prasarana Sekolah</small>
    </div>

</div>

<script>
function searchTable() {
    let input = document
        .getElementById("search")
        .value
        .toLowerCase();
    let rows = document.querySelectorAll("tbody tr");
    rows.forEach(function(row) {
        let text = row.innerText.toLowerCase();
        if (text.includes(input)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
</script>

</body>
</html>
