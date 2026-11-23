<?php
// logout.php - keluar; kunjungan berikutnya otomatis jadi tamu baru
session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
