<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use SQLite3;

/**
 * Layanan backup dan restore SQLite konsisten menggunakan SQLite Online Backup API (SQLite3::backup).
 * Mendukung penyimpanan lokal, sinkronisasi otomatis ke USB, dan retensi 30 hari.
 */
final class BackupService
{
    public function __construct(
        private string $dbPath,
        private string $localBackupDir,
        private string $usbBackupDir,
        private int $retentionDays = 30
    ) {
    }

    /**
     * Membuat backup konsisten menggunakan SQLite Online Backup API.
     *
     * @return array{ok: bool, filename: string, local_path: string, usb_synced: bool, usb_path: ?string, size_bytes: int, pruned_local: int, pruned_usb: int, message: string}
     */
    public function createBackup(string $prefix = 'attendance'): array
    {
        if (!is_file($this->dbPath)) {
            throw new RuntimeException('File database tidak ditemukan: ' . $this->dbPath);
        }

        if (!is_dir($this->localBackupDir) && !mkdir($this->localBackupDir, 0750, true) && !is_dir($this->localBackupDir)) {
            throw new RuntimeException('Gagal membuat direktori backup lokal.');
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) ?: 'attendance';
        $filename = sprintf('%s_%s.sqlite', $safePrefix, date('Ymd_His'));
        $localTarget = $this->localBackupDir . DIRECTORY_SEPARATOR . $filename;

        $this->sqliteOnlineBackup($this->dbPath, $localTarget);

        // Verifikasi integritas hasil backup
        if (!$this->verifyIntegrity($localTarget)) {
            @unlink($localTarget);
            throw new RuntimeException('Verifikasi integritas backup gagal (PRAGMA integrity_check).');
        }

        $size = (int) (filesize($localTarget) ?: 0);

        // Salin ke USB jika mount point tersedia dan bisa ditulisi
        $usbSynced = false;
        $usbPath = null;
        if ($this->usbBackupDir !== '' && $this->isUsbAvailable()) {
            if (!is_dir($this->usbBackupDir)) {
                @mkdir($this->usbBackupDir, 0750, true);
            }
            if (is_dir($this->usbBackupDir) && is_writable($this->usbBackupDir)) {
                $candidateUsb = rtrim($this->usbBackupDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
                if (@copy($localTarget, $candidateUsb)) {
                    $usbSynced = true;
                    $usbPath = $candidateUsb;
                }
            }
        }

        $prunedLocal = $this->pruneOldBackups($this->localBackupDir);
        $prunedUsb = $usbSynced ? $this->pruneOldBackups($this->usbBackupDir) : 0;

        $msg = $usbSynced
            ? "Backup {$filename} berhasil disimpan lokal dan disalin ke USB."
            : "Backup {$filename} berhasil disimpan lokal (USB tidak terpasang atau belum writable).";

        return [
            'ok' => true,
            'filename' => $filename,
            'local_path' => $localTarget,
            'usb_synced' => $usbSynced,
            'usb_path' => $usbPath,
            'size_bytes' => $size,
            'pruned_local' => $prunedLocal,
            'pruned_usb' => $prunedUsb,
            'message' => $msg,
        ];
    }

    /**
     * Daftar file backup lokal yang tersedia.
     *
     * @return list<array{filename: string, size_bytes: int, created_at: string, location: string}>
     */
    public function listBackups(): array
    {
        $items = [];
        if (is_dir($this->localBackupDir)) {
            $files = glob($this->localBackupDir . DIRECTORY_SEPARATOR . '*.sqlite') ?: [];
            foreach ($files as $f) {
                $items[] = [
                    'filename' => basename($f),
                    'size_bytes' => (int) (filesize($f) ?: 0),
                    'created_at' => date('Y-m-d H:i:s', (int) (filemtime($f) ?: time())),
                    'location' => 'local',
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return $items;
    }

    public function resolveLocalBackupPath(string $filename): ?string
    {
        $base = basename($filename);
        if (!preg_match('/^[a-zA-Z0-9._-]+\.sqlite$/', $base)) {
            return null;
        }
        $path = $this->localBackupDir . DIRECTORY_SEPARATOR . $base;
        return is_file($path) ? $path : null;
    }

    public function isUsbAvailable(): bool
    {
        if ($this->usbBackupDir === '') {
            return false;
        }
        if (is_dir($this->usbBackupDir) && is_writable($this->usbBackupDir)) {
            return true;
        }
        $parent = dirname($this->usbBackupDir);
        return is_dir($parent) && is_writable($parent);
    }

    public function usbPath(): string
    {
        return $this->usbBackupDir;
    }

    public function retentionDays(): int
    {
        return $this->retentionDays;
    }

    /**
     * Memulihkan database dari file backup SQLite setelah memeriksa integritas dan membuat snapshot keamanan.
     *
     * @return array{ok: bool, message: string, safety_backup?: string}
     */
    public function restoreFromFile(string $sourceFile): array
    {
        if (!is_file($sourceFile)) {
            return ['ok' => false, 'message' => 'File backup sumber tidak ditemukan.'];
        }

        if (!$this->verifyIntegrity($sourceFile)) {
            return ['ok' => false, 'message' => 'File backup rusak atau bukan database SQLite Smart Attendance yang valid.'];
        }

        // Pastikan tabel inti ada di file backup
        if (!$this->hasRequiredSchema($sourceFile)) {
            return ['ok' => false, 'message' => 'Struktur tabel database backup tidak sesuai dengan Smart Attendance.'];
        }

        // Buat safety snapshot sebelum menimpa database aktif
        $safety = $this->createBackup('pre_restore');

        // Gunakan SQLite Online Backup API dari sourceFile ke database aktif agar aman meskipun WAL aktif
        $this->sqliteOnlineBackup($sourceFile, $this->dbPath);

        return [
            'ok' => true,
            'message' => 'Database berhasil dipulihkan. Snapshot sebelum restore disimpan sebagai ' . $safety['filename'] . '.',
            'safety_backup' => $safety['filename'],
        ];
    }

    public function verifyIntegrity(string $sqlitePath): bool
    {
        try {
            $conn = new SQLite3($sqlitePath, SQLITE3_OPEN_READONLY);
            $result = $conn->querySingle('PRAGMA integrity_check');
            $conn->close();
            return strtolower((string) $result) === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasRequiredSchema(string $sqlitePath): bool
    {
        try {
            $conn = new SQLite3($sqlitePath, SQLITE3_OPEN_READONLY);
            $required = ['users', 'settings', 'attendances', 'qr_tokens'];
            foreach ($required as $table) {
                $exists = $conn->querySingle(
                    "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='" . SQLite3::escapeString($table) . "'"
                );
                if ((int) $exists !== 1) {
                    $conn->close();
                    return false;
                }
            }
            $conn->close();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function sqliteOnlineBackup(string $sourcePath, string $destPath): void
    {
        $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
        $source->busyTimeout(5000);
        $dest = new SQLite3($destPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $dest->busyTimeout(5000);

        try {
            $ok = $source->backup($dest);
            if (!$ok) {
                throw new RuntimeException('SQLite Online Backup gagal dieksekusi.');
            }
        } finally {
            $dest->close();
            $source->close();
        }
    }

    private function pruneOldBackups(string $dir): int
    {
        if (!is_dir($dir) || $this->retentionDays <= 0) {
            return 0;
        }

        $cutoff = time() - ($this->retentionDays * 86400);
        $files = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*.sqlite') ?: [];
        $removed = 0;

        foreach ($files as $f) {
            $mtime = filemtime($f);
            if ($mtime !== false && $mtime < $cutoff) {
                if (@unlink($f)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }
}
