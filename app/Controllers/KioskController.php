<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Application;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\SettingModel;
use App\Services\AuthService;
use App\Services\QrSvgGenerator;
use App\Services\QrTokenService;

final class KioskController
{
    public function __construct(
        private Application $app,
        private Database $db,
        private AuthService $auth,
        private SettingModel $settings,
        private QrTokenService $qrTokens,
        private QrSvgGenerator $qrSvg
    ) {
    }

    public function display(Request $req): Response
    {
        $kioskKey = (string) ($req->query('key') ?? '');
        if ($kioskKey === '' && $this->auth->isAdmin()) {
            $kioskKey = $this->settings->get('kiosk_display_key', '');
        }

        $schedule = $this->qrTokens->scheduleInfo();
        $today = date('Y-m-d');
        $initialRecent = $this->getRecentAttendances($today);

        $html = $this->app->view()->render('kiosk/display', [
            'title' => 'Monitor QR Absensi - ' . $this->app->config('name'),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'schedule' => $schedule,
            'kioskKey' => $kioskKey,
            'isAdmin' => $this->auth->isAdmin(),
            'initialRecent' => $initialRecent,
        ]);

        return Response::html($html);
    }

    public function currentQr(Request $req): Response
    {
        $requestedMode = (string) $req->query('mode', 'auto');
        $forcedMode = null;

        // Hanya administrator yang login boleh memaksa override mode manual; monitor otomatis mengikuti jadwal
        if ($this->auth->isAdmin() && in_array($requestedMode, ['checkin', 'checkout'], true)) {
            $forcedMode = $requestedMode;
        }

        $issued = $this->qrTokens->issueToken($forcedMode, $req->ip());
        $svg = $this->qrSvg->renderSvg($issued['token'], 4, '#0F1D3A', '#FFFFFF');
        $schedule = $this->qrTokens->scheduleInfo();

        $today = date('Y-m-d');
        $todayStats = $this->db->fetchOne(
            "SELECT
                SUM(CASE WHEN checkin_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in,
                SUM(CASE WHEN checkout_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_out
             FROM attendances
             WHERE attendance_date = :d",
            [':d' => $today]
        );
        $recent = $this->getRecentAttendances($today);

        return Response::json([
            'ok' => true,
            'mode' => $issued['mode'],
            'mode_label' => $issued['mode'] === 'checkout' ? 'QR Check-out (Jam Pulang)' : 'QR Check-in (Jam Masuk)',
            'svg' => $svg,
            'issued_at' => $issued['issued_at'],
            'expires_at' => $issued['expires_at'],
            'refresh_seconds' => $issued['refresh_seconds'],
            'ttl_seconds' => $issued['ttl_seconds'],
            'server_time' => date('H:i:s'),
            'server_date' => date('Y-m-d'),
            'schedule' => $schedule,
            'today_counts' => [
                'checked_in' => (int) ($todayStats['checked_in'] ?? 0),
                'checked_out' => (int) ($todayStats['checked_out'] ?? 0),
            ],
            'recent_attendances' => $recent,
        ]);
    }

    public function recentActivity(Request $req): Response
    {
        $today = date('Y-m-d');
        $todayStats = $this->db->fetchOne(
            "SELECT
                SUM(CASE WHEN checkin_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in,
                SUM(CASE WHEN checkout_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_out
             FROM attendances
             WHERE attendance_date = :d",
            [':d' => $today]
        );
        $recent = $this->getRecentAttendances($today);

        return Response::json([
            'ok' => true,
            'server_time' => date('H:i:s'),
            'today_counts' => [
                'checked_in' => (int) ($todayStats['checked_in'] ?? 0),
                'checked_out' => (int) ($todayStats['checked_out'] ?? 0),
            ],
            'recent_attendances' => $recent,
        ]);
    }

    /**
     * Mengambil daftar aktivitas absensi terbaru hari ini (check-in & check-out).
     *
     * @return list<array<string, mixed>>
     */
    private function getRecentAttendances(string $today): array
    {
        $rows = $this->db->fetchAll(
            "SELECT 
                'in_' || a.id AS event_id,
                a.id AS attendance_id,
                u.id AS user_id,
                u.full_name,
                u.employee_code,
                'checkin' AS mode,
                'Masuk' AS mode_label,
                a.checkin_at AS event_time,
                a.status,
                a.late_minutes,
                a.checkin_photo
             FROM attendances a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.attendance_date = :today_in AND a.checkin_at IS NOT NULL

             UNION ALL

             SELECT 
                'out_' || a.id AS event_id,
                a.id AS attendance_id,
                u.id AS user_id,
                u.full_name,
                u.employee_code,
                'checkout' AS mode,
                'Pulang' AS mode_label,
                a.checkout_at AS event_time,
                a.status,
                a.late_minutes,
                a.checkin_photo
             FROM attendances a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.attendance_date = :today_out AND a.checkout_at IS NOT NULL

             ORDER BY event_time DESC
             LIMIT 6",
            [
                ':today_in' => $today,
                ':today_out' => $today,
            ]
        );

        $result = [];
        foreach ($rows as $r) {
            $photoUrl = null;
            if (!empty($r['checkin_photo'])) {
                $photoPath = (string) $this->app->config('storage_path') . '/photos/' . (string) $r['checkin_photo'];
                if (is_file($photoPath)) {
                    $photoUrl = '/photo/' . (string) $r['checkin_photo'];
                }
            }

            $timeStr = substr((string) ($r['event_time'] ?? ''), 11, 5);
            $statusText = 'Tepat Waktu';
            if ($r['mode'] === 'checkin' && $r['status'] === 'late') {
                $statusText = 'Terlambat ' . (int) $r['late_minutes'] . 'm';
            } elseif ($r['mode'] === 'checkout') {
                $statusText = 'Pulang';
            }

            $result[] = [
                'event_id' => (string) $r['event_id'],
                'user_id' => (int) $r['user_id'],
                'full_name' => (string) $r['full_name'],
                'employee_code' => (string) $r['employee_code'],
                'mode' => (string) $r['mode'],
                'mode_label' => (string) $r['mode_label'],
                'event_time' => (string) $r['event_time'],
                'time_formatted' => $timeStr !== '' ? $timeStr : '--:--',
                'status' => (string) $r['status'],
                'status_label' => $statusText,
                'late_minutes' => (int) $r['late_minutes'],
                'photo_url' => $photoUrl,
            ];
        }

        return $result;
    }
}
