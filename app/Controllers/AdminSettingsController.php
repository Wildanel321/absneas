<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLogModel;
use App\Models\HolidayModel;
use App\Models\LeaveRequestModel;
use App\Models\SettingModel;
use App\Services\AuthService;
use App\Services\BackupService;

final class AdminSettingsController
{
    public function __construct(
        private Application $app,
        private AuthService $auth,
        private SettingModel $settings,
        private HolidayModel $holidays,
        private LeaveRequestModel $leaves,
        private BackupService $backups,
        private AuditLogModel $audit
    ) {
    }

    public function index(Request $req): Response
    {
        $html = $this->app->view()->render('admin/settings', [
            'title' => 'Aturan Absensi & Lokasi - ' . $this->app->config('name'),
            'activeNav' => 'settings',
            'user' => $this->auth->user(),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'kioskKey' => $this->settings->get('kiosk_display_key', ''),
            'pendingLeaves' => $this->leaves->countPending(),
            'settings' => $this->settings->all(),
            'workingDays' => $this->settings->workingDays(),
            'holidays' => $this->holidays->all(),
        ], 'layouts/admin');

        return Response::html($html);
    }

    public function update(Request $req): Response
    {
        $officeName = Validator::str($req->input('office_name', ''), 100);
        $officeLat = $req->input('office_lat');
        $officeLng = $req->input('office_lng');
        $radiusM = (int) $req->input('geofence_radius_m', 100);
        $maxAccuracyM = (int) $req->input('max_gps_accuracy_m', 150);
        $workStart = Validator::str($req->input('work_start_time', '07:45'), 5);
        $workEnd = Validator::str($req->input('work_end_time', '14:00'), 5);
        $saturdayWorkEnd = Validator::str($req->input('saturday_work_end_time', '12:00'), 5);
        $lateTol = (int) $req->input('late_tolerance_minutes', 20);
        $checkoutBefore = (int) $req->input('checkout_open_before_minutes', 30);
        $qrRefresh = (int) $req->input('qr_refresh_seconds', 30);
        $qrTtl = (int) $req->input('qr_token_ttl_seconds', 45);
        $timezone = Validator::str($req->input('timezone', 'Asia/Jakarta'), 50);

        $rawDays = $req->input('working_days', []);
        $workingDays = [];
        if (is_array($rawDays)) {
            foreach ($rawDays as $d) {
                $i = (int) $d;
                if ($i >= 1 && $i <= 7 && !in_array($i, $workingDays, true)) {
                    $workingDays[] = $i;
                }
            }
        }
        sort($workingDays);

        if ($officeName === '' || !Validator::isLatitude($officeLat) || !Validator::isLongitude($officeLng)) {
            Session::flash('error', 'Nama kantor atau koordinat latitude/longitude tidak valid.');
            return Response::redirect('/admin/settings');
        }
        if ($radiusM < 10 || $radiusM > 5000) {
            Session::flash('error', 'Radius GPS harus antara 10 sampai 5000 meter.');
            return Response::redirect('/admin/settings');
        }
        if ($maxAccuracyM < 20 || $maxAccuracyM > 1000) {
            Session::flash('error', 'Batas akurasi GPS harus antara 20 sampai 1000 meter.');
            return Response::redirect('/admin/settings');
        }
        if (!Validator::isTime($workStart) || !Validator::isTime($workEnd) || !Validator::isTime($saturdayWorkEnd)) {
            Session::flash('error', 'Format jam masuk atau jam pulang tidak valid (HH:MM).');
            return Response::redirect('/admin/settings');
        }
        if ($workEnd <= $workStart || $saturdayWorkEnd <= $workStart) {
            Session::flash('error', 'Jam pulang harus lebih akhir daripada jam masuk.');
            return Response::redirect('/admin/settings');
        }
        if ($lateTol < 0 || $lateTol > 180) {
            Session::flash('error', 'Toleransi keterlambatan harus antara 0 sampai 180 menit.');
            return Response::redirect('/admin/settings');
        }
        if ($qrRefresh < 10 || $qrRefresh > 300) {
            Session::flash('error', 'Interval pergantian QR harus antara 10 sampai 300 detik.');
            return Response::redirect('/admin/settings');
        }
        if ($qrTtl < $qrRefresh + 5 || $qrTtl > 600) {
            $qrTtl = $qrRefresh + 15;
        }
        if ($workingDays === []) {
            Session::flash('error', 'Pilih minimal satu hari kerja.');
            return Response::redirect('/admin/settings');
        }
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Asia/Jakarta';
        }

        $payload = [
            'office_name' => $officeName,
            'office_lat' => sprintf('%.6f', (float) $officeLat),
            'office_lng' => sprintf('%.6f', (float) $officeLng),
            'geofence_radius_m' => $radiusM,
            'max_gps_accuracy_m' => $maxAccuracyM,
            'work_start_time' => $workStart,
            'work_end_time' => $workEnd,
            'saturday_work_end_time' => $saturdayWorkEnd,
            'late_tolerance_minutes' => $lateTol,
            'checkout_open_before_minutes' => max(0, min(240, $checkoutBefore)),
            'qr_refresh_seconds' => $qrRefresh,
            'qr_token_ttl_seconds' => $qrTtl,
            'working_days' => json_encode($workingDays) ?: '[1,2,3,4,5]',
            'timezone' => $timezone,
        ];

        $this->settings->setMany($payload);

        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'settings.update',
            'settings',
            'global',
            $payload,
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', 'Pengaturan absensi dan geofencing berhasil disimpan.');
        return Response::redirect('/admin/settings');
    }

    public function rotateKioskKey(Request $req): Response
    {
        $newKey = bin2hex(random_bytes(16));
        $this->settings->setMany(['kiosk_display_key' => $newKey]);

        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'settings.rotate_kiosk_key',
            'settings',
            'kiosk_display_key',
            [],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', 'Kunci URL monitor QR berhasil diganti. Perbarui alamat monitor jika menggunakan tautan kunci.');
        return Response::redirect('/admin/settings');
    }

    public function storeHoliday(Request $req): Response
    {
        $date = Validator::str($req->input('holiday_date', ''), 10);
        $desc = Validator::str($req->input('description', ''), 120);

        if (!Validator::isDate($date) || mb_strlen($desc, 'UTF-8') < 2) {
            Session::flash('error', 'Tanggal atau keterangan hari libur tidak valid.');
            return Response::redirect('/admin/settings');
        }

        $id = $this->holidays->add($date, $desc);
        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'holiday.create',
            'holiday',
            (string) $id,
            ['date' => $date, 'description' => $desc],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', "Hari libur {$date} ({$desc}) disimpan.");
        return Response::redirect('/admin/settings');
    }

    /**
     * @param array<string, string> $params
     */
    public function deleteHoliday(Request $req, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $this->holidays->delete($id);
        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'holiday.delete',
            'holiday',
            (string) $id,
            [],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', 'Hari libur berhasil dihapus.');
        return Response::redirect('/admin/settings');
    }

    public function backupsPage(Request $req): Response
    {
        $html = $this->app->view()->render('admin/backups', [
            'title' => 'Backup & Restore Database - ' . $this->app->config('name'),
            'activeNav' => 'backups',
            'user' => $this->auth->user(),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'kioskKey' => $this->settings->get('kiosk_display_key', ''),
            'pendingLeaves' => $this->leaves->countPending(),
            'backups' => $this->backups->listBackups(),
            'usbAvailable' => $this->backups->isUsbAvailable(),
            'usbPath' => $this->backups->usbPath(),
            'retentionDays' => $this->backups->retentionDays(),
        ], 'layouts/admin');

        return Response::html($html);
    }

    public function createBackup(Request $req): Response
    {
        try {
            $result = $this->backups->createBackup('manual');
            $actor = $this->auth->user();
            $this->audit->record(
                (int) $actor['id'],
                'backup.create',
                'database',
                $result['filename'],
                ['size_bytes' => $result['size_bytes'], 'usb_synced' => $result['usb_synced']],
                $req->ip(),
                $req->userAgent()
            );
            Session::flash('success', $result['message']);
        } catch (\Throwable $e) {
            $this->app->logger()->error('Backup gagal', ['error' => $e->getMessage()]);
            Session::flash('error', 'Gagal membuat backup: ' . $e->getMessage());
        }

        return Response::redirect('/admin/backups');
    }

    /**
     * @param array<string, string> $params
     */
    public function downloadBackup(Request $req, array $params): Response
    {
        $filename = (string) ($params['filename'] ?? '');
        $path = $this->backups->resolveLocalBackupPath($filename);
        if ($path === null) {
            Session::flash('error', 'File backup tidak ditemukan.');
            return Response::redirect('/admin/backups');
        }

        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'backup.download',
            'database',
            basename($path),
            [],
            $req->ip(),
            $req->userAgent()
        );

        return Response::download($path, basename($path), 'application/x-sqlite3');
    }

    public function restoreBackup(Request $req): Response
    {
        $actor = $this->auth->user();
        $selectedFile = Validator::str($req->input('backup_file', ''), 120);
        $uploaded = $req->file('backup_upload');

        $sourcePath = null;
        $label = '';

        if ($uploaded !== null && (int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) $uploaded['tmp_name'];
            $origName = (string) ($uploaded['name'] ?? 'upload.sqlite');
            if (!str_ends_with(strtolower($origName), '.sqlite')) {
                Session::flash('error', 'File unggahan harus berekstensi .sqlite.');
                return Response::redirect('/admin/backups');
            }
            $sourcePath = $tmp;
            $label = 'upload:' . basename($origName);
        } elseif ($selectedFile !== '') {
            $resolved = $this->backups->resolveLocalBackupPath($selectedFile);
            if ($resolved === null) {
                Session::flash('error', 'File backup yang dipilih tidak ditemukan.');
                return Response::redirect('/admin/backups');
            }
            $sourcePath = $resolved;
            $label = basename($resolved);
        } else {
            Session::flash('error', 'Pilih file backup lokal atau unggah file .sqlite terlebih dahulu.');
            return Response::redirect('/admin/backups');
        }

        try {
            $result = $this->backups->restoreFromFile($sourcePath);
            if ($result['ok']) {
                $this->audit->record(
                    (int) $actor['id'],
                    'backup.restore',
                    'database',
                    $label,
                    ['safety_backup' => $result['safety_backup'] ?? ''],
                    $req->ip(),
                    $req->userAgent()
                );
                Session::flash('success', $result['message']);
            } else {
                Session::flash('error', $result['message']);
            }
        } catch (\Throwable $e) {
            $this->app->logger()->error('Restore gagal', ['error' => $e->getMessage()]);
            Session::flash('error', 'Restore database gagal: ' . $e->getMessage());
        }

        return Response::redirect('/admin/backups');
    }

    public function auditLogs(Request $req): Response
    {
        $html = $this->app->view()->render('admin/audit-logs', [
            'title' => 'Log Audit Administrator - ' . $this->app->config('name'),
            'activeNav' => 'audit',
            'user' => $this->auth->user(),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'kioskKey' => $this->settings->get('kiosk_display_key', ''),
            'pendingLeaves' => $this->leaves->countPending(),
            'logs' => $this->audit->latest(150),
        ], 'layouts/admin');

        return Response::html($html);
    }
}
