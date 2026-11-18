<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class LeaveRequestModel
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
            'SELECT lr.*, u.full_name, u.employee_code, d.name AS department_name,
                    rv.full_name AS reviewer_name
             FROM leave_requests lr
             INNER JOIN users u ON u.id = lr.user_id
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN users rv ON rv.id = lr.reviewed_by
             WHERE lr.id = :id',
            [':id' => $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT lr.*, rv.full_name AS reviewer_name
             FROM leave_requests lr
             LEFT JOIN users rv ON rv.id = lr.reviewed_by
             WHERE lr.user_id = :uid
             ORDER BY lr.created_at DESC
             LIMIT ' . max(1, $limit),
            [':uid' => $userId]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT lr.*, u.full_name, u.employee_code, u.department_id, d.name AS department_name,
                       rv.full_name AS reviewer_name
                FROM leave_requests lr
                INNER JOIN users u ON u.id = lr.user_id
                LEFT JOIN departments d ON d.id = u.department_id
                LEFT JOIN users rv ON rv.id = lr.reviewed_by
                WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND lr.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND lr.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['department_id'])) {
            $sql .= ' AND u.department_id = :dept_id';
            $params[':dept_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['month'])) {
            $sql .= ' AND (lr.start_date LIKE :m OR lr.end_date LIKE :m)';
            $params[':m'] = $filters['month'] . '-%';
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (u.full_name LIKE :q OR u.employee_code LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql .= " ORDER BY CASE lr.status WHEN 'pending' THEN 0 ELSE 1 END, lr.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function hasOverlap(int $userId, string $startDate, string $endDate): bool
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM leave_requests
             WHERE user_id = :uid
               AND status IN ('pending', 'approved')
               AND NOT (end_date < :start OR start_date > :end)",
            [':uid' => $userId, ':start' => $startDate, ':end' => $endDate],
            0
        ) > 0;
    }

    public function create(int $userId, string $type, string $startDate, string $endDate, string $reason): int
    {
        $this->db->execute(
            "INSERT INTO leave_requests (user_id, type, start_date, end_date, reason, status, created_at, updated_at)
             VALUES (:uid, :type, :start, :end, :reason, 'pending', datetime('now', 'localtime'), datetime('now', 'localtime'))",
            [
                ':uid' => $userId,
                ':type' => $type === 'sick' ? 'sick' : 'leave',
                ':start' => $startDate,
                ':end' => $endDate,
                ':reason' => $reason,
            ]
        );
        return $this->db->lastInsertId();
    }

    public function review(int $id, string $decision, int $reviewerId, string $note = ''): void
    {
        $status = $decision === 'approved' ? 'approved' : 'rejected';
        $this->db->execute(
            "UPDATE leave_requests
             SET status = :status,
                 reviewed_by = :reviewer,
                 reviewed_at = datetime('now', 'localtime'),
                 review_note = :note,
                 updated_at = datetime('now', 'localtime')
             WHERE id = :id",
            [
                ':id' => $id,
                ':status' => $status,
                ':reviewer' => $reviewerId,
                ':note' => $note,
            ]
        );
    }

    public function countPending(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'", [], 0);
    }
}
