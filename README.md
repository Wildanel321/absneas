# Smart Attendance (Raspberry Pi 3 Edition)

Sistem absensi karyawan berbasis web yang ringan, responsif, dapat di-install sebagai PWA, serta menggunakan QR Code dinamis dan validasi GPS geofencing di server. Dirancang khusus untuk berjalan stabil pada Raspberry Pi 3 Model B (1 GB RAM, ARM64) dengan PHP Native, SQLite, Nginx + PHP-FPM, Cloudflare Tunnel, dan backup otomatis ke USB.

## Fitur Utama

- **Absensi QR Dinamis (Check-in & Check-out)**: Monitor kantor (`/kiosk`) menampilkan QR SVG yang dibuat lokal di server tanpa API pihak ketiga, diperbarui otomatis dengan token acak bertanda tangan HMAC, dan berpindah otomatis dari mode Check-in ke Check-out mengikuti jadwal kerja.
- **Anti-Replay & Anti-Double Attendance**: Validasi token, masa berlaku, pencabutan token lama, dan pencegahan penggunaan ulang/absensi ganda dilakukan penuh di backend SQLite dengan transaksi atomik.
- **GPS & Geofencing Haversine**: Browser mengambil koordinat dan akurasi GPS, lalu server menghitung jarak terhadap lokasi kantor memakai rumus Haversine. Absensi di luar radius (default 100 m) atau dengan akurasi GPS terlalu rendah langsung ditolak. Catatan: GPS browser membantu verifikasi lokasi namun bukan perlindungan anti-spoofing yang mutlak, sehingga dikombinasikan dengan QR dinamis fisik di lokasi kerja.
- **PWA Karyawan (Mobile-First)**: Dapat di-install di Android dan desktop, dilengkapi kamera scanner QR (native `BarcodeDetector` + fallback lokal `jsQR`), status absensi hari ini, riwayat bulanan, pengajuan izin/sakit, profil, dan Service Worker khusus caching aset statis (tidak menyimpan token QR dan tidak mengizinkan absensi offline).
- **Dashboard Administrator**: Statistik kehadiran real-time, daftar karyawan belum check-out, filter tanggal/bulan/departemen/nama, 4 grafik interaktif SVG (kehadiran harian, keterlambatan, tren bulanan, distribusi izin & sakit), ranking kedisiplinan karyawan, ekspor CSV, manajemen karyawan & departemen, pengaturan aturan absensi & hari libur, backup/restore database, dan log audit administrator.
- **Keamanan & Backup**: `password_hash()`, PDO prepared statements (`ATTR_EMULATE_PREPARES = false`), proteksi CSRF di setiap permintaan mutasi, cookie sesi `HttpOnly` + `SameSite=Lax`, rate limiting login persisten, log audit admin, backup konsisten memakai SQLite Online Backup API dengan retensi 30 hari ke USB.

## Struktur Proyek

```text
smart-attendance/
├── app/
│   ├── Core/           # Autoloader, Env, Database, Session, Csrf, Router, View, Validator, Bootstrap
│   ├── Controllers/    # Auth, Employee, Kiosk, AdminDashboard, AdminEmployee, AdminSettings
│   ├── Models/         # User, Department, Setting, Holiday, Attendance, LeaveRequest, AuditLog
│   ├── Services/       # Auth, Geo (Haversine), QrSvgGenerator, QrToken, Attendance, Analytics, Backup
│   └── Middleware/     # AuthMiddleware (Auth, Admin, CSRF, Kiosk Key)
├── bin/                # migrate.php, backup.php, keygen.php, generate-icons.php, seed-demo.php
├── config/             # app.php, nginx, php-fpm pool, systemd timer, cloudflared example
├── database/
│   └── migrations/     # 001_initial_schema.sql
├── public/             # Document root web server (index.php, manifest.json, sw.js, assets, icons)
├── resources/views/    # Layout admin, layout PWA karyawan, auth, kiosk, halaman admin & karyawan
├── scripts/            # install-rpi.sh, backup-usb.sh, restore-backup.sh
├── storage/            # database/, backups/, logs/ (tidak terekspos ke publik)
├── tests/              # run.php (uji unit & integrasi), qr-verify.mjs (uji decode QR)
├── .env.example
├── composer.json
└── README.md
```

## Menjalankan Secara Lokal (Pengembangan / Pengujian)

Prasyarat: PHP 8.1+ dengan ekstensi `pdo_sqlite`, `sqlite3`, `openssl`, `mbstring`, `gd`.

```bash
cp .env.example .env
php bin/keygen.php
# Salin APP_KEY dan KIOSK_KEY ke file .env

# 1. Jalankan migrasi database SQLite (membuat akun awal admin / ChangeMe123!)
php bin/migrate.php

# 2. (Opsional) Isi data karyawan & riwayat demo untuk pengujian lokal
php bin/seed-demo.php

# 3. Jalankan server lokal
php -S 127.0.0.1:8000 -t public public/index.php
```

Buka `http://127.0.0.1:8000`:
- Login awal admin: `admin` / `ChangeMe123!` (segera ubah password setelah login).
- Jika menjalankan `bin/seed-demo.php`, contoh akun karyawan: `rina.kartika` / `Karyawan123!`.

## Instalasi Produksi di Raspberry Pi 3 Model B (ARM64)

Target OS: **Raspberry Pi OS Lite 64-bit (Bookworm/Bullseye ARM64)**.

### 1. Instalasi Otomatis

Salin folder proyek ke Raspberry Pi (misalnya via `git clone` atau `scp`), lalu jalankan:

```bash
cd smart-attendance
sudo bash scripts/install-rpi.sh
```

Script tersebut otomatis:
1. Memasang `nginx`, `php-fpm`, `php-sqlite3`, `php-mbstring`, `php-gd`, dan `sqlite3`.
2. Menyalin aplikasi ke `/var/www/smart-attendance` dengan izin file ketat (`www-data`, `.env` bermode `600`).
3. Membuat `.env` berisi `APP_KEY` dan `KIOSK_KEY` acak lalu menjalankan migrasi SQLite.
4. Memasang pool PHP-FPM hemat RAM (`pm = ondemand`, `pm.max_children = 5`, `memory_limit = 64M`).
5. Mengaktifkan konfigurasi Nginx di port lokal `8080` dengan document root `/var/www/smart-attendance/public`.
6. Mengaktifkan `systemd` timer `smart-attendance-backup.timer` untuk backup harian pukul 02:15.

### 2. Mount USB Flashdisk untuk Backup Otomatis

Cari UUID USB flashdisk:

```bash
lsblk -f
```

Buat direktori mount dan tambahkan baris ke `/etc/fstab` (ganti `<UUID>` dan sesuaikan tipe filesystem, misalnya `ext4` atau `exfat`):

```bash
sudo mkdir -p /mnt/usb-backup
```

Contoh baris `/etc/fstab` untuk partisi `ext4`:

```text
UUID=<UUID-USB-ANDA> /mnt/usb-backup ext4 defaults,nofail,noatime 0 2
```

Mount USB dan siapkan folder target agar dapat ditulis oleh `www-data`:

```bash
sudo mount -a
sudo mkdir -p /mnt/usb-backup/smart-attendance
sudo chown -R www-data:www-data /mnt/usb-backup/smart-attendance
sudo chmod 750 /mnt/usb-backup/smart-attendance
```

Uji backup manual:

```bash
sudo -u www-data /var/www/smart-attendance/scripts/backup-usb.sh
systemctl list-timers smart-attendance-backup.timer
```

Untuk restore dari CLI:

```bash
sudo bash /var/www/smart-attendance/scripts/restore-backup.sh /mnt/usb-backup/smart-attendance/daily_YYYYMMDD_HHMMSS.sqlite
```

### 3. Deployment HTTPS via Cloudflare Tunnel

Akses kamera (`getUserMedia`) dan GPS (`Geolocation API`) pada PWA **mewajibkan HTTPS**. Cloudflare Tunnel menyediakan HTTPS gratis tanpa membuka port router.

Pasang `cloudflared` ARM64 di Raspberry Pi:

```bash
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm64.deb -o cloudflared.deb
sudo dpkg -i cloudflared.deb
rm -f cloudflared.deb
```

Autentikasi dan buat tunnel:

```bash
cloudflared tunnel login
cloudflared tunnel create smart-attendance
cloudflared tunnel route dns smart-attendance absensi.domainanda.com
```

Salin contoh konfigurasi:

```bash
sudo mkdir -p /etc/cloudflared
sudo cp /var/www/smart-attendance/config/cloudflared-config.yml.example /etc/cloudflared/config.yml
# Edit /etc/cloudflared/config.yml dan isi Tunnel ID, path credentials JSON, serta hostname Anda
sudo cloudflared service install
sudo systemctl enable --now cloudflared
```

Perbarui `APP_URL=https://absensi.domainanda.com` di `/var/www/smart-attendance/.env`.

### 4. Monitor QR Fullscreen di Lokasi Kerja

1. Login sebagai administrator, buka menu **Aturan & GPS**.
2. Salin **URL Kiosk** (`/kiosk?key=...`).
3. Buka URL tersebut pada browser monitor kantor (atau jalankan Chromium kiosk mode di Raspberry Pi):

```bash
chromium-browser --kiosk --incognito --noerrdialogs "https://absensi.domainanda.com/kiosk?key=KUNCI_KIOSK_ANDA"
```

## Menjalankan Pengujian Otomatis

```bash
php tests/run.php
node tests/qr-verify.mjs
```

Pengujian mencakup:
- Migrasi SQLite, WAL mode, foreign keys.
- Autentikasi, `password_hash()`, rate limiting login (blokir setelah 5 gagal), CSRF validation.
- CRUD karyawan, kode otomatis `EMP-001`, pencegahan hapus/nonaktif admin terakhir.
- Generasi QR dinamis & verifikasi decode nyata via `jsQR`.
- Check-in & check-out, perhitungan keterlambatan otomatis, deteksi replay token, pencegahan double check-in/check-out, validasi jarak Haversine (dalam vs luar radius, akurasi GPS rendah).
- Pengajuan izin/sakit dan propagasi otomatis ke tabel `attendances` saat disetujui.
- Dashboard analytics & ranking kedisiplinan.
- SQLite Online Backup API, verifikasi integritas, dan restore database.
