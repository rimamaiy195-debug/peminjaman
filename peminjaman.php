
<?php
session_start();
include 'koneksi.php';
include 'catat_aktivitas.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$level = $_SESSION['level'];
$user_id = (int) $_SESSION['user_id'];
$aksi = $_GET['aksi'] ?? '';
$pesan = "";

// =====================================
// PROSES AJUKAN PEMINJAMAN MULTI ALAT
// =====================================
if (isset($_POST['ajukan']) && $level == 'peminjam') {
    $alat_ids = $_POST['alat_id'] ?? [];
    $jumlahs = $_POST['jumlah'] ?? [];

    $tanggal_pinjam = str_replace('T', ' ', $_POST['tanggal_pinjam'] ?? '');
    $tanggal_kembali = str_replace('T', ' ', $_POST['tanggal_kembali'] ?? '');
    $keperluan = trim($_POST['keperluan'] ?? '');

    $tanggal_pinjam_db = mysqli_real_escape_string($koneksi, $tanggal_pinjam);
    $tanggal_kembali_db = mysqli_real_escape_string($koneksi, $tanggal_kembali);
    $keperluan_db = mysqli_real_escape_string($koneksi, $keperluan);

    $barang = [];
    $alat_unik = true;
    $total_barang = 0;

    if (!is_array($alat_ids) || !is_array($jumlahs) ||
        count($alat_ids) == 0 || count($alat_ids) != count($jumlahs)) {
        $pesan = "Pilih minimal satu alat untuk dipinjam!";
    } else {
        foreach ($alat_ids as $i => $id_alat) {
            $id_alat = (int) $id_alat;
            $jumlah = (int) ($jumlahs[$i] ?? 0);

            if ($id_alat < 1 || $jumlah < 1) {
                $pesan = "Pilih alat dan masukkan jumlah yang valid!";
                break;
            }

            // Cegah alat yang sama dimasukkan dua kali.
            if (isset($barang[$id_alat])) {
                $alat_unik = false;
                break;
            }

            $barang[$id_alat] = $jumlah;
            $total_barang += $jumlah;
        }

        if (!$alat_unik) {
            $pesan = "Alat yang sama tidak boleh dipilih dua kali. Ubah jumlahnya pada baris alat tersebut.";
        }
    }

    if ($pesan == "") {
        foreach ($barang as $id_alat => $jumlah) {
            $cek = mysqli_query($koneksi, "SELECT alat_id, nama_alat, stok FROM alat WHERE alat_id='$id_alat' AND stok > 0");

            $alat = $cek ? mysqli_fetch_assoc($cek) : null;

            if (!$alat || $alat['stok'] < $jumlah) {
                $nama = $alat ? $alat['nama_alat'] : "Alat ID $id_alat";
                $pesan = "Stok $nama tidak tersedia atau tidak mencukupi!";
                break;
            }
        }
    }

    if ($pesan == "" && (
        !strtotime($tanggal_pinjam) ||
        !strtotime($tanggal_kembali) ||
        strtotime($tanggal_kembali) <= strtotime($tanggal_pinjam)
    )) {
        $pesan = "Tanggal kembali harus lebih lambat dari tanggal pinjam!";
    }

    if ($pesan == "" && $keperluan == '') {
        $pesan = "Keperluan peminjaman wajib diisi!";
    }

    if ($pesan != "") {
        $aksi = 'tambah';
    } else {
        mysqli_begin_transaction($koneksi);

        $berhasil = mysqli_query($koneksi, "INSERT INTO peminjaman (user_id, tanggal_pengajuan, tanggal_pinjam, tanggal_rencana_kembali, status, keperluan)VALUES('$user_id', NOW(), '$tanggal_pinjam_db', '$tanggal_kembali_db', 'menunggu', '$keperluan_db')");

        if ($berhasil) {
            $peminjaman_id = mysqli_insert_id($koneksi);

            foreach ($barang as $id_alat => $jumlah) {
                $berhasil = mysqli_query($koneksi, "INSERT INTO detail_peminjaman (peminjaman_id, alat_id, jumlah) VALUES ('$peminjaman_id', '$id_alat', '$jumlah')");

                if (!$berhasil) {
                    break;
                }
            }
        }

        if ($berhasil) {
            mysqli_commit($koneksi);

            catatAktivitas($koneksi, $user_id, 'Mengajukan peminjaman', "Mengajukan $total_barang barang dari " . count($barang) . " jenis alat. ID peminjaman: $peminjaman_id");
            header("Location: peminjaman.php");
            exit;
        } else {
            mysqli_rollback($koneksi);
            $pesan = "Pengajuan gagal disimpan. Periksa struktur database!";
            $aksi = 'tambah';
        }
    }
}

// =====================================
// PROSES SETUJUI / TOLAK PEMINJAMAN
// =====================================
if (
    isset($_GET['proses'], $_GET['id']) &&
    in_array($level, ['admin', 'petugas'])
) {
    $id = (int) $_GET['id'];
    $status = $_GET['proses'];

    if (in_array($status, ['disetujui', 'ditolak'])) {
        $cek = mysqli_query($koneksi, "SELECT * FROM peminjaman WHERE peminjaman_id='$id' AND status='menunggu'");

        if ($cek && mysqli_num_rows($cek) > 0) {
            if ($status == 'disetujui') {
                mysqli_begin_transaction($koneksi);

                $detail = mysqli_query($koneksi, "SELECT alat_id, jumlah FROM detail_peminjaman WHERE peminjaman_id='$id'");

                $cukup = $detail && mysqli_num_rows($detail) > 0;
                $daftar_detail = [];

                while ($cukup && $d = mysqli_fetch_assoc($detail)) {
                    $alat_id = (int) $d['alat_id'];
                    $jumlah = (int) $d['jumlah'];

                    // Kunci stok agar tidak berubah saat diperiksa.
                    $alat = mysqli_query($koneksi, "SELECT stok FROM alat WHERE alat_id='$alat_id' FOR UPDATE");

                    $stok = $alat ? mysqli_fetch_assoc($alat) : null;

                    if (!$stok || $jumlah < 1 || $stok['stok'] < $jumlah) {
                        $cukup = false;
                    } else {
                        $daftar_detail[] = [
                            'alat_id' => $alat_id,
                            'jumlah' => $jumlah
                        ];
                    }
                }

                $berhasil = false;

                if ($cukup) {
                    $berhasil = mysqli_query($koneksi, "UPDATE peminjaman SET status='disetujui', petugas_id='$user_id' WHERE peminjaman_id='$id' AND status='menunggu'");

                    if ($berhasil && mysqli_affected_rows($koneksi) == 1) {
                        foreach ($daftar_detail as $d) {
                            $alat_id = $d['alat_id'];
                            $jumlah = $d['jumlah'];

                            $berhasil = mysqli_query($koneksi, "UPDATE alat SET stok = stok - $jumlah WHERE alat_id='$alat_id' AND stok >= $jumlah");

                            if (!$berhasil || mysqli_affected_rows($koneksi) != 1) {
                                $berhasil = false;
                                break;
                            }
                        }
                    } else {
                        $berhasil = false;
                    }
                }

                if ($berhasil && $cukup) {
                    mysqli_commit($koneksi);

                    catatAktivitas($koneksi, $user_id, 'Menyetujui peminjaman', 'Menyetujui pengajuan ID ' . $id . ' dan memperbarui stok seluruh alat');
                } else {
                    mysqli_rollback($koneksi);
                    $pesan = "Gagal menyetujui. Stok salah satu alat tidak cukup atau detail tidak ditemukan!";
                }
            } else {
                $berhasil = mysqli_query($koneksi, "UPDATE peminjaman SET status='ditolak', petugas_id='$user_id' WHERE peminjaman_id='$id' AND status='menunggu'");

                if ($berhasil && mysqli_affected_rows($koneksi) > 0) {
                    catatAktivitas($koneksi, $user_id, 'Menolak peminjaman', 'Menolak pengajuan peminjaman ID ' . $id);
                }
            }
        }
    }

    if ($pesan == "") {
        header("Location: peminjaman.php");
        exit;
    }
}

// =====================================
// DATA PEMINJAMAN
// =====================================
if ($level == 'peminjam') {
    $data_peminjaman = mysqli_query($koneksi, "SELECT p.*, u.nama FROM peminjaman p JOIN user u ON p.user_id = u.user_id WHERE p.user_id='$user_id' ORDER BY p.peminjaman_id DESC");
} else {
    $data_peminjaman = mysqli_query($koneksi, "SELECT p.*, u.nama FROM peminjaman p JOIN user u ON p.user_id = u.user_id ORDER BY p.peminjaman_id DESC");
}

// Daftar alat untuk dropdown
$daftar_alat = mysqli_query($koneksi, "SELECT * FROM alat WHERE stok > 0 ORDER BY nama_alat ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Peminjaman</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        .select2-container .select2-selection--single {
            height: 38px;
            padding: 4px 8px;
            border: 1px solid #ced4da;
            border-radius: 6px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        .baris-alat {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
        }

        .btn-hapus-alat {
            margin-top: 30px;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Peminjaman Sarpras</a>

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
            <h2>Data Peminjaman</h2>
            <p class="page-subtitle mb-0">
                <?= $level == 'peminjam'
                    ? 'Ajukan peminjaman dan pantau status permohonanmu.'
                    : 'Kelola dan periksa pengajuan peminjaman alat.' ?>
            </p>
        </div>

        <a href="dashboard.php" class="btn btn-secondary">Kembali ke Dashboard</a>
    </div>

    <?php if ($pesan != "") { ?>
        <div class="alert alert-warning mt-3">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php } ?>

    <?php if ($level == 'peminjam' && $aksi != 'tambah') { ?>
        <div class="mt-4 mb-3">
            <a href="peminjaman.php?aksi=tambah" class="btn btn-primary">
                + Ajukan Peminjaman
            </a>
        </div>
    <?php } ?>

    <?php if ($aksi == 'tambah' && $level == 'peminjam') { ?>

        <div class="card mt-4">
            <div class="card-header">Form Pengajuan Peminjaman</div>

            <div class="card-body">
                <form method="POST" id="form-peminjaman">

                    <label class="form-label fw-bold">Daftar Alat yang Dipinjam</label>

                    <div id="daftar-baris-alat">
                        <div class="baris-alat">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-7">
                                    <label class="form-label">Pilih Alat</label>
                                    <select name="alat_id[]" class="form-select pilih-alat" required>
                                        <option value="">Ketik nama alat untuk mencari...</option>
                                        <?php
                                        mysqli_data_seek($daftar_alat, 0);
                                        while ($a = mysqli_fetch_assoc($daftar_alat)) {
                                        ?>
                                            <option value="<?= (int) $a['alat_id'] ?>"
                                                data-stok="<?= (int) $a['stok'] ?>">
                                                <?= htmlspecialchars($a['nama_alat']) ?>
                                                (Stok: <?= (int) $a['stok'] ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Jumlah</label>
                                    <input type="number" name="jumlah[]" class="form-control input-jumlah"
                                           min="1" value="1" required>
                                    <small class="text-secondary info-stok">Pilih alat terlebih dahulu</small>
                                </div>

                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger hapus-alat w-100">
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="tambah-alat" class="btn btn-outline-primary btn-sm mt-1 mb-3">
                        + Tambah Barang Lain
                    </button>

                    <div class="alert alert-info py-2">
                        Total barang: <strong id="total-barang">1</strong>
                        <span class="ms-2">| Jumlah jenis alat: <strong id="total-jenis">1</strong></span>
                    </div>

                    <hr>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal dan Jam Pinjam</label>
                            <input type="datetime-local" name="tanggal_pinjam" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_pinjam'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Rencana Tanggal dan Jam Kembali</label>
                            <input type="datetime-local" name="tanggal_kembali" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_kembali'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="mt-3 mb-4">
                        <label class="form-label">Keperluan</label>
                        <textarea name="keperluan" class="form-control" placeholder="Jelaskan keperluan peminjaman" required><?= htmlspecialchars($_POST['keperluan'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" name="ajukan" class="btn btn-primary">
                        Ajukan Peminjaman
                    </button>

                    <a href="peminjaman.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>

    <?php } else { ?>

        <div class="card mt-4">
            <div class="card-header">Daftar Peminjaman</div>

            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-5">
                        <input type="text" id="cari-peminjaman" class="form-control" placeholder="Cari nama, alat, tanggal, keperluan, atau status...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table" id="tabel-peminjaman">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Peminjam</th>
                                <th>Tanggal Pinjam</th>
                                <th>Rencana Kembali</th>
                                <th>Daftar Alat</th>
                                <th>Jumlah</th>
                                <th>Keperluan</th>
                                <th>Status</th>

                                <?php if (in_array($level, ['admin', 'petugas'])) { ?>
                                    <th>Aksi</th>
                                <?php } ?>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $no = 1;

                            while ($p = mysqli_fetch_assoc($data_peminjaman)) {
                                $id = (int) $p['peminjaman_id'];

                                $detail = mysqli_query($koneksi, "SELECT d.jumlah, a.nama_alat FROM detail_peminjaman d JOIN alat a ON d.alat_id = a.alat_id WHERE d.peminjaman_id='$id'");

                                $daftar = [];
                                $jumlah_total = 0;

                                while ($d = mysqli_fetch_assoc($detail)) {
                                    $daftar[] = htmlspecialchars($d['nama_alat']) . ' (' . (int) $d['jumlah'] . ' buah)';
                                    $jumlah_total += (int) $d['jumlah'];
                                }

                                $warna = 'bg-secondary';

                                if ($p['status'] == 'menunggu') {
                                    $warna = 'bg-warning text-dark';
                                } elseif ($p['status'] == 'disetujui') {
                                    $warna = 'bg-primary';
                                } elseif ($p['status'] == 'ditolak') {
                                    $warna = 'bg-danger';
                                } elseif ($p['status'] == 'dipinjam') {
                                    $warna = 'bg-info text-dark';
                                } elseif ($p['status'] == 'selesai') {
                                    $warna = 'bg-success';
                                }
                            ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($p['nama']) ?></td>

                                    <td>
                                        <?= $p['tanggal_pinjam']
                                            ? date('d-m-Y H:i', strtotime($p['tanggal_pinjam']))
                                            : '-' ?>
                                    </td>

                                    <td>
                                        <?= $p['tanggal_rencana_kembali']
                                            ? date('d-m-Y H:i', strtotime($p['tanggal_rencana_kembali']))
                                            : '-' ?>
                                    </td>

                                    <td><?= !empty($daftar) ? implode('<br>', $daftar) : '-' ?></td>
                                    <td><?= $jumlah_total ?></td>
                                    <td><?= htmlspecialchars($p['keperluan']) ?></td>

                                    <td>
                                        <span class="badge <?= $warna ?>">
                                            <?= htmlspecialchars(ucfirst($p['status'])) ?>
                                        </span>
                                    </td>

                                    <?php if (in_array($level, ['admin', 'petugas'])) { ?>
                                        <td>
                                            <?php if ($p['status'] == 'menunggu') { ?>
                                                <div class="d-flex gap-2">
                                                    <a href="peminjaman.php?proses=disetujui&id=<?= $id ?>" class="btn btn-success btn-sm" 
                                                        onclick="return confirm('Setujui semua alat dalam pengajuan ini?')">
                                                        Setujui
                                                    </a>

                                                    <a href="peminjaman.php?proses=ditolak&id=<?= $id ?>" class="btn btn-danger btn-sm"
                                                       onclick="return confirm('Tolak pengajuan ini?')">
                                                        Tolak
                                                    </a>
                                                </div>
                                            <?php } else { ?>
                                                <span class="text-secondary">-</span>
                                            <?php } ?>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>

                            <?php if (mysqli_num_rows($data_peminjaman) == 0) { ?>
                                <tr>
                                    <td colspan="<?= in_array($level, ['admin', 'petugas']) ? 9 : 8 ?>" class="text-center py-4">
                                        Belum ada data peminjaman.
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <p id="hasil-pencarian" class="text-secondary small mb-0"></p>
                </div>
            </div>
        </div>

    <?php } ?>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function () {

    // Dropdown yang bisa diketik untuk mencari alat.
    function aktifkanDropdown(elemen) {
        $(elemen).select2({
            width: '100%',
            placeholder: 'Ketik nama alat untuk mencari...',
            allowClear: true
        });
    }

    aktifkanDropdown('.pilih-alat');

    // Tambah baris alat baru.
    $('#tambah-alat').on('click', function () {
        const baris = $('.baris-alat').first().clone(false);

        baris.find('.pilih-alat').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });

        baris.find('select').val('');
        baris.find('input.input-jumlah').val(1);
        baris.find('.info-stok').text('Pilih alat terlebih dahulu');

        $('#daftar-baris-alat').append(baris);

        aktifkanDropdown(baris.find('.pilih-alat'));
        hitungTotal();
    });

    // Hapus baris alat, minimal harus ada satu baris.
    $(document).on('click', '.hapus-alat', function () {
        if ($('.baris-alat').length <= 1) {
            alert('Minimal harus ada satu alat.');
            return;
        }

        $(this).closest('.baris-alat').remove();
        hitungTotal();
    });

    // Tampilkan stok alat yang dipilih.
    $(document).on('change', '.pilih-alat', function () {
        const pilihan = $(this).find(':selected');
        const stok = pilihan.data('stok');
        const baris = $(this).closest('.baris-alat');
        const inputJumlah = baris.find('.input-jumlah');

        if (stok !== undefined) {
            inputJumlah.attr('max', stok);
            baris.find('.info-stok').text('Stok tersedia: ' + stok);

            if (parseInt(inputJumlah.val()) > parseInt(stok)) {
                inputJumlah.val(stok);
            }
        } else {
            inputJumlah.removeAttr('max');
            baris.find('.info-stok').text('Pilih alat terlebih dahulu');
        }

        hitungTotal();
    });

    $(document).on('input change', '.input-jumlah', hitungTotal);

    function hitungTotal() {
        let total = 0;
        let jenis = 0;

        $('.baris-alat').each(function () {
            const id = $(this).find('.pilih-alat').val();
            const jumlah = parseInt($(this).find('.input-jumlah').val()) || 0;

            if (id) {
                jenis++;
                total += jumlah;
            }
        });

        $('#total-barang').text(total);
        $('#total-jenis').text(jenis);
    }

    // Cegah pengiriman jika alat sama dipilih dua kali atau stok terlampaui.
    $('#form-peminjaman').on('submit', function (e) {
        const terpilih = [];
        let valid = true;

        $('.baris-alat').each(function () {
            const select = $(this).find('.pilih-alat');
            const id = select.val();
            const jumlah = parseInt($(this).find('.input-jumlah').val()) || 0;
            const stok = parseInt(select.find(':selected').data('stok')) || 0;

            if (!id || jumlah < 1 || jumlah > stok || terpilih.includes(id)) {
                valid = false;
            }

            if (id) {
                terpilih.push(id);
            }
        });

        if (!valid) {
            e.preventDefault();
            alert('Periksa pilihan alat dan jumlahnya. Jangan pilih alat yang sama dua kali, dan pastikan jumlah tidak melebihi stok.');
        }
    });

    // Pencarian langsung di tabel peminjaman.
    $('#cari-peminjaman').on('input', function () {
        const kata = $(this).val().toLowerCase();
        let terlihat = 0;

        $('#tabel-peminjaman tbody tr').each(function () {
            const teks = $(this).text().toLowerCase();

            if (teks.includes(kata)) {
                $(this).show();
                terlihat++;
            } else {
                $(this).hide();
            }
        });

        $('#hasil-pencarian').text(
            kata ? 'Baris ditemukan: ' + terlihat : ''
        );
    });

});
</script>

</body>
</html>