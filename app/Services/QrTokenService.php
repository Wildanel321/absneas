<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\SettingModel;

final class QrTokenService
{
    public function __construct(
        private Database $db,
        private SettingModel $settings,
        private string $appKey
    ) {
    }

    /**
     * Mengembalikan jam pulang yang berlaku berdasarkan hari:
     * Sabtu => saturday_work_end_time (default 12:00)
     * Senin-Jumat => work_end_time (default 14:00)
     */
    public function getWorkEndTimeForDay(int $timestamp): string
    {
        $isoDay = (int) date('N', $timestamp);
        if ($isoDay === 6) {
            return $this->settings->get('saturday_work_end_time', '12:00');
        }
        return $this->settings->get('work_end_time', '14:00');
    }

    /**
     * Menentukan mode QR otomatis berdasarkan jadwal kantor saat ini.
     * Sebelum (work_end_time - checkout_open_before_minutes) => checkin, setelahnya => checkout.
     */
    public function resolveScheduledMode(?int $nowTimestamp = null): string
    {
        $now = $nowTimestamp ?? time();
        $workEnd = $this->getWorkEndTimeForDay($now);
        $openBefore = max(0, $this->settings->getInt('checkout_open_before_minutes', 30));

        $todayStr = date('Y-m-d', $now);
        $endTs = strtotime($todayStr . ' ' . $workEnd . ':00');
        if ($endTs === false) {
            return 'checkin';
        }

        $switchTs = $endTs - ($openBefore * 60);
        return $now >= $switchTs ? 'checkout' : 'checkin';
    }

    /**
     * Mengembalikan informasi jadwal sesi QR untuk ditampilkan di monitor kiosk.
     *
     * @return array<string, mixed>
     */
    public function scheduleInfo(?int $nowTimestamp = null): array
    {
        $now = $nowTimestamp ?? time();
        $isoDay = (int) date('N', $now);
        $isSaturday = ($isoDay === 6);
        $workStart = $this->settings->get('work_start_time', '07:45');
        $workEnd = $this->getWorkEndTimeForDay($now);
        $satEnd = $this->settings->get('saturday_work_end_time', '12:00');
        $openBefore = max(0, $this->settings->getInt('checkout_open_before_minutes', 30));
        $todayStr = date('Y-m-d', $now);
        $endTs = strtotime($todayStr . ' ' . $workEnd . ':00') ?: $now;
        $switchTs = $endTs - ($openBefore * 60);
        $mode = $now >= $switchTs ? 'checkout' : 'checkin';

        return [
            'mode' => $mode,
            'work_start_time' => $workStart,
            'work_end_time' => $workEnd,
            'saturday_work_end_time' => $satEnd,
            'is_saturday' => $isSaturday,
            'checkout_switch_time' => date('H:i', $switchTs),
            'refresh_seconds' => max(10, $this->settings->getInt('qr_refresh_seconds', 30)),
            'token_ttl_seconds' => max(15, $this->settings->getInt('qr_token_ttl_seconds', 45)),
        ];
    }

    /**
     * Menerbitkan token QR dinamis baru dan menyimpan hash-nya ke database.
     *
     * @return array{id: int, token: string, mode: string, issued_at: int, expires_at: int, refresh_seconds: int, ttl_seconds: int}
     */
    public function issueToken(?string $forcedMode = null, string $ip = '', ?int $nowTimestamp = null): array
    {
        $now = $nowTimestamp ?? time();
        $mode = ($forcedMode === 'checkin' || $forcedMode === 'checkout')
            ? $forcedMode
            : $this->resolveScheduledMode($now);

        $refreshSeconds = max(10, $this->settings->getInt('qr_refresh_seconds', 30));
        $ttlSeconds = max($refreshSeconds + 5, $this->settings->getInt('qr_token_ttl_seconds', 45));
        $expiresAt = $now + $ttlSeconds;

        // Batasi token aktif sebelumnya: izinkan grace window singkat (maks 10 detik) agar scan terakhir tidak putus,
        // sedangkan token lebih lama langsung dicabut.
        $graceLimit = $now + min(10, max(5, $ttlSeconds - $refreshSeconds));
        $this->db->execute(
            'UPDATE qr_tokens SET expires_at = :grace WHERE mode = :mode AND revoked_at IS NULL AND expires_at > :grace',
            [':grace' => $graceLimit, ':mode' => $mode]
        );
        // Cabut token untuk mode lawan agar tidak bisa dipakai setelah pergantian sesi
        $otherMode = $mode === 'checkin' ? 'checkout' : 'checkin';
        $this->db->execute(
            'UPDATE qr_tokens SET revoked_at = :now WHERE mode = :mode AND revoked_at IS NULL',
            [':now' => $now, ':mode' => $otherMode]
        );

        // Hapus token kedaluwarsa lebih dari 24 jam
        $this->db->execute('DELETE FROM qr_tokens WHERE expires_at < :old', [':old' => $now - 86400]);

        $nonce = bin2hex(random_bytes(16));
        $prefix = substr($nonce, 0, 8);
        $payload = sprintf('SAT1|%s|%d|%s', $mode, $expiresAt, $nonce);
        $sig = substr(hash_hmac('sha256', $payload, $this->appKey), 0, 24);
        $rawToken = $payload . '|' . $sig;
        $tokenHash = hash('sha256', $rawToken);

        $this->db->execute(
            'INSERT INTO qr_tokens (token_hash, token_prefix, mode, issued_at, expires_at, created_by_ip)
             VALUES (:hash, :prefix, :mode, :issued, :exp, :ip)',
            [
                ':hash' => $tokenHash,
                ':prefix' => $prefix,
                ':mode' => $mode,
                ':issued' => $now,
                ':exp' => $expiresAt,
                ':ip' => $ip,
            ]
        );

        return [
            'id' => $this->db->lastInsertId(),
            'token' => $rawToken,
            'mode' => $mode,
            'issued_at' => $now,
            'expires_at' => $expiresAt,
            'refresh_seconds' => $refreshSeconds,
            'ttl_seconds' => $ttlSeconds,
        ];
    }

    /**
     * Memvalidasi token QR di sisi backend (format, HMAC, keberadaan di DB, belum dicabut, belum kedaluwarsa, belum direplay user).
     *
     * @return array{ok: bool, message: string, token_row?: array<string, mixed>, mode?: string}
     */
    public function validateToken(string $rawToken, int $userId, ?int $nowTimestamp = null): array
    {
        $now = $nowTimestamp ?? time();
        $trimmed = trim($rawToken);
        $parts = explode('|', $trimmed);

        if (count($parts) !== 5 || $parts[0] !== 'SAT1') {
            return ['ok' => false, 'message' => 'Format QR Code tidak valid untuk Smart Attendance.'];
        }

        [$version, $mode, $expStr, $nonce, $sig] = $parts;
        if (!in_array($mode, ['checkin', 'checkout'], true) || !ctype_digit($expStr)) {
            return ['ok' => false, 'message' => 'Isi QR Code tidak dikenali.'];
        }

        $payload = sprintf('%s|%s|%s|%s', $version, $mode, $expStr, $nonce);
        $expectedSig = substr(hash_hmac('sha256', $payload, $this->appKey), 0, 24);
        if (!hash_equals($expectedSig, $sig)) {
            return ['ok' => false, 'message' => 'Tanda tangan QR Code tidak valid.'];
        }

        $hash = hash('sha256', $trimmed);
        $row = $this->db->fetchOne('SELECT * FROM qr_tokens WHERE token_hash = :hash', [':hash' => $hash]);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Token QR tidak ditemukan atau sudah tidak berlaku.'];
        }

        if ($row['revoked_at'] !== null) {
            return ['ok' => false, 'message' => 'Token QR sudah dicabut. Silakan pindai QR terbaru di layar monitor.'];
        }

        if ((int) $row['expires_at'] < $now) {
            return ['ok' => false, 'message' => 'Token QR sudah kedaluwarsa. Silakan pindai QR terbaru di layar monitor.'];
        }

        if ((string) $row['mode'] !== $mode) {
            return ['ok' => false, 'message' => 'Mode token QR tidak cocok.'];
        }

        $used = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM qr_token_usages WHERE qr_token_id = :tid AND user_id = :uid',
            [':tid' => (int) $row['id'], ':uid' => $userId],
            0
        );
        if ($used > 0) {
            return ['ok' => false, 'message' => 'Token QR ini sudah pernah digunakan oleh akun Anda.'];
        }

        return [
            'ok' => true,
            'message' => 'Token QR valid.',
            'token_row' => $row,
            'mode' => $mode,
        ];
    }

    public function markUsed(int $qrTokenId, int $userId): void
    {
        $this->db->execute(
            "INSERT INTO qr_token_usages (qr_token_id, user_id, used_at) VALUES (:tid, :uid, datetime('now', 'localtime'))",
            [':tid' => $qrTokenId, ':uid' => $userId]
        );
    }
}
