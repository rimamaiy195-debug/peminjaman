<?php
$koneksi = mysqli_connect("localhost", "root", "", "db_peminjaman");
if (!$koneksi) {
	die("Koneksi database gagal!" . mysqli_connect_error());
}
?>