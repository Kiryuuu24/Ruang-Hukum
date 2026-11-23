# RuangHukum

Platform diskusi hukum Indonesia. Dibangun dengan **PHP native**, **MySQL (PDO)**, dan **JavaScript vanilla**, tanpa framework.

Konsep utama: **Guest First, Login Optional**. Pengunjung langsung bisa bertanya dan membalas sebagai tamu (akun tamu dibuat otomatis), lalu boleh mendaftar kapan saja.

## Fitur

- **Peran pengguna**: tamu, member, advokat (`lawyer`), dan admin
- **Forum diskusi**: buat pertanyaan, balasan, dan suka (like)
- **Kategori "Belum Diketahui"**: pertanyaan yang belum jelas bidangnya bisa dikategorikan ulang oleh advokat/admin
- **Moderasi otomatis**:
  - Rate limit per peran (jeda dan batas per jam)
  - Filter kata kasar dan skor spam
  - Deteksi duplikat dan bot (honeypot dan validasi waktu pengisian form)
  - Sistem poin penalti yang meluruh 1 poin per 24 jam, dengan mute dan suspend otomatis
  - Postingan tamu berskor sedang masuk status *pending*
- **Notifikasi** dasar
- **Keamanan**: CSRF token, prepared statements, IP tidak disimpan mentah (di-hash dengan `APP_SALT`)

## Teknologi

| Bagian   | Teknologi                   |
| -------- | --------------------------- |
| Backend  | PHP 8+ (native, tanpa framework) |
| Database | MySQL / MariaDB via PDO     |
| Frontend | HTML, CSS, JavaScript vanilla |
| Deploy   | XAMPP (lokal), Railway (production) |

## Struktur Folder

```
ruang-hukum/
├── api/                # endpoint JSON (like, reply, notifications, recategorize)
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── config/
│   ├── app.php         # nama aplikasi & APP_SALT
│   ├── database.php    # koneksi PDO (baca env, fallback ke XAMPP)
│   └── moderation.php  # semua batas & angka moderasi
├── database/
│   ├── ruang_hukum.sql # skema + data awal (kategori, kata terlarang)
│   └── create_admin.php# script CLI buat akun admin/advokat
├── includes/           # init, auth, functions, moderation, header/footer, post_card
├── process/            # handler form (create_post)
├── index.php           # beranda
├── detail-post.php     # detail pertanyaan & balasan
├── login.php / register.php / logout.php
└── notifications.php
```

## Menjalankan di XAMPP (Lokal)

1. Salin folder proyek ke `C:\xampp\htdocs\ruang-hukum`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Buka phpMyAdmin, lalu import `database/ruang_hukum.sql` (otomatis membuat database `ruang_hukum`).
4. Buka http://localhost/ruang-hukum/
5. Buat akun admin atau advokat lewat CLI:

   ```bash
   php database/create_admin.php <username> <email> <password> [admin|lawyer]
   ```

   Contoh:

   ```bash
   php database/create_admin.php rizky_hukum rizky@mail.com passwordkuat lawyer
   ```

   Jika peran tidak diisi, default-nya `admin`.

Tanpa environment variable, koneksi memakai default XAMPP: host `127.0.0.1`, port `3306`, user `root`, password kosong, database `ruang_hukum`.

## Konfigurasi

Semua konfigurasi dibaca dari environment variable:

| Variabel                           | Keterangan                                         | Default (lokal)                  |
| ---------------------------------- | -------------------------------------------------- | -------------------------------- |
| `APP_SALT`                         | String acak panjang untuk hash IP dan tanda tangan form. **Wajib diganti di production.** | `ganti-salt-ini-sebelum-deploy`  |
| `MYSQLHOST`                        | Host database                                      | `127.0.0.1`                      |
| `MYSQLPORT`                        | Port database                                      | `3306`                           |
| `MYSQLDATABASE`                    | Nama database                                      | `ruang_hukum`                    |
| `MYSQLUSER`                        | User database                                      | `root`                           |
| `MYSQLPASSWORD`                    | Password database                                  | *(kosong)*                       |

Batas moderasi (jeda posting, batas per jam, ambang skor, poin penalti) bisa disesuaikan di `config/moderation.php`.

## Deploy ke Railway

1. Buat project baru dan hubungkan repository ini.
2. Tambahkan service **MySQL**. Variabel `MYSQL*` terisi otomatis.
3. Isi environment variable `APP_SALT` dengan string acak panjang, misalnya hasil `openssl rand -hex 32`.
4. Import `database/ruang_hukum.sql` ke database Railway.
5. Buat akun admin lewat Railway shell atau CLI:

   ```bash
   php database/create_admin.php <username> <email> <password> admin
   ```

## Status Pengembangan

**Selesai**
- Fase 1: sistem peran
- Fase 2: kategori "Belum Diketahui"
- Fase 3: moderasi inti
- Notifikasi dasar

**Belum**
- Panel admin
- Sistem laporan
- Verifikasi advokat
- Pencarian
- Halaman profil

## Catatan Keamanan

- Jangan commit file `.env` atau kredensial apa pun (sudah diabaikan oleh `.gitignore`).
- Ganti `APP_SALT` sebelum deploy. Nilai default hanya untuk pengembangan lokal.
- Hapus atau batasi akses ke `database/create_admin.php` di server production (script ini hanya bisa dijalankan lewat CLI).
