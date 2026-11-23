<?php
// config/app.php - konstanta umum aplikasi
define('APP_NAME', 'RuangHukum');
// Di production WAJIB diisi lewat environment variable APP_SALT
define('APP_SALT', getenv('APP_SALT') ?: 'ganti-salt-ini-sebelum-deploy');
