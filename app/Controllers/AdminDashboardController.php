<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AttendanceModel;
use App\Models\AuditLogModel;
use App\Models\DepartmentModel;
use App\Models\LeaveRequestModel;
use App\Models\SettingModel;
use App\Services\AnalyticsService;
use App\Services\AttendanceService;
use App\Services\AuthService;

final class AdminDashboardController
{
    public function __construct(
        private Application $app,
        private AuthService $auth,
        private AnalyticsService $analytics,
        private AttendanceModel $attendances,
        private DepartmentModel $departments,
        private LeaveRequestModel $leaves,
        private SettingModel $settings,
        private AttendanceService $attendanceService,
        private AuditLogModel $audit
    ) {
    }

    public function index(Request $req): Response
    {
        $filters = [
            'date' => (string) $req->query('date', date('Y-m-d')),
            'month' => (string) $req->query('month', date('Y-m')),
            'department_id' => $req->query('department_id', ''),
            'search' => (string) $req->query('search', ''),
        ];

        $dashboard = $this->analytics->dashboardData($filters);
        $departments = $this->departments->all();

        $html = $this->app->view()->render('admin/dashboard', [
            'title' => 'Dashboard Administrator - ' . $this->app->config('name'),
            'activeNav' => 'dashboard',
            'user' => $this->auth->user(),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'kioskKey' => $this->settings->get('kiosk_display_key', ''),
            'dashboard' => $dashboard,
            'departments' => $departments,
            'pendingLeaves' => (int) ($dashboard['kpis']['pending_leaves'] ?? 0),
        ], 'layouts/admin');

        return Response::html($html);
    }

    public function apiData(Request $req): Response
    {
        $filters = [
            'date' => (string) $req->query('date', date('Y-m-d')),
            'month' => (string) $req->query('month', date('Y-m')),
            'department_id' => $req->query('department_id', ''),
            'search' => (string) $req->query('search', ''),
        ];

        return Response::json([
            'ok' => true,
            'data' => $this->analytics->dashboardData($filters),
        ]);
    }

    public function reports(Request $req): Response
    {
        $month = (string) $req->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $date = (string) $req->query('date', '');
        if ($date !== '' && !Validator::isDate($date)) {
            $date = '';
        }
        $departmentId = (string) $req->query('department_id', '');
        $status = (string) $req->query('status', '');
        $search = trim((string) $req->query('search', ''));

        $filters = [
            'date' => $date,
            'month' => $date === '' ? $month : '',
            'department_id' => $departmentId,
            'status' => $status,
            'search' => $search,
            'limit' => 500,
        ];

        $records = $this->attendances->listFiltered($filters);
        $departments = $this->departments->all();

        $html = $this->app->view()->render('admin/reports', [
            'title' => 'Laporan Kehadiran - ' . $this->app->config('name'),
            'activeNav' => 'reports',
            'user' => $this->auth->user(),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'kioskKey' => $this->settings->get('kiosk_display_key', ''),
            'pendingLeaves' => $this->leaves->countPending(),
            'records' => $records,
            'departments' => $departments,
            'filters' => [
                'month' => $month,
                'date' => $date,
                'department_id' => $departmentId,
                'status' => $status,
                'search' => $search,
            ],
        ], 'layouts/admin');

        return Response::html($html);
    }

    public function exportCsv(Request $req): Response
    {
        $month = (string) $req->query('month', date('Y-m'));
        $date = (string) $req->query('date', '');
        $filters = [
            'date' => Validator::isDate($date) ? $date : '',
            'month' => Validator::isDate($date) ? '' : $month,
            'department_id' => (string) $req->query('department_id', ''),
            'status' => (string) $req->query('status', ''),
            'search' => trim((string) $req->query('search', '')),
        ];

        $records = $this->attendances->listFiltered($filters);

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, [
            'Tanggal',
            'Kode Karyawan',
            'Nama',
            'Departemen',
            'Jam Masuk',
            'Jam Pulang',
            'Terlambat (Menit)',
            'Status',
            'Jarak Masuk (m)',
            'Akurasi GPS Masuk (m)',
            'Catatan',
        ]);

        foreach ($records as $r) {
            $desc = AttendanceService::describeStatus($r);
            fputcsv($fh, [
                $r['attendance_date'],
                $r['employee_code'],
                $r['full_name'],
                $r['department_name'] ?? '',
                $r['checkin_at'] ? substr((string) $r['checkin_at'], 11, 8) : '',
                $r['checkout_at'] ? substr((string) $r['checkout_at'], 11, 8) : '',
                $r['late_minutes'],
                $desc['label'],
                $r['checkin_distance_m'] ?? '',
                $r['checkin_accuracy_m'] ?? '',
                $r['notes'] ?? '',
            ]);
        }

        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'report.export_csv',
            'attendance',
            $date !== '' ? $date : $month,
            ['rows' => count($records)],
            $req->ip(),
            $req->userAgent()
        );

        $suffix = $date !== '' ? $date : $month;
        return Response::csv($csv, "laporan-absensi-{$suffix}.csv");
    }

    public function leaves(Request $req): Response
    {
        $filters = [
            'status' => (string) $req->query('status', ''),
            'type' => (string) $req->query('type', ''),
            'department_id' => (string) $req->query('department_id', ''),
            'search' => trim((string) $req->query('search', '')),
        ];

        $items = $this->leaves->all($filters);
        $departments = $this->departments->all();

        $html = $this->app->view()->render('admin/leaves', [
            'title' => 'Pengajuan Izin & Sakit - ' . $this->app->config('name'),
            'activeNav' => 'leaves',
            'user' => $this->auth->user(),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'kioskKey' => $this->settings->get('kiosk_display_key', ''),
            'pendingLeaves' => $this->leaves->countPending(),
            'items' => $items,
            'departments' => $departments,
            'filters' => $filters,
        ], 'layouts/admin');

        return Response::html($html);
    }

    /**
     * @param array<string, string> $params
     */
    public function reviewLeave(Request $req, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $decision = (string) $req->input('decision', '');
        $note = Validator::str($req->input('review_note', ''), 255);

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Session::flash('error', 'Keputusan tidak valid.');
            return Response::redirect('/admin/leaves');
        }

        $actor = $this->auth->user();
        $result = $this->attendanceService->reviewLeaveRequest($id, $decision, (int) $actor['id'], $note);

        if ($result['ok']) {
            $this->audit->record(
                (int) $actor['id'],
                'leave.' . $decision,
                'leave_request',
                (string) $id,
                ['note' => $note],
                $req->ip(),
                $req->userAgent()
            );
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        return Response::redirect('/admin/leaves');
    }
}
