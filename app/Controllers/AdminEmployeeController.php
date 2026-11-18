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
use App\Models\UserModel;
use App\Services\AuthService;

final class AdminEmployeeController
{
    public function __construct(
        private Application $app,
        private AuthService $auth,
        private UserModel $users,
        private DepartmentModel $departments,
        private AttendanceModel $attendances,
        private LeaveRequestModel $leaves,
        private SettingModel $settings,
        private AuditLogModel $audit
    ) {
    }

    public function index(Request $req): Response
    {
        $filters = [
            'department_id' => (string) $req->query('department_id', ''),
            'role' => (string) $req->query('role', ''),
            'is_active' => (string) $req->query('is_active', ''),
            'search' => trim((string) $req->query('search', '')),
        ];

        $employees = $this->users->all($filters);
        $departments = $this->departments->all();

        $html = $this->app->view()->render('admin/employees', [
            'title' => 'Manajemen Karyawan - ' . $this->app->config('name'),
            'activeNav' => 'employees',
            'user' => $this->auth->user(),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'kioskKey' => $this->settings->get('kiosk_display_key', ''),
            'pendingLeaves' => $this->leaves->countPending(),
            'employees' => $employees,
            'departments' => $departments,
            'filters' => $filters,
            'nextCode' => $this->users->nextEmployeeCode('employee'),
        ], 'layouts/admin');

        return Response::html($html);
    }

    public function store(Request $req): Response
    {
        $fullName = Validator::str($req->input('full_name', ''), 100);
        $username = strtolower(Validator::str($req->input('username', ''), 40));
        $password = (string) $req->input('password', '');
        $email = Validator::str($req->input('email', ''), 120);
        $phone = Validator::str($req->input('phone', ''), 30);
        $role = (string) $req->input('role', 'employee');
        $departmentId = (string) $req->input('department_id', '');
        $position = Validator::str($req->input('position', ''), 80);

        if (mb_strlen($fullName, 'UTF-8') < 2) {
            Session::flash('error', 'Nama lengkap minimal 2 karakter.');
            return Response::redirect('/admin/employees');
        }
        if (!Validator::isUsername($username)) {
            Session::flash('error', 'Username hanya boleh berisi huruf, angka, titik, underscore, atau tanda hubung (3 sampai 40 karakter).');
            return Response::redirect('/admin/employees');
        }
        if ($this->users->usernameExists($username)) {
            Session::flash('error', 'Username tersebut sudah digunakan.');
            return Response::redirect('/admin/employees');
        }
        if (strlen($password) < 8) {
            Session::flash('error', 'Password awal minimal 8 karakter.');
            return Response::redirect('/admin/employees');
        }
        if (!Validator::isEmail($email)) {
            Session::flash('error', 'Format email tidak valid.');
            return Response::redirect('/admin/employees');
        }
        if (!in_array($role, ['admin', 'employee'], true)) {
            $role = 'employee';
        }

        $id = $this->users->create([
            'full_name' => $fullName,
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'department_id' => $departmentId !== '' ? (int) $departmentId : null,
            'position' => $position,
            'is_active' => 1,
        ]);

        $created = $this->users->findById($id);
        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'employee.create',
            'user',
            (string) $id,
            ['employee_code' => $created['employee_code'] ?? '', 'username' => $username, 'role' => $role],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', "Akun {$fullName} ({$created['employee_code']}) berhasil ditambahkan.");
        return Response::redirect('/admin/employees');
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $req, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $employee = $this->users->findById($id);
        if ($employee === null) {
            Session::flash('error', 'Karyawan tidak ditemukan.');
            return Response::redirect('/admin/employees');
        }

        $month = (string) $req->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $history = $this->attendances->historyForUser($id, $month, 60);
        $departments = $this->departments->all();

        $html = $this->app->view()->render('admin/employee-detail', [
            'title' => $employee['full_name'] . ' - Manajemen Karyawan',
            'activeNav' => 'employees',
            'user' => $this->auth->user(),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'kioskKey' => $this->settings->get('kiosk_display_key', ''),
            'pendingLeaves' => $this->leaves->countPending(),
            'employee' => $employee,
            'departments' => $departments,
            'history' => $history,
            'month' => $month,
        ], 'layouts/admin');

        return Response::html($html);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $req, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $target = $this->users->findById($id);
        if ($target === null) {
            Session::flash('error', 'Karyawan tidak ditemukan.');
            return Response::redirect('/admin/employees');
        }

        $fullName = Validator::str($req->input('full_name', ''), 100);
        $username = strtolower(Validator::str($req->input('username', ''), 40));
        $email = Validator::str($req->input('email', ''), 120);
        $phone = Validator::str($req->input('phone', ''), 30);
        $role = (string) $req->input('role', 'employee');
        $departmentId = (string) $req->input('department_id', '');
        $position = Validator::str($req->input('position', ''), 80);
        $isActive = $req->input('is_active') === '1' || $req->input('is_active') === 'on';

        if (mb_strlen($fullName, 'UTF-8') < 2 || !Validator::isUsername($username)) {
            Session::flash('error', 'Nama lengkap atau username tidak valid.');
            return Response::redirect('/admin/employees/' . $id);
        }
        if ($this->users->usernameExists($username, $id)) {
            Session::flash('error', 'Username tersebut sudah digunakan akun lain.');
            return Response::redirect('/admin/employees/' . $id);
        }
        if (!Validator::isEmail($email)) {
            Session::flash('error', 'Format email tidak valid.');
            return Response::redirect('/admin/employees/' . $id);
        }

        // Cegah menonaktifkan atau menurunkan role admin terakhir
        if ($target['role'] === 'admin' && ((int) $target['is_active'] === 1)) {
            if (($role !== 'admin' || !$isActive) && $this->users->countActiveAdmins() <= 1) {
                Session::flash('error', 'Tidak dapat menurunkan role atau menonaktifkan satu-satunya administrator aktif.');
                return Response::redirect('/admin/employees/' . $id);
            }
        }

        $this->users->update($id, [
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'role' => $role === 'admin' ? 'admin' : 'employee',
            'department_id' => $departmentId !== '' ? (int) $departmentId : null,
            'position' => $position,
            'is_active' => $isActive ? 1 : 0,
        ]);

        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'employee.update',
            'user',
            (string) $id,
            ['username' => $username, 'role' => $role, 'is_active' => $isActive],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', 'Data karyawan berhasil diperbarui.');
        return Response::redirect('/admin/employees/' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function resetPassword(Request $req, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $target = $this->users->findById($id);
        if ($target === null) {
            Session::flash('error', 'Karyawan tidak ditemukan.');
            return Response::redirect('/admin/employees');
        }

        $newPass = (string) $req->input('new_password', '');
        if (strlen($newPass) < 8) {
            Session::flash('error', 'Password baru minimal 8 karakter.');
            return Response::redirect('/admin/employees/' . $id);
        }

        $this->users->updatePassword($id, $newPass);

        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'employee.reset_password',
            'user',
            (string) $id,
            ['username' => $target['username']],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', "Password untuk {$target['full_name']} berhasil direset.");
        return Response::redirect('/admin/employees/' . $id);
    }

    /**
     * @param array<string, string> $params
     */
    public function toggleActive(Request $req, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $target = $this->users->findById($id);
        if ($target === null) {
            Session::flash('error', 'Karyawan tidak ditemukan.');
            return Response::redirect('/admin/employees');
        }

        $actor = $this->auth->user();
        $nextActive = (int) $target['is_active'] !== 1;
        if (!$nextActive && (int) $actor['id'] === $id) {
            Session::flash('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            return Response::redirect('/admin/employees');
        }
        if (!$nextActive && $target['role'] === 'admin' && $this->users->countActiveAdmins() <= 1) {
            Session::flash('error', 'Tidak dapat menonaktifkan administrator aktif terakhir.');
            return Response::redirect('/admin/employees');
        }

        $this->users->setActive($id, $nextActive);
        $this->audit->record(
            (int) $actor['id'],
            $nextActive ? 'employee.activate' : 'employee.deactivate',
            'user',
            (string) $id,
            ['username' => $target['username']],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', $nextActive ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.');
        return Response::redirect('/admin/employees');
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $req, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $target = $this->users->findById($id);
        if ($target === null) {
            Session::flash('error', 'Karyawan tidak ditemukan.');
            return Response::redirect('/admin/employees');
        }

        $actor = $this->auth->user();
        if ((int) $actor['id'] === $id) {
            Session::flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
            return Response::redirect('/admin/employees');
        }
        if ($target['role'] === 'admin' && (int) $target['is_active'] === 1 && $this->users->countActiveAdmins() <= 1) {
            Session::flash('error', 'Tidak dapat menghapus administrator aktif terakhir.');
            return Response::redirect('/admin/employees');
        }

        $this->users->delete($id);
        $this->audit->record(
            (int) $actor['id'],
            'employee.delete',
            'user',
            (string) $id,
            ['employee_code' => $target['employee_code'], 'username' => $target['username']],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', "Akun {$target['full_name']} berhasil dihapus.");
        return Response::redirect('/admin/employees');
    }

    public function storeDepartment(Request $req): Response
    {
        $name = Validator::str($req->input('name', ''), 60);
        if (mb_strlen($name, 'UTF-8') < 2) {
            Session::flash('error', 'Nama departemen minimal 2 karakter.');
            return Response::redirect('/admin/employees');
        }
        if ($this->departments->existsByName($name)) {
            Session::flash('error', 'Nama departemen sudah ada.');
            return Response::redirect('/admin/employees');
        }

        $id = $this->departments->create($name);
        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'department.create',
            'department',
            (string) $id,
            ['name' => $name],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', "Departemen {$name} ditambahkan.");
        return Response::redirect('/admin/employees');
    }

    /**
     * @param array<string, string> $params
     */
    public function deleteDepartment(Request $req, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $this->departments->delete($id);
        $actor = $this->auth->user();
        $this->audit->record(
            (int) $actor['id'],
            'department.delete',
            'department',
            (string) $id,
            [],
            $req->ip(),
            $req->userAgent()
        );

        Session::flash('success', 'Departemen dihapus.');
        return Response::redirect('/admin/employees');
    }
}
