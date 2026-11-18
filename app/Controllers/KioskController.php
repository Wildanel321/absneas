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

        $html = $this->app->view()->render('kiosk/display', [
            'title' => 'Monitor QR Absensi - ' . $this->app->config('name'),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'schedule' => $schedule,
            'kioskKey' => $kioskKey,
            'isAdmin' => $this->auth->isAdmin(),
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
        ]);
    }
}
