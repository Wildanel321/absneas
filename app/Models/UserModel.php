<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class UserModel
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.*, d.name AS department_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = :id',
            [':id' => $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.*, d.name AS department_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE LOWER(u.username) = LOWER(:username)',
            [':username' => trim($username)]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT u.*, d.name AS department_name
                FROM users u
                LEFT JOIN departments d ON d.id = u.department_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['role'])) {
            $sql .= ' AND u.role = :role';
            $params[':role'] = $filters['role'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $sql .= ' AND u.is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }
        if (!empty($filters['department_id'])) {
            $sql .= ' AND u.department_id = :dept_id';
            $params[':dept_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (u.full_name LIKE :q OR u.username LIKE :q OR u.employee_code LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql .= ' ORDER BY u.is_active DESC, u.employee_code ASC';
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeEmployees(?int $departmentId = null, ?string $search = null): array
    {
        return $this->all([
            'role' => 'employee',
            'is_active' => 1,
            'department_id' => $departmentId,
            'search' => $search,
        ]);
    }

    public function nextEmployeeCode(string $role = 'employee'): string
    {
        $prefix = $role === 'admin' ? 'ADM' : 'EMP';
        $rows = $this->db->fetchAll(
            'SELECT employee_code FROM users WHERE employee_code LIKE :prefix',
            [':prefix' => $prefix . '-%']
        );

        $max = 0;
        foreach ($rows as $row) {
            $code = (string) ($row['employee_code'] ?? '');
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $code, $m)) {
                $num = (int) $m[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        return sprintf('%s-%03d', $prefix, $max + 1);
    }

    public function usernameExists(string $username, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE LOWER(username) = LOWER(:username)';
        $params = [':username' => trim($username)];
        if ($ignoreId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $ignoreId;
        }
        return (int) $this->db->fetchValue($sql, $params, 0) > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $role = ($data['role'] ?? 'employee') === 'admin' ? 'admin' : 'employee';
        $code = trim((string) ($data['employee_code'] ?? ''));
        if ($code === '') {
            $code = $this->nextEmployeeCode($role);
        }

        $this->db->execute(
            "INSERT INTO users (employee_code, full_name, username, email, phone, password_hash, role, department_id, position, is_active, created_at, updated_at)
             VALUES (:code, :full_name, :username, :email, :phone, :hash, :role, :dept_id, :position, :is_active, datetime('now', 'localtime'), datetime('now', 'localtime'))",
            [
                ':code' => $code,
                ':full_name' => $data['full_name'],
                ':username' => strtolower(trim((string) $data['username'])),
                ':email' => $data['email'] ?? '',
                ':phone' => $data['phone'] ?? '',
                ':hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                ':role' => $role,
                ':dept_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
                ':position' => $data['position'] ?? '',
                ':is_active' => isset($data['is_active']) ? (int) ((bool) $data['is_active']) : 1,
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE users
             SET full_name = :full_name,
                 username = :username,
                 email = :email,
                 phone = :phone,
                 role = :role,
                 department_id = :dept_id,
                 position = :position,
                 is_active = :is_active,
                 updated_at = datetime('now', 'localtime')
             WHERE id = :id",
            [
                ':id' => $id,
                ':full_name' => $data['full_name'],
                ':username' => strtolower(trim((string) $data['username'])),
                ':email' => $data['email'] ?? '',
                ':phone' => $data['phone'] ?? '',
                ':role' => ($data['role'] ?? 'employee') === 'admin' ? 'admin' : 'employee',
                ':dept_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
                ':position' => $data['position'] ?? '',
                ':is_active' => (int) ((bool) ($data['is_active'] ?? true)),
            ]
        );
    }

    public function updateProfile(int $id, string $fullName, string $email, string $phone): void
    {
        $this->db->execute(
            "UPDATE users
             SET full_name = :full_name, email = :email, phone = :phone, updated_at = datetime('now', 'localtime')
             WHERE id = :id",
            [
                ':id' => $id,
                ':full_name' => $fullName,
                ':email' => $email,
                ':phone' => $phone,
            ]
        );
    }

    public function updatePassword(int $id, string $newPassword): void
    {
        $this->db->execute(
            "UPDATE users SET password_hash = :hash, updated_at = datetime('now', 'localtime') WHERE id = :id",
            [
                ':id' => $id,
                ':hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ]
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute(
            "UPDATE users SET is_active = :active, updated_at = datetime('now', 'localtime') WHERE id = :id",
            [
                ':id' => $id,
                ':active' => $active ? 1 : 0,
            ]
        );
    }

    public function touchLastLogin(int $id): void
    {
        $this->db->execute(
            "UPDATE users SET last_login_at = datetime('now', 'localtime') WHERE id = :id",
            [':id' => $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM users WHERE id = :id', [':id' => $id]);
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1",
            [],
            0
        );
    }
}
