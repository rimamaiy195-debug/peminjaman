
<?php
session_start();
include 'koneksi.php';
include 'catat_aktivitas.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$level = $_SESSION['level'];
$pesan = "";

// PEMINJAM MENGAJUKAN PENGEMBALIAN
if (isset($_POST['ajukan']) && $level == 'peminjam') {
    $peminjaman_id = (int) $_POST['peminjaman_id'];

    $cek = mysqli_query($koneksi, "SELECT * FROM peminjaman
        WHERE peminjaman_id='$peminjaman_id'
        AND user_id='$user_id'
        AND status IN ('disetujui', 'dipinjam')");

    if ($cek && mysqli_num_rows($cek) > 0) {
        $cek_kembali = mysqli_query($koneksi, "SELECT * FROM pengembalian
            WHERE peminjaman_id='$peminjaman_id'");

        if ($cek_kembali && mysqli_num_rows($cek_kembali) == 0) {
            mysqli_begin_transaction($koneksi);

            $berhasil = mysqli_query($koneksi, "INSERT INTO pengembalian
                (peminjaman_id, tanggal_kembali, status)
                VALUES ('$peminjaman_id', NOW(), 'menunggu pemeriksaan')");

            if ($berhasil) {
                $berhasil = mysqli_query($koneksi, "UPDATE peminjaman
                    SET status='dipinjam'
                    WHERE peminjaman_id='$peminjaman_id'");
            }

            if ($berhasil) {
                mysqli_commit($koneksi);

                catatAktivitas(
                    $koneksi,
                    $user_id,
                    'Mengajukan pengembalian',
                    'Mengajukan pengembalian untuk peminjaman ID ' . $peminjaman_id
                );

                header("Location: pengembalian.php");
                exit;
            } else {
                mysqli_rollback($koneksi);
                $pesan = "Gagal mengajukan pengembalian.";
            }
        } else {
            $pesan = "Pengajuan pengembalian sudah ada.";
        }
    } else {
        $pesan = "Peminjaman tidak ditemukan atau belum disetujui.";
    }
}

// ADMIN/PETUGAS MENYELESAIKAN PENGEMBALIAN
if (
    isset($_POST['selesaikan']) &&
    in_array($level, ['admin', 'petugas'])
) {
    $pengembalian_id = (int) $_POST['pengembalian_id'];
    $kondisi = $_POST['kondisi_sesudah'] ?? '';
    $catatan = mysqli_real_escape_string(
        $koneksi,
        $_POST['catatan'] ?? ''
    );

    if (!in_array($kondisi, ['baik', 'rusak ringan', 'rusak berat'])) {
        $pesan = "Kondisi alat tidak valid.";
    } else {
        mysqli_begin_transaction($koneksi);

        $cek = mysqli_query($koneksi, "SELECT * FROM pengembalian
            WHERE pengembalian_id='$pengembalian_id'
            AND status='menunggu pemeriksaan'
            FOR UPDATE");

        if ($cek && mysqli_num_rows($cek) > 0) {
            $data = mysqli_fetch_assoc($cek);
            $peminjaman_id = (int) $data['peminjaman_id'];

            $berhasil = mysqli_query($koneksi, "UPDATE pengembalian SET
                petugas_id='$user_id',
                kondisi_sesudah='$kondisi',
                catatan='$catatan',
                status='dikembalikan'
                WHERE pengembalian_id='$pengembalian_id'
                AND status='menunggu pemeriksaan'");

            if ($berhasil && mysqli_affected_rows($koneksi) == 1) {
                $berhasil = mysqli_query($koneksi, "UPDATE peminjaman
                    SET status='selesai'
                    WHERE peminjaman_id='$peminjaman_id'
                    AND status IN ('disetujui', 'dipinjam')");

                if ($berhasil && mysqli_affected_rows($koneksi) == 1) {
                    $detail = mysqli_query($koneksi, "SELECT * FROM detail_peminjaman
                        WHERE peminjaman_id='$peminjaman_id'");

                    if (!$detail || mysqli_num_rows($detail) == 0) {
                        $berhasil = false;
                    }

                    while ($berhasil && $d = mysqli_fetch_assoc($detail)) {
                        $alat_id = (int) $d['alat_id'];
                        $jumlah = (int) $d['jumlah'];

                        if ($kondisi != 'rusak berat') {
                            $berhasil = mysqli_query($koneksi, "UPDATE alat
                                SET stok = stok + $jumlah
                                WHERE alat_id='$alat_id'");
                        } else {
                            $berhasil = mysqli_query($koneksi, "UPDATE alat
                                SET kondisi='rusak berat'
                                WHERE alat_id='$alat_id'");
                        }
                    }
                } else {
                    $berhasil = false;
                }
            } else {
                $berhasil = false;
            }

            if ($berhasil) {
                mysqli_commit($koneksi);

                catatAktivitas(
                    $koneksi,
                    $user_id,
                    'Menyelesaikan pengembalian',
                    'Menyelesaikan pengembalian ID ' . $pengembalian_id .
                    ' untuk peminjaman ID ' . $peminjaman_id .
                    '. Kondisi alat: ' . $kondisi
                );

                header("Location: pengembalian.php");
                exit;
            } else {
                mysqli_rollback($koneksi);
                $pesan = "Gagal menyelesaikan pengembalian. Periksa kembali data alat dan peminjaman.";
            }
        } else {
            mysqli_rollback($koneksi);
            $pesan = "Data pengembalian tidak ditemukan atau sudah diproses.";
        }
    }
}

// DAFTAR PEMINJAMAN YANG BISA DIAJUKAN PENGEMBALIANNYA
if ($level == 'peminjam') {
    $daftar_pinjam = mysqli_query($koneksi, "SELECT p.*
        FROM peminjaman p
        LEFT JOIN pengembalian k ON p.peminjaman_id = k.peminjaman_id
        WHERE p.user_id='$user_id'
        AND p.status IN ('disetujui', 'dipinjam')
        AND k.pengembalian_id IS NULL
        ORDER BY p.peminjaman_id DESC");
} else {
    $daftar_pinjam = mysqli_query($koneksi, "SELECT p.*
        FROM peminjaman p
        LEFT JOIN pengembalian k ON p.peminjaman_id = k.peminjaman_id
        WHERE p.status IN ('disetujui', 'dipinjam')
        AND k.pengembalian_id IS NULL
        ORDER BY p.peminjaman_id DESC");
}

// DATA TABEL PENGEMBALIAN
if ($level == 'peminjam') {
    $data_kembali = mysqli_query($koneksi, "SELECT k.*, p.user_id, p.tanggal_pinjam
        FROM pengembalian k
        JOIN peminjaman p ON k.peminjaman_id = p.peminjaman_id
        WHERE p.user_id='$user_id'
        ORDER BY k.pengembalian_id DESC");
} else {
    $data_kembali = mysqli_query($koneksi, "SELECT k.*, p.user_id,
        p.tanggal_pinjam, u.nama
        FROM pengembalian k
        JOIN peminjaman p ON k.peminjaman_id = p.peminjaman_id
        JOIN user u ON p.user_id = u.user_id
        ORDER BY k.pengembalian_id DESC");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengembalian</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            Peminjaman Sarpras
        </a>

        <div class="d-flex align-items-center gap-3">
            <span class="text-white">
                <?= htmlspecialchars($_SESSION['nama']) ?>
            </span>

            <a href="logout.php" class="btn btn-outline-light btn-sm"
               onclick="return confirm('Yakin ingin keluar?')">
                Logout
            </a>
        </div>
    </div>
</nav>

<div class="container main-content">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
        <div>
            <h2>Data Pengembalian</h2>
            <p class="page-subtitle mb-0">
                <?= $level == 'peminjam'
                    ? 'Ajukan pengembalian dan pantau statusnya.'
                    : 'Periksa kondisi alat dan selesaikan pengembalian.' ?>
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-secondary">
            Kembali ke Dashboard
        </a>
    </div>

    <?php if ($pesan != "") { ?>
        <div class="alert alert-warning mt-3">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php } ?>

    <!-- FORM AJUKAN PENGEMBALIAN -->
    <?php if ($level == 'peminjam') { ?>
        <div class="card mt-4 mb-4">
            <div class="card-header">Ajukan Pengembalian</div>

            <div class="card-body">
                <?php if ($daftar_pinjam && mysqli_num_rows($daftar_pinjam) > 0) { ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Pilih Peminjaman</label>

                            <select name="peminjaman_id" class="form-select" required>
                                <option value="">-- Pilih Peminjaman --</option>

                                <?php while ($p = mysqli_fetch_assoc($daftar_pinjam)) { ?>
                                    <option value="<?= $p['peminjaman_id'] ?>">
                                        Peminjaman #<?= $p['peminjaman_id'] ?>
                                        - <?= htmlspecialchars(
                                            date('d-m-Y H:i', strtotime($p['tanggal_pinjam']))
                                        ) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <button type="submit" name="ajukan" class="btn btn-primary">
                            Ajukan Pengembalian
                        </button>
                    </form>
                <?php } else { ?>
                    <p class="text-secondary mb-0">
                        Tidak ada peminjaman yang bisa diajukan pengembaliannya.
                    </p>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <!-- RIWAYAT PENGEMBALIAN -->
    <div class="card">
        <div class="card-header">Riwayat Pengembalian</div>

        <div class="table-responsive">
            <input type="text" id="search" placeholder="🔍 Cari pengembalian..." onkeyup="searchTable()">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>ID Peminjaman</th>

                        <?php if ($level != 'peminjam') { ?>
                            <th>Nama Peminjam</th>
                        <?php } ?>

                        <th>Tanggal dan Jam Kembali</th>
                        <th>Kondisi Sesudah</th>
                        <th>Status</th>
                        <th>Catatan</th>

                        <?php if (in_array($level, ['admin', 'petugas'])) { ?>
                            <th>Aksi</th>
                        <?php } ?>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $no = 1;

                    while ($k = mysqli_fetch_assoc($data_kembali)) {
                        $warna = 'bg-secondary';

                        if ($k['status'] == 'menunggu pemeriksaan') {
                            $warna = 'bg-warning text-dark';
                        } elseif ($k['status'] == 'dikembalikan') {
                            $warna = 'bg-success';
                        }
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>#<?= (int) $k['peminjaman_id'] ?></td>

                            <?php if ($level != 'peminjam') { ?>
                                <td><?= htmlspecialchars($k['nama']) ?></td>
                            <?php } ?>

                            <td>
                                <?= !empty($k['tanggal_kembali'])
                                    ? date('d-m-Y H:i', strtotime($k['tanggal_kembali']))
                                    : '-' ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($k['kondisi_sesudah'] ?? '-') ?>
                            </td>

                            <td>
                                <span class="badge <?= $warna ?>">
                                    <?= htmlspecialchars(ucfirst($k['status'])) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($k['catatan'] ?? '-') ?>
                            </td>

                            <?php if (in_array($level, ['admin', 'petugas'])) { ?>
                                <td>
                                    <?php if ($k['status'] == 'menunggu pemeriksaan') { ?>
                                        <form method="POST">
                                            <input type="hidden" name="pengembalian_id"
                                                   value="<?= $k['pengembalian_id'] ?>">

                                            <select name="kondisi_sesudah"
                                                    class="form-select form-select-sm mb-2"
                                                    required>
                                                <option value="baik">Baik</option>
                                                <option value="rusak ringan">Rusak Ringan</option>
                                                <option value="rusak berat">Rusak Berat</option>
                                            </select>

                                            <input type="text" name="catatan"
                                                   class="form-control form-control-sm mb-2"
                                                   placeholder="Catatan (opsional)">

                                            <button type="submit" name="selesaikan"
                                                    class="btn btn-success btn-sm"
                                                    onclick="return confirm('Selesaikan pengembalian ini?')">
                                                Selesaikan
                                            </button>
                                        </form>
                                    <?php } else { ?>
                                        <span class="text-secondary">-</span>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>

                    <?php if (!$data_kembali || mysqli_num_rows($data_kembali) == 0) { ?>
                        <tr>
                            <td colspan="<?= in_array($level, ['admin', 'petugas']) ? 8 : 6 ?>"
                                class="text-center py-4">
                                Belum ada riwayat pengembalian.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
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
