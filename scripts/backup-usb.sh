#!/usr/bin/env bash
set -euo pipefail

# Smart Attendance - Backup SQLite Konsisten Harian ke USB (Retensi 30 Hari)
# Menggunakan SQLite Online Backup API via PHP bin/backup.php

APP_DIR="${APP_DIR:-/var/www/smart-attendance}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"

if [[ -x "${PHP_BIN}" ]] && [[ -f "${APP_DIR}/bin/backup.php" ]]; then
  exec "${PHP_BIN}" "${APP_DIR}/bin/backup.php" daily
fi

# Fallback memakai sqlite3 CLI ".backup" bila dipanggil tanpa PHP
DB_FILE="${APP_DIR}/storage/database/attendance.sqlite"
LOCAL_DIR="${APP_DIR}/storage/backups"
USB_DIR="${BACKUP_USB_DIR:-/mnt/usb-backup/smart-attendance}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
STAMP="$(date +%Y%m%d_%H%M%S)"
OUT_FILE="${LOCAL_DIR}/daily_${STAMP}.sqlite"

mkdir -p "${LOCAL_DIR}"
sqlite3 "${DB_FILE}" ".timeout 5000" ".backup '${OUT_FILE}'"

CHECK="$(sqlite3 "${OUT_FILE}" "PRAGMA integrity_check;")"
if [[ "${CHECK}" != "ok" ]]; then
  rm -f "${OUT_FILE}"
  echo "[BACKUP ERROR] Integrity check gagal: ${CHECK}" >&2
  exit 1
fi

if [[ -d "$(dirname "${USB_DIR}")" ]] && [[ -w "$(dirname "${USB_DIR}")" ]]; then
  mkdir -p "${USB_DIR}"
  cp -f "${OUT_FILE}" "${USB_DIR}/daily_${STAMP}.sqlite"
  find "${USB_DIR}" -maxdepth 1 -name '*.sqlite' -type f -mtime "+${RETENTION_DAYS}" -delete
  echo "[BACKUP] Tersimpan di lokal dan USB: ${USB_DIR}/daily_${STAMP}.sqlite"
else
  echo "[BACKUP] Tersimpan di lokal saja (USB tidak terpasang): ${OUT_FILE}"
fi

find "${LOCAL_DIR}" -maxdepth 1 -name '*.sqlite' -type f -mtime "+${RETENTION_DAYS}" -delete
