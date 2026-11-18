<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\UserModel;

final class AuthService
{
    private const SESSION_USER_ID = '_auth_user_id';
    /** @var array<string, mixed>|null */
    private ?array $cachedUser = null;

    public function __construct(
        private Database $db,
        private UserModel $users,
        private int $maxAttempts = 5,
        private int $windowMinutes = 15
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $id = Session::get(self::SESSION_USER_ID);
        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }

        $user = $this->users->findById((int) $id);
        if ($user === null || (int) $user['is_active'] !== 1) {
            Session::forget(self::SESSION_USER_ID);
            return null;
        }

        $this->cachedUser = $user;
        return $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        $u = $this->user();
        return $u !== null && $u['role'] === 'admin';
    }

    /**
     * @return array{blocked: bool, retry_after_seconds: int, attempts: int}
     */
    public function rateLimitStatus(string $ip, string $username): array
    {
        $windowSeconds = max(60, $this->windowMinutes * 60);
        $cutoff = time() - $windowSeconds;
        $normalizedUser = strtolower(trim($username));

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt, MIN(attempted_at) AS oldest
             FROM login_attempts
             WHERE success = 0
               AND attempted_at >= :cutoff
               AND (ip_address = :ip OR (username != "" AND username = :username))',
            [
                ':cutoff' => $cutoff,
                ':ip' => $ip,
                ':username' => $normalizedUser,
            ]
        );

        $attempts = (int) ($row['cnt'] ?? 0);
        if ($attempts < $this->maxAttempts) {
            return ['blocked' => false, 'retry_after_seconds' => 0, 'attempts' => $attempts];
        }

        $oldest = (int) ($row['oldest'] ?? time());
        $retryAfter = max(1, ($oldest + $windowSeconds) - time());
        return ['blocked' => true, 'retry_after_seconds' => $retryAfter, 'attempts' => $attempts];
    }

    /**
     * @return array{ok: bool, status: int, message: string, user?: array<string, mixed>, retry_after?: int}
     */
    public function attempt(string $username, string $password, string $ip): array
    {
        $normalizedUser = strtolower(trim($username));
        $limit = $this->rateLimitStatus($ip, $normalizedUser);
        if ($limit['blocked']) {
            $minutes = (int) ceil($limit['retry_after_seconds'] / 60);
            return [
                'ok' => false,
                'status' => 429,
                'message' => "Terlalu banyak percobaan login gagal. Coba lagi dalam {$minutes} menit.",
                'retry_after' => $limit['retry_after_seconds'],
            ];
        }

        $user = $this->users->findByUsername($normalizedUser);
        $validPassword = $user !== null && password_verify($password, (string) $user['password_hash']);

        if (!$validPassword) {
            $this->recordAttempt($ip, $normalizedUser, false);
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Username atau password salah.',
            ];
        }

        if ((int) $user['is_active'] !== 1) {
            $this->recordAttempt($ip, $normalizedUser, false);
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Akun Anda sedang dinonaktifkan. Hubungi administrator.',
            ];
        }

        // Rehash jika algoritma PHP berubah
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->updatePassword((int) $user['id'], $password);
        }

        $this->recordAttempt($ip, $normalizedUser, true);
        $this->clearFailedAttempts($ip, $normalizedUser);
        $this->users->touchLastLogin((int) $user['id']);

        Session::regenerate();
        Session::set(self::SESSION_USER_ID, (int) $user['id']);
        $this->cachedUser = $user;

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Login berhasil.',
            'user' => $user,
        ];
    }

    public function logout(): void
    {
        $this->cachedUser = null;
        Session::destroy();
    }

    private function recordAttempt(string $ip, string $username, bool $success): void
    {
        $this->db->execute(
            'INSERT INTO login_attempts (ip_address, username, attempted_at, success) VALUES (:ip, :u, :at, :s)',
            [
                ':ip' => $ip,
                ':u' => $username,
                ':at' => time(),
                ':s' => $success ? 1 : 0,
            ]
        );

        // Bersihkan log lama lebih dari 7 hari secara ringan
        $this->db->execute('DELETE FROM login_attempts WHERE attempted_at < :old', [
            ':old' => time() - (7 * 86400),
        ]);
    }

    private function clearFailedAttempts(string $ip, string $username): void
    {
        $this->db->execute(
            'DELETE FROM login_attempts WHERE success = 0 AND ip_address = :ip AND username = :u',
            [':ip' => $ip, ':u' => $username]
        );
    }
}
