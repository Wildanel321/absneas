<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class AttendanceModel
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUserAndDate(int $userId, string $date): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM attendances WHERE user_id = :uid AND attendance_date = :d',
            [':uid' => $userId, ':d' => $date]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForUser(int $userId, ?string $month = null, int $limit = 60): array
    {
        $sql = 'SELECT * FROM attendances WHERE user_id = :uid';
        $params = [':uid' => $userId];
        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $sql .= ' AND attendance_date LIKE :m';
            $params[':m'] = $month . '-%';
        }
        $sql .= ' ORDER BY attendance_date DESC LIMIT :lim';
        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function recordCheckin(
        int $userId,
        string $date,
        string $checkinAt,
        float $lat,
        float $lng,
        float $accuracyM,
        float $distanceM,
        int $lateMinutes,
        string $status
    ): int {
        $this->db->execute(
            "INSERT INTO attendances (
                user_id, attendance_date, checkin_at, checkin_lat, checkin_lng,
                checkin_accuracy_m, checkin_distance_m, late_minutes, status, created_at, updated_at
            ) VALUES (
                :uid, :d, :at, :lat, :lng, :acc, :dist, :late, :status,
                datetime('now', 'localtime'), datetime('now', 'localtime')
            )",
            [
                ':uid' => $userId,
                ':d' => $date,
                ':at' => $checkinAt,
                ':lat' => $lat,
                ':lng' => $lng,
                ':acc' => round($accuracyM, 2),
                ':dist' => round($distanceM, 2),
                ':late' => $lateMinutes,
                ':status' => $status,
            ]
        );

        return $this->db->lastInsertId();
    }

    public function recordCheckout(
        int $attendanceId,
        string $checkoutAt,
        float $lat,
        float $lng,
        float $accuracyM,
        float $distanceM
    ): void {
        $this->db->execute(
            "UPDATE attendances
             SET checkout_at = :at,
                 checkout_lat = :lat,
                 checkout_lng = :lng,
                 checkout_accuracy_m = :acc,
                 checkout_distance_m = :dist,
                 updated_at = datetime('now', 'localtime')
             WHERE id = :id",
            [
                ':id' => $attendanceId,
                ':at' => $checkoutAt,
                ':lat' => $lat,
                ':lng' => $lng,
                ':acc' => round($accuracyM, 2),
                ':dist' => round($distanceM, 2),
            ]
        );
    }

    public function upsertLeaveOrSick(int $userId, string $date, string $type, string $notes = ''): void
    {
        $status = $type === 'sick' ? 'sick' : 'leave';
        $this->db->execute(
            "INSERT INTO attendances (user_id, attendance_date, status, notes, late_minutes, created_at, updated_at)
             VALUES (:uid, :d, :status, :notes, 0, datetime('now', 'localtime'), datetime('now', 'localtime'))
             ON CONFLICT(user_id, attendance_date) DO UPDATE SET
                 status = excluded.status,
                 notes = excluded.notes,
                 updated_at = datetime('now', 'localtime')",
            [
                ':uid' => $userId,
                ':d' => $date,
                ':status' => $status,
                ':notes' => $notes,
            ]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listFiltered(array $filters = []): array
    {
        $sql = 'SELECT a.*, u.employee_code, u.full_name, u.username, u.department_id, d.name AS department_name
                FROM attendances a
                INNER JOIN users u ON u.id = a.user_id
                LEFT JOIN departments d ON d.id = u.department_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['date'])) {
            $sql .= ' AND a.attendance_date = :date';
            $params[':date'] = $filters['date'];
        } elseif (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= ' AND a.attendance_date BETWEEN :start_date AND :end_date';
            $params[':start_date'] = $filters['start_date'];
            $params[':end_date'] = $filters['end_date'];
        } elseif (!empty($filters['month'])) {
            $sql .= ' AND a.attendance_date LIKE :month';
            $params[':month'] = $filters['month'] . '-%';
        }

        if (!empty($filters['department_id'])) {
            $sql .= ' AND u.department_id = :dept_id';
            $params[':dept_id'] = (int) $filters['department_id'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= ' AND a.user_id = :uid';
            $params[':uid'] = (int) $filters['user_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (u.full_name LIKE :q OR u.employee_code LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'not_checked_out') {
                $sql .= " AND a.checkin_at IS NOT NULL AND a.checkout_at IS NULL AND a.status IN ('on_time', 'late')";
            } else {
                $sql .= ' AND a.status = :status';
                $params[':status'] = $filters['status'];
            }
        }

        $sql .= ' ORDER BY a.attendance_date DESC, a.checkin_at DESC, u.full_name ASC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int) $filters['limit']);
        }

        return $this->db->fetchAll($sql, $params);
    }
}
