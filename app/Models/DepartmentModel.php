<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class DepartmentModel
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT d.*, COUNT(u.id) AS employee_count
             FROM departments d
             LEFT JOIN users u ON u.department_id = d.id AND u.role = "employee"
             GROUP BY d.id
             ORDER BY d.name ASC'
        );
    }

    public function create(string $name): int
    {
        $this->db->execute('INSERT INTO departments (name) VALUES (:name)', [':name' => trim($name)]);
        return $this->db->lastInsertId();
    }

    public function existsByName(string $name): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM departments WHERE LOWER(name) = LOWER(:name)',
            [':name' => trim($name)],
            0
        ) > 0;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM departments WHERE id = :id', [':id' => $id]);
    }
}
