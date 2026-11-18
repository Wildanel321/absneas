<?php

declare(strict_types=1);

namespace App\Core;

final class Application
{
    /** @var array<string, mixed> */
    private array $config;
    private Database $db;
    private View $view;
    private Logger $logger;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Jakarta'));
        $this->logger = new Logger((string) $config['logs_path']);
        $this->db = new Database((string) $config['db_path']);
        $this->view = new View((string) $config['views_path']);

        // Sinkronkan timezone dari pengaturan database bila tersedia
        try {
            $tz = $this->db->fetchValue("SELECT value FROM settings WHERE key = 'timezone'");
            if (is_string($tz) && $tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
                date_default_timezone_set($tz);
            }
        } catch (\Throwable) {
            // Tabel settings belum dibuat saat migrasi awal
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? $default;
    }

    public function db(): Database
    {
        return $this->db;
    }

    public function view(): View
    {
        return $this->view;
    }

    public function logger(): Logger
    {
        return $this->logger;
    }

    public function appKey(): string
    {
        $key = (string) ($this->config['key'] ?? '');
        if ($key === '' || str_starts_with($key, 'CHANGE_ME')) {
            // Fallback deterministik berbasis path + host jika .env belum diisi, disarankan isi APP_KEY di .env
            $key = hash('sha256', (string) $this->config['db_path'] . '|smart-attendance-local-key');
        }
        return $key;
    }
}
