<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SettingModel
{
    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rows = $this->db->fetchAll('SELECT key, value FROM settings');
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['key']] = (string) $row['value'];
        }
        $this->cache = $map;
        return $map;
    }

    public function get(string $key, string $default = ''): string
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $v = $this->get($key, (string) $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $v = $this->get($key, (string) $default);
        return is_numeric($v) ? (float) $v : $default;
    }

    /**
     * @return list<int> ISO day numbers 1 (Mon) .. 7 (Sun)
     */
    public function workingDays(): array
    {
        $raw = $this->get('working_days', '[1,2,3,4,5]');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [1, 2, 3, 4, 5];
        }
        $days = [];
        foreach ($decoded as $d) {
            $i = (int) $d;
            if ($i >= 1 && $i <= 7 && !in_array($i, $days, true)) {
                $days[] = $i;
            }
        }
        sort($days);
        return $days !== [] ? $days : [1, 2, 3, 4, 5];
    }

    /**
     * @param array<string, string|int|float> $values
     */
    public function setMany(array $values): void
    {
        $this->db->transaction(static function (Database $db) use ($values): void {
            foreach ($values as $key => $val) {
                $db->execute(
                    "INSERT INTO settings (key, value, updated_at)
                     VALUES (:key, :val, datetime('now', 'localtime'))
                     ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now', 'localtime')",
                    [':key' => $key, ':val' => (string) $val]
                );
            }
        });
        $this->cache = null;
    }

    public function clearCache(): void
    {
        $this->cache = null;
    }
}
