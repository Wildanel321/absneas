<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AttendanceModel;
use App\Models\LeaveRequestModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\AttendanceService;
use App\Services\AuthService;
use App\Services\QrTokenService;

final class EmployeeController
{
    public function __construct(
        private Application $app,
        private AuthService $auth,
        private UserModel $users,
        private AttendanceModel $attendances,
        private LeaveRequestModel $leaves,
        private SettingModel $settings,
        private AttendanceService $attendanceService,
        private QrTokenService $qrTokens
    ) {
    }

    public function dashboard(Request $req): Response
    {
        $user = $this->auth->user();
        $today = date('Y-m-d');
        $month = date('Y-m');

        $todayAtt = $this->attendances->findByUserAndDate((int) $user['id'], $today);
        $todayDesc = AttendanceService::describeStatus($todayAtt);
        $monthHistory = $this->attendances->historyForUser((int) $user['id'], $month, 35);
        $recentLeaves = $this->leaves->forUser((int) $user['id'], 5);
        $schedule = $this->qrTokens->scheduleInfo();

        $summary = ['on_time' => 0, 'late' => 0, 'leave' => 0, 'sick' => 0];
        foreach ($monthHistory as $row) {
            $st = (string) $row['status'];
            if (isset($summary[$st])) {
                $summary[$st]++;
            }
        }

        $html = $this->app->view()->render('employee/dashboard', [
            'title' => 'Beranda Karyawan - ' . $this->app->config('name'),
            'activeNav' => 'home',
            'user' => $user,
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'today' => $today,
            'todayAtt' => $todayAtt,
            'todayDesc' => $todayDesc,
            'schedule' => $schedule,
            'summary' => $summary,
            'recentHistory' => array_slice($monthHistory, 0, 7),
            'recentLeaves' => $recentLeaves,
        ], 'layouts/employee');

        return Response::html($html);
    }

    public function scanPage(Request $req): Response
    {
        $user = $this->auth->user();
        $today = date('Y-m-d');
        $todayAtt = $this->attendances->findByUserAndDate((int) $user['id'], $today);
        $todayDesc = AttendanceService::describeStatus($todayAtt);
        $schedule = $this->qrTokens->scheduleInfo();

        $html = $this->app->view()->render('employee/scan', [
            'title' => 'Scan QR Absensi - ' . $this->app->config('name'),
            'activeNav' => 'scan',
            'user' => $user,
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'officeLat' => $this->settings->getFloat('office_lat', -6.2),
            'officeLng' => $this->settings->getFloat('office_lng', 106.816666),
            'radiusM' => $this->settings->getFloat('geofence_radius_m', 100.0),
            'maxAccuracyM' => $this->settings->getFloat('max_gps_accuracy_m', 150.0),
            'todayAtt' => $todayAtt,
            'todayDesc' => $todayDesc,
            'schedule' => $schedule,
        ], 'layouts/employee');

        return Response::html($html);
    }

    public function submitScan(Request $req): Response
    {
        $user = $this->auth->user();
        $token = trim((string) $req->input('token', ''));
        $lat = $req->input('latitude');
        $lng = $req->input('longitude');
        $accuracy = $req->input('accuracy');

        if ($token === '') {
            return Response::json(['ok' => false, 'message' => 'Token QR tidak ditemukan. Pindai ulang QR di monitor kantor.'], 422);
        }

        if (!Validator::isLatitude($lat) || !Validator::isLongitude($lng) || !is_numeric($accuracy)) {
            return Response::json([
                'ok' => false,
                'message' => 'Lokasi GPS wajib tersedia sebelum mengirim absensi. Pastikan izin lokasi aktif.',
            ], 422);
        }

        $result = $this->attendanceService->processScan(
            (int) $user['id'],
            $token,
            (float) $lat,
            (float) $lng,
            (float) $accuracy
        );

        return Response::json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status']);
    }

    public function history(Request $req): Response
    {
        $user = $this->auth->user();
        $month = (string) $req->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $records = $this->attendances->historyForUser((int) $user['id'], $month, 60);

        $html = $this->app->view()->render('employee/history', [
            'title' => 'Riwayat Kehadiran - ' . $this->app->config('name'),
            'activeNav' => 'history',
            'user' => $user,
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'month' => $month,
            'records' => $records,
        ], 'layouts/employee');

        return Response::html($html);
    }

    public function leavesPage(Request $req): Response
    {
        $user = $this->auth->user();
        $requests = $this->leaves->forUser((int) $user['id'], 30);

        $html = $this->app->view()->render('employee/leaves', [
            'title' => 'Pengajuan Izin & Sakit - ' . $this->app->config('name'),
            'activeNav' => 'leaves',
            'user' => $user,
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'requests' => $requests,
        ], 'layouts/employee');

        return Response::html($html);
    }

    public function submitLeave(Request $req): Response
    {
        $user = $this->auth->user();
        $type = (string) $req->input('type', 'leave');
        $startDate = Validator::str($req->input('start_date', ''), 10);
        $endDate = Validator::str($req->input('end_date', ''), 10);
        $reason = Validator::str($req->input('reason', ''), 500);

        if (!in_array($type, ['leave', 'sick'], true)) {
            Session::flash('error', 'Jenis pengajuan harus Izin atau Sakit.');
            return Response::redirect('/app/leaves');
        }
        if (!Validator::isDate($startDate) || !Validator::isDate($endDate)) {
            Session::flash('error', 'Format tanggal mulai atau selesai tidak valid.');
            return Response::redirect('/app/leaves');
        }
        if ($endDate < $startDate) {
            Session::flash('error', 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
            return Response::redirect('/app/leaves');
        }
        if (mb_strlen($reason, 'UTF-8') < 5) {
            Session::flash('error', 'Alasan pengajuan minimal 5 karakter.');
            return Response::redirect('/app/leaves');
        }
        if ($this->leaves->hasOverlap((int) $user['id'], $startDate, $endDate)) {
            Session::flash('error', 'Sudah ada pengajuan aktif pada rentang tanggal tersebut.');
            return Response::redirect('/app/leaves');
        }

        $this->leaves->create((int) $user['id'], $type, $startDate, $endDate, $reason);
        Session::flash('success', 'Pengajuan berhasil dikirim dan menunggu persetujuan administrator.');
        return Response::redirect('/app/leaves');
    }

    public function profilePage(Request $req): Response
    {
        $user = $this->auth->user();

        $html = $this->app->view()->render('employee/profile', [
            'title' => 'Profil Saya - ' . $this->app->config('name'),
            'activeNav' => 'profile',
            'user' => $user,
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
        ], 'layouts/employee');

        return Response::html($html);
    }

    public function updateProfile(Request $req): Response
    {
        $user = $this->auth->user();
        $fullName = Validator::str($req->input('full_name', ''), 100);
        $email = Validator::str($req->input('email', ''), 120);
        $phone = Validator::str($req->input('phone', ''), 30);

        if (mb_strlen($fullName, 'UTF-8') < 2) {
            Session::flash('error', 'Nama lengkap minimal 2 karakter.');
            return Response::redirect('/app/profile');
        }
        if (!Validator::isEmail($email)) {
            Session::flash('error', 'Format email tidak valid.');
            return Response::redirect('/app/profile');
        }

        $this->users->updateProfile((int) $user['id'], $fullName, $email, $phone);
        Session::flash('success', 'Profil berhasil diperbarui.');
        return Response::redirect('/app/profile');
    }

    public function changePassword(Request $req): Response
    {
        $user = $this->auth->user();
        $current = (string) $req->input('current_password', '');
        $next = (string) $req->input('new_password', '');
        $confirm = (string) $req->input('confirm_password', '');

        if (!password_verify($current, (string) $user['password_hash'])) {
            Session::flash('error', 'Password saat ini tidak sesuai.');
            return Response::redirect('/app/profile');
        }
        if (strlen($next) < 8) {
            Session::flash('error', 'Password baru minimal 8 karakter.');
            return Response::redirect('/app/profile');
        }
        if ($next !== $confirm) {
            Session::flash('error', 'Konfirmasi password baru tidak cocok.');
            return Response::redirect('/app/profile');
        }

        $this->users->updatePassword((int) $user['id'], $next);
        Session::flash('success', 'Password berhasil diperbarui.');
        return Response::redirect('/app/profile');
    }
}
