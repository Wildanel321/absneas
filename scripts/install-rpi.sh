#!/usr/bin/env bash
set -euo pipefail

# Smart Attendance - Script Instalasi Otomatis untuk Raspberry Pi OS Lite 64-bit (ARM64)
# Jalankan sebagai root: sudo bash scripts/install-rpi.sh

if [[ "${EUID}" -ne 0 ]]; then
  echo "Harap jalankan script ini dengan sudo: sudo bash scripts/install-rpi.sh"
  exit 1
fi

APP_DIR="/var/www/smart-attendance"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
USB_MOUNT="/mnt/usb-backup"

echo "[1/7] Memperbarui paket dan memasang Nginx, PHP-FPM, SQLite3..."
apt-get update
apt-get install -y --no-install-recommends \
  nginx \
  php-fpm \
  php-cli \
  php-sqlite3 \
  php-mbstring \
  php-gd \
  php-xml \
  sqlite3 \
  curl \
  ca-certificates \
  rsync

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
echo "Versi PHP terdeteksi: ${PHP_VER}"

echo "[2/7] Menyalin aplikasi ke ${APP_DIR}..."
mkdir -p "${APP_DIR}"
rsync -a --delete \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='storage/database/*.sqlite*' \
  --exclude='storage/backups/*.sqlite' \
  --exclude='storage/logs/*.log' \
  "${SRC_DIR}/" "${APP_DIR}/"

mkdir -p "${APP_DIR}/storage/database" "${APP_DIR}/storage/backups" "${APP_DIR}/storage/logs"
mkdir -p "${USB_MOUNT}/smart-attendance"

echo "[3/7] Menyiapkan file .env dan kunci kriptografi..."
if [[ ! -f "${APP_DIR}/.env" ]]; then
  cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
  APP_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
  KIOSK_KEY="$(php -r 'echo bin2hex(random_bytes(16));')"
  sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" "${APP_DIR}/.env"
  sed -i "s|^KIOSK_KEY=.*|KIOSK_KEY=${KIOSK_KEY}|" "${APP_DIR}/.env"
  echo "File .env baru dibuat dengan APP_KEY dan KIOSK_KEY acak."
fi

echo "[4/7] Mengatur hak akses file yang aman..."
chown -R www-data:www-data "${APP_DIR}"
find "${APP_DIR}" -type d -exec chmod 750 {} \;
find "${APP_DIR}" -type f -exec chmod 640 {} \;
chmod 755 "${APP_DIR}/public" "${APP_DIR}/scripts/backup-usb.sh" "${APP_DIR}/scripts/restore-backup.sh" "${APP_DIR}/scripts/install-rpi.sh"
chmod 600 "${APP_DIR}/.env"
chmod 770 "${APP_DIR}/storage" "${APP_DIR}/storage/database" "${APP_DIR}/storage/backups" "${APP_DIR}/storage/logs"

echo "[5/7] Menjalankan migrasi database SQLite..."
su -s /bin/bash www-data -c "php ${APP_DIR}/bin/migrate.php"

echo "[6/7] Mengonfigurasi PHP-FPM (hemat RAM) dan Nginx..."
cp "${APP_DIR}/config/php-fpm-pool.conf" "/etc/php/${PHP_VER}/fpm/pool.d/smart-attendance.conf"
cp "${APP_DIR}/config/nginx-smart-attendance.conf" "/etc/nginx/sites-available/smart-attendance"
ln -sf /etc/nginx/sites-available/smart-attendance /etc/nginx/sites-enabled/smart-attendance
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl enable --now "php${PHP_VER}-fpm"
systemctl restart "php${PHP_VER}-fpm"
systemctl enable --now nginx
systemctl restart nginx

echo "[7/7] Memasang systemd timer backup harian ke USB..."
cp "${APP_DIR}/config/systemd/smart-attendance-backup.service" /etc/systemd/system/
cp "${APP_DIR}/config/systemd/smart-attendance-backup.timer" /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now smart-attendance-backup.timer

echo ""
echo "Instalasi Smart Attendance selesai."
echo "- URL lokal Nginx: http://127.0.0.1:8080"
echo "- Cek healthcheck: curl -s http://127.0.0.1:8080/health"
echo "- Segera ganti password akun 'admin' setelah login pertama."
echo "- Langkah lanjut: pasang Cloudflare Tunnel sesuai README.md agar HTTPS aktif untuk kamera & GPS."
