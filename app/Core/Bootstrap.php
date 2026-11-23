<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminDashboardController;
use App\Controllers\AdminEmployeeController;
use App\Controllers\AdminSettingsController;
use App\Controllers\AuthController;
use App\Controllers\EmployeeController;
use App\Controllers\KioskController;
use App\Middleware\AuthMiddleware;
use App\Models\AttendanceModel;
use App\Models\AuditLogModel;
use App\Models\DepartmentModel;
use App\Models\HolidayModel;
use App\Models\LeaveRequestModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\AnalyticsService;
use App\Services\AttendanceService;
use App\Services\AuthService;
use App\Services\BackupService;
use App\Services\GeoService;
use App\Services\QrSvgGenerator;
use App\Services\QrTokenService;

final class Bootstrap
{
    /**
     * @return array{app: Application, router: Router}
     */
    public static function create(array $config): array
    {
        $app = new Application($config);
        $db = $app->db();

        // Models
        $users = new UserModel($db);
        $departments = new DepartmentModel($db);
        $settings = new SettingModel($db);
        $holidays = new HolidayModel($db);
        $attendances = new AttendanceModel($db);
        $leaves = new LeaveRequestModel($db);
        $audit = new AuditLogModel($db);

        // Services
        $auth = new AuthService(
            $db,
            $users,
            (int) $app->config('login_max_attempts', 5),
            (int) $app->config('login_window_minutes', 15)
        );
        $geo = new GeoService();
        $qrTokens = new QrTokenService($db, $settings, $app->appKey());
        $qrSvg = new QrSvgGenerator();
        $attendanceService = new AttendanceService($db, $attendances, $settings, $holidays, $leaves, $qrTokens, $geo);
        $analytics = new AnalyticsService($db, $users, $settings, $holidays);
        $backupService = new BackupService(
            (string) $app->config('db_path'),
            (string) $app->config('backup_local_dir'),
            (string) $app->config('backup_usb_dir'),
            (int) $app->config('backup_retention_days', 30)
        );

        // Middleware
        $mw = new AuthMiddleware($auth, $settings);
        $authOnly = [$mw->requireAuth()];
        $authCsrf = [$mw->requireAuth(), $mw->verifyCsrf()];
        $adminOnly = [$mw->requireAdmin()];
        $adminCsrf = [$mw->requireAdmin(), $mw->verifyCsrf()];
        $csrfOnly = [$mw->verifyCsrf()];
        $kioskOnly = [$mw->requireKioskAccess()];

        // Controllers
        $authCtrl = new AuthController($app, $auth, $settings, $audit);
        $empCtrl = new EmployeeController($app, $auth, $users, $attendances, $leaves, $settings, $attendanceService, $qrTokens);
        $kioskCtrl = new KioskController($app, $db, $auth, $settings, $qrTokens, $qrSvg);
        $adminDashCtrl = new AdminDashboardController($app, $auth, $analytics, $attendances, $departments, $leaves, $settings, $attendanceService, $audit);
        $adminEmpCtrl = new AdminEmployeeController($app, $auth, $users, $departments, $attendances, $leaves, $settings, $audit);
        $adminSetCtrl = new AdminSettingsController($app, $auth, $settings, $holidays, $leaves, $backupService, $audit);

        // Router
        $router = new Router();

        // Healthcheck (ringan untuk Cloudflare Tunnel / systemd)
        $router->get('/health', static function () use ($db): Response {
            $ok = (int) $db->fetchValue('SELECT 1', [], 0) === 1;
            return Response::json(['ok' => $ok, 'time' => date('c')], $ok ? 200 : 503);
        });

        // Auth routes
        $router->get('/', [$authCtrl, 'home']);
        $router->get('/login', [$authCtrl, 'showLogin']);
        $router->post('/login', [$authCtrl, 'login'], $csrfOnly);
        $router->post('/logout', [$authCtrl, 'logout'], $authCsrf);

        // Employee PWA routes
        $router->get('/app', [$empCtrl, 'dashboard'], $authOnly);
        $router->get('/app/scan', [$empCtrl, 'scanPage'], $authOnly);
        $router->get('/app/history', [$empCtrl, 'history'], $authOnly);
        $router->get('/app/leaves', [$empCtrl, 'leavesPage'], $authOnly);
        $router->post('/app/leaves', [$empCtrl, 'submitLeave'], $authCsrf);
        $router->get('/app/profile', [$empCtrl, 'profilePage'], $authOnly);
        $router->post('/app/profile', [$empCtrl, 'updateProfile'], $authCsrf);
        $router->post('/app/profile/password', [$empCtrl, 'changePassword'], $authCsrf);
        $router->post('/api/attendance/scan', [$empCtrl, 'submitScan'], $authCsrf);
        $router->get('/photo/{folder}/{file}', [$empCtrl, 'servePhoto'], $authOnly);

        // Kiosk QR Display routes
        $router->get('/kiosk', [$kioskCtrl, 'display'], $kioskOnly);
        $router->get('/api/kiosk/qr', [$kioskCtrl, 'currentQr'], $kioskOnly);

        // Admin Dashboard, Reports & Leaves
        $router->get('/admin', [$adminDashCtrl, 'index'], $adminOnly);
        $router->get('/api/admin/dashboard', [$adminDashCtrl, 'apiData'], $adminOnly);
        $router->get('/admin/reports', [$adminDashCtrl, 'reports'], $adminOnly);
        $router->get('/admin/reports/export', [$adminDashCtrl, 'exportCsv'], $adminOnly);
        $router->get('/admin/leaves', [$adminDashCtrl, 'leaves'], $adminOnly);
        $router->post('/admin/leaves/{id}/review', [$adminDashCtrl, 'reviewLeave'], $adminCsrf);

        // Admin Employee & Department CRUD
        $router->get('/admin/employees', [$adminEmpCtrl, 'index'], $adminOnly);
        $router->post('/admin/employees', [$adminEmpCtrl, 'store'], $adminCsrf);
        $router->get('/admin/employees/{id}', [$adminEmpCtrl, 'show'], $adminOnly);
        $router->post('/admin/employees/{id}/update', [$adminEmpCtrl, 'update'], $adminCsrf);
        $router->post('/admin/employees/{id}/reset-password', [$adminEmpCtrl, 'resetPassword'], $adminCsrf);
        $router->post('/admin/employees/{id}/toggle', [$adminEmpCtrl, 'toggleActive'], $adminCsrf);
        $router->post('/admin/employees/{id}/delete', [$adminEmpCtrl, 'delete'], $adminCsrf);
        $router->post('/admin/departments', [$adminEmpCtrl, 'storeDepartment'], $adminCsrf);
        $router->post('/admin/departments/{id}/delete', [$adminEmpCtrl, 'deleteDepartment'], $adminCsrf);

        // Admin Settings, Holidays, Backups, Audit
        $router->get('/admin/settings', [$adminSetCtrl, 'index'], $adminOnly);
        $router->post('/admin/settings', [$adminSetCtrl, 'update'], $adminCsrf);
        $router->post('/admin/settings/rotate-kiosk', [$adminSetCtrl, 'rotateKioskKey'], $adminCsrf);
        $router->post('/admin/holidays', [$adminSetCtrl, 'storeHoliday'], $adminCsrf);
        $router->post('/admin/holidays/{id}/delete', [$adminSetCtrl, 'deleteHoliday'], $adminCsrf);
        $router->get('/admin/backups', [$adminSetCtrl, 'backupsPage'], $adminOnly);
        $router->post('/admin/backups/create', [$adminSetCtrl, 'createBackup'], $adminCsrf);
        $router->get('/admin/backups/download/{filename}', [$adminSetCtrl, 'downloadBackup'], $adminOnly);
        $router->post('/admin/backups/restore', [$adminSetCtrl, 'restoreBackup'], $adminCsrf);
        $router->get('/admin/audit-logs', [$adminSetCtrl, 'auditLogs'], $adminOnly);

        return ['app' => $app, 'router' => $router];
    }
}
