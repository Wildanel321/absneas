#!/usr/bin/env bash
set -euo pipefail

# Smart Attendance - Restore Database SQLite secara aman (CLI khusus administrator)
# Penggunaan: sudo bash scripts/restore-backup.sh /path/ke/file_backup.sqlite

if [[ $# -lt 1 ]]; then
  echo "Penggunaan: $0 <path-file-backup.sqlite>"
  exit 1
fi

SOURCE_FILE="$1"
APP_DIR="${APP_DIR:-/var/www/smart-attendance}"
DB_FILE="${APP_DIR}/storage/database/attendance.sqlite"
LOCAL_BACKUP_DIR="${APP_DIR}/storage/backups"

if [[ ! -f "${SOURCE_FILE}" ]]; then
  echo "Error: file backup tidak ditemukan: ${SOURCE_FILE}" >&2
  exit 1
fi

echo "[1/4] Memverifikasi integritas file backup..."
CHECK="$(sqlite3 "${SOURCE_FILE}" "PRAGMA integrity_check;")"
if [[ "${CHECK}" != "ok" ]]; then
  echo "Error: file backup gagal PRAGMA integrity_check (${CHECK}). Restore dibatalkan." >&2
  exit 1
fi

echo "[2/4] Membuat snapshot keamanan sebelum restore..."
mkdir -p "${LOCAL_BACKUP_DIR}"
SAFETY_FILE="${LOCAL_BACKUP_DIR}/pre_restore_$(date +%Y%m%d_%H%M%S).sqlite"
if [[ -f "${DB_FILE}" ]]; then
  sqlite3 "${DB_FILE}" ".timeout 5000" ".backup '${SAFETY_FILE}'"
  echo "Snapshot keamanan tersimpan: ${SAFETY_FILE}"
fi

echo "[3/4] Memulihkan database memakai SQLite Online Backup API..."
sqlite3 "${SOURCE_FILE}" ".timeout 5000" ".backup '${DB_FILE}'"

if id -u www-data >/dev/null 2>&1; then
  chown www-data:www-data "${DB_FILE}"
  chmod 660 "${DB_FILE}"
fi

echo "[4/4] Memverifikasi database aktif pasca-restore..."
POST_CHECK="$(sqlite3 "${DB_FILE}" "PRAGMA integrity_check;")"
if [[ "${POST_CHECK}" != "ok" ]]; then
  echo "Error: database pasca-restore rusak (${POST_CHECK}). Gunakan snapshot ${SAFETY_FILE}." >&2
  exit 1
fi

echo "Restore berhasil diselesaikan dengan aman."
