<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class HolidayModel
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(?int $year = null): array
    {
        if ($year !== null) {
            return $this->db->fetchAll(
                'SELECT * FROM holidays WHERE holiday_date LIKE :prefix ORDER BY holiday_date ASC',
                [':prefix' => sprintf('%04d-%%', $year)]
            );
        }

        return $this->db->fetchAll('SELECT * FROM holidays ORDER BY holiday_date DESC');
    }

    public function isHoliday(string $date): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM holidays WHERE holiday_date = :d',
            [':d' => $date],
            0
        ) > 0;
    }

    /**
     * @return array<string, string> map date => description
     */
    public function mapInRange(string $startDate, string $endDate): array
    {
        $rows = $this->db->fetchAll(
            'SELECT holiday_date, description FROM holidays WHERE holiday_date BETWEEN :s AND :e',
            [':s' => $startDate, ':e' => $endDate]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['holiday_date']] = (string) $r['description'];
        }
        return $map;
    }

    public function add(string $date, string $description): int
    {
        $this->db->execute(
            'INSERT INTO holidays (holiday_date, description) VALUES (:d, :desc)
             ON CONFLICT(holiday_date) DO UPDATE SET description = excluded.description',
            [':d' => $date, ':desc' => trim($description)]
        );
        return $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM holidays WHERE id = :id', [':id' => $id]);
    }
}
