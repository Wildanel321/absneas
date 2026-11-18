<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class AuditLogModel
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param array<string, mixed> $details
     */
    public function record(
        ?int $actorUserId,
        string $action,
        string $entityType,
        string $entityId = '',
        array $details = [],
        string $ip = '',
        string $userAgent = ''
    ): void {
        $this->db->execute(
            "INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details_json, ip_address, user_agent, created_at)
             VALUES (:actor, :action, :etype, :eid, :details, :ip, :ua, datetime('now', 'localtime'))",
            [
                ':actor' => $actorUserId,
                ':action' => $action,
                ':etype' => $entityType,
                ':eid' => $entityId,
                ':details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                ':ip' => $ip,
                ':ua' => substr($userAgent, 0, 255),
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 100): array
    {
        return $this->db->fetchAll(
            'SELECT l.*, u.full_name AS actor_name, u.username AS actor_username
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.actor_user_id
             ORDER BY l.id DESC
             LIMIT ' . max(1, $limit)
        );
    }
}
