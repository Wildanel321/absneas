<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Autoloader;
use App\Core\Bootstrap;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
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

$basePath = dirname(__DIR__);
require_once $basePath . '/app/Core/Autoloader.php';
Autoloader::register($basePath);

$passed = 0;
$failed = 0;

function assertTrue(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        fwrite(STDOUT, "  [PASS] {$label}\n");
    } else {
        $failed++;
        fwrite(STDERR, "  [FAIL] {$label}\n");
    }
}

function assertEquals(mixed $expected, mixed $actual, string $label): void
{
    assertTrue($expected === $actual, $label . ($expected === $actual ? '' : ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')'));
}

// Setup isolated temp directory for tests
$tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'smart_att_test_' . bin2hex(random_bytes(4));
$dbPath = $tmpRoot . DIRECTORY_SEPARATOR . 'test.sqlite';
$localBackupDir = $tmpRoot . DIRECTORY_SEPARATOR . 'backups_local';
$usbBackupDir = $tmpRoot . DIRECTORY_SEPARATOR . 'backups_usb';
$logsDir = $tmpRoot . DIRECTORY_SEPARATOR . 'logs';
mkdir($tmpRoot, 0777, true);
mkdir($usbBackupDir, 0777, true);

$_SESSION = [];
$config = [
    'name' => 'Smart Attendance Test',
    'env' => 'testing',
    'debug' => true,
    'url' => 'http://localhost:8000',
    'timezone' => 'Asia/Jakarta',
    'key' => bin2hex(random_bytes(32)),
    'base_path' => $basePath,
    'db_path' => $dbPath,
    'storage_path' => $tmpRoot,
    'logs_path' => $logsDir,
    'views_path' => $basePath . '/resources/views',
    'migrations_path' => $basePath . '/database/migrations',
    'session_lifetime_minutes' => 60,
    'login_max_attempts' => 5,
    'login_window_minutes' => 15,
    'trust_proxy_headers' => true,
    'kiosk_key' => 'test-kiosk-key-1234',
    'backup_local_dir' => $localBackupDir,
    'backup_usb_dir' => $usbBackupDir,
    'backup_retention_days' => 30,
];

try {
    // =========================================================================
    // TAHAP 1: Database Migration, Auth, Rate Limiting, CSRF, Employee CRUD
    // =========================================================================
    fwrite(STDOUT, "\n=== TAHAP 1: Fondasi, Database, Autentikasi, CRUD Karyawan ===\n");
    $app = new Application($config);
    $db = $app->db();
    $sqlFiles = glob($basePath . '/database/migrations/*.sql') ?: [];
    sort($sqlFiles, SORT_STRING);
    foreach ($sqlFiles as $sqlFile) {
        $sql = file_get_contents($sqlFile);
        assertTrue(is_string($sql) && $sql !== '', 'File migrasi ' . basename($sqlFile) . ' terbaca');
        $db->pdo()->exec($sql);
    }

    $walMode = strtolower((string) $db->fetchValue('PRAGMA journal_mode'));
    assertEquals('wal', $walMode, 'SQLite journal_mode aktif WAL');
    assertEquals(1, (int) $db->fetchValue('PRAGMA foreign_keys'), 'SQLite foreign_keys ON');

    $users = new UserModel($db);
    $depts = new DepartmentModel($db);
    $settings = new SettingModel($db);
    $holidays = new HolidayModel($db);
    $attendances = new AttendanceModel($db);
    $leaves = new LeaveRequestModel($db);
    $audit = new AuditLogModel($db);

    $settings->setMany(['kiosk_display_key' => 'test-kiosk-key-1234']);

    $adminId = $users->create([
        'employee_code' => 'ADM-001',
        'full_name' => 'Admin Uji',
        'username' => 'admin',
        'password' => 'AdminPass123!',
        'role' => 'admin',
        'is_active' => 1,
    ]);
    assertTrue($adminId > 0, 'Pembuatan akun admin berhasil');

    $emp1Id = $users->create([
        'full_name' => 'Karyawan Satu',
        'username' => 'emp.satu',
        'password' => 'EmpPass123!',
        'role' => 'employee',
        'department_id' => 1,
        'position' => 'Staf',
    ]);
    $emp1 = $users->findById($emp1Id);
    assertEquals('EMP-001', $emp1['employee_code'], 'Kode karyawan otomatis EMP-001');
    assertTrue(password_verify('EmpPass123!', (string) $emp1['password_hash']), 'Password tersimpan memakai password_hash()');

    $emp2Id = $users->create([
        'full_name' => 'Karyawan Dua',
        'username' => 'emp.dua',
        'password' => 'EmpPass123!',
        'role' => 'employee',
        'department_id' => 2,
    ]);
    $emp2 = $users->findById($emp2Id);
    assertEquals('EMP-002', $emp2['employee_code'], 'Kode karyawan kedua otomatis EMP-002');

    // CSRF
    $csrfToken = Csrf::token();
    assertTrue(Csrf::verify($csrfToken), 'CSRF token valid diterima');
    assertTrue(!Csrf::verify('token-palsu'), 'CSRF token palsu ditolak');

    // Rate limiting login (5 gagal => percobaan ke-6 diblokir 429)
    $auth = new AuthService($db, $users, 5, 15);
    for ($i = 1; $i <= 5; $i++) {
        $res = $auth->attempt('emp.satu', 'SalahPassword!', '10.0.0.50');
        assertEquals(401, $res['status'], "Login gagal ke-{$i} mengembalikan 401");
    }
    $blocked = $auth->attempt('emp.satu', 'EmpPass123!', '10.0.0.50');
    assertEquals(429, $blocked['status'], 'Percobaan ke-6 diblokir rate limiter (HTTP 429)');

    // Login sukses dari IP lain + akun nonaktif ditolak
    $users->setActive($emp2Id, false);
    $inactiveLogin = $auth->attempt('emp.dua', 'EmpPass123!', '10.0.0.51');
    assertEquals(403, $inactiveLogin['status'], 'Akun nonaktif ditolak saat login (HTTP 403)');
    $users->setActive($emp2Id, true);

    $goodLogin = $auth->attempt('emp.dua', 'EmpPass123!', '10.0.0.52');
    assertTrue($goodLogin['ok'], 'Login valid berhasil');

    // =========================================================================
    // TAHAP 2 & 3: QR Dinamis, Jadwal Otomatis, GPS Haversine, Check-in & Check-out
    // =========================================================================
    fwrite(STDOUT, "\n=== TAHAP 2 & 3: QR Dinamis, Geofencing Haversine, Check-in & Check-out ===\n");
    $geo = new GeoService();
    $distZero = $geo->distanceMeters(-6.200000, 106.816666, -6.200000, 106.816666);
    assertTrue($distZero < 0.01, 'Haversine titik identik ~0 meter');

    // ~111 meter ke utara (0.001 derajat lintang)
    $dist111 = $geo->distanceMeters(-6.200000, 106.816666, -6.199000, 106.816666);
    assertTrue($dist111 > 105.0 && $dist111 < 116.0, 'Haversine 0.001 deg lintang ~111 meter (' . round($dist111, 1) . 'm)');

    $outside = $geo->validateLocation(-6.198000, 106.816666, 20.0, -6.200000, 106.816666, 100.0, 150.0);
    assertTrue(!$outside['ok'], 'Lokasi di luar radius 100m ditolak');

    $lowAcc = $geo->validateLocation(-6.200000, 106.816666, 260.0, -6.200000, 106.816666, 100.0, 150.0);
    assertTrue(!$lowAcc['ok'], 'Akurasi GPS terlalu rendah (260m > 150m) ditolak');

    $qrTokens = new QrTokenService($db, $settings, $app->appKey());
    $qrSvg = new QrSvgGenerator();
    $attService = new AttendanceService($db, $attendances, $settings, $holidays, $leaves, $qrTokens, $geo);

    // Uji jadwal khusus: Senin-Jumat pulang 14:00 (checkout 13:30), Sabtu pulang 12:00 (checkout 11:30)
    // Jam masuk 07:45 dengan toleransi 20 menit (tepat waktu sampai 08:05)
    $mondayMorning = strtotime('2026-11-16 07:55:00'); // Senin pagi
    $mondayDeadline = strtotime('2026-11-16 08:05:00'); // Senin pas batas 08:05
    $mondayLate = strtotime('2026-11-16 08:25:00');    // Senin terlambat (melewati 08:05)
    $mondayEvening = strtotime('2026-11-16 13:45:00'); // Senin sore (pulang 14:00, switch 13:30 => mode checkout)
    $saturdayMorning = strtotime('2026-11-21 10:00:00'); // Sabtu pagi => mode checkin
    $saturdayNoon = strtotime('2026-11-21 11:45:00');    // Sabtu siang (pulang 12:00, switch 11:30 => mode checkout)

    assertEquals('checkin', $qrTokens->resolveScheduledMode($mondayMorning), 'Jadwal 07:55 otomatis mode checkin');
    assertEquals('checkout', $qrTokens->resolveScheduledMode($mondayEvening), 'Jadwal Senin 13:45 otomatis mode checkout (pulang 14:00)');
    assertEquals('checkin', $qrTokens->resolveScheduledMode($saturdayMorning), 'Jadwal Sabtu 10:00 otomatis mode checkin');
    assertEquals('checkout', $qrTokens->resolveScheduledMode($saturdayNoon), 'Jadwal Sabtu 11:45 otomatis mode checkout (pulang 12:00)');

    $issuedIn = $qrTokens->issueToken('checkin', '127.0.0.1', $mondayMorning);
    $svgOut = $qrSvg->renderSvg($issuedIn['token']);
    assertTrue(str_starts_with($svgOut, '<svg') && str_contains($svgOut, '<path'), 'QrSvgGenerator menghasilkan SVG valid');

    // Karyawan 1 check-in tepat waktu (07:55) di dalam radius dengan foto selfie
    $fakePhoto = '2026-11/selfie_emp1.jpg';
    $ci1 = $attService->processScan($emp1Id, $issuedIn['token'], -6.200100, 106.816666, 15.0, $fakePhoto, $mondayMorning);
    assertTrue($ci1['ok'], 'Check-in tepat waktu berhasil');
    assertEquals('on_time', $ci1['data']['status'], 'Status tercatat on_time');
    assertEquals(0, $ci1['data']['late_minutes'], 'Late minutes = 0');
    $att1Record = $attendances->findByUserAndDate($emp1Id, '2026-11-16');
    assertEquals($fakePhoto, $att1Record['checkin_photo'], 'Foto selfie check-in tersimpan di database');

    // Uji batas 08:05 masih tepat waktu
    [$statusDeadline, $lateMinDeadline] = $attService->evaluateArrival($mondayDeadline);
    assertEquals('on_time', $statusDeadline, 'Pukul 08:05 pas batas toleransi tercatat on_time');
    assertEquals(0, $lateMinDeadline, 'Pukul 08:05 late_minutes = 0');

    // Uji lewat 08:05 (08:25) terlambat 40 menit dari 07:45
    [$statusLate, $lateMinLate] = $attService->evaluateArrival($mondayLate);
    assertEquals('late', $statusLate, 'Pukul 08:25 tercatat late');
    assertEquals(40, $lateMinLate, 'Pukul 08:25 late_minutes = 40 menit dari 07:45');

    // Replay attack: karyawan 1 memakai token sama lagi => ditolak
    $replay = $attService->processScan($emp1Id, $issuedIn['token'], -6.200100, 106.816666, 15.0, null, $mondayMorning + 5);
    assertTrue(!$replay['ok'], 'Replay attack oleh karyawan yang sama ditolak');

    // Double attendance dengan token baru pada hari sama => ditolak
    $issuedIn2 = $qrTokens->issueToken('checkin', '127.0.0.1', $mondayLate);
    $doubleIn = $attService->processScan($emp1Id, $issuedIn2['token'], -6.200100, 106.816666, 15.0, null, $mondayLate);
    assertTrue(!$doubleIn['ok'] && $doubleIn['status'] === 409, 'Double check-in hari sama ditolak (409)');

    // Karyawan 2 check-in terlambat (08:25 => 40 menit setelah 07:45, melewati toleransi 20 menit)
    $ci2 = $attService->processScan($emp2Id, $issuedIn2['token'], -6.200100, 106.816666, 22.0, null, $mondayLate);
    assertTrue($ci2['ok'], 'Check-in karyawan 2 berhasil');
    assertEquals('late', $ci2['data']['status'], 'Status karyawan 2 otomatis late');
    assertEquals(40, $ci2['data']['late_minutes'], 'Keterlambatan otomatis terhitung 40 menit');

    // Token kedaluwarsa ditolak
    $expiredAttempt = $attService->processScan($emp2Id, $issuedIn['token'], -6.200100, 106.816666, 15.0, $mondayMorning + 120);
    assertTrue(!$expiredAttempt['ok'], 'Token QR kedaluwarsa ditolak backend');

    // Token palsu / tanda tangan salah ditolak
    $forgedAttempt = $attService->processScan($emp2Id, 'SAT1|checkin|9999999999|deadbeef|000000000000000000000000', -6.200100, 106.816666, 15.0, $mondayMorning);
    assertTrue(!$forgedAttempt['ok'], 'Token QR palsu ditolak backend');

    // Check-in di luar radius ditolak
    $outsideToken = $qrTokens->issueToken('checkin', '127.0.0.1', $mondayLate + 10);
    $emp3Id = $users->create(['full_name' => 'Karyawan Tiga', 'username' => 'emp.tiga', 'password' => 'EmpPass123!', 'role' => 'employee', 'department_id' => 1]);
    $outsideScan = $attService->processScan($emp3Id, $outsideToken['token'], -6.210000, 106.816666, 15.0, $mondayLate + 10);
    assertTrue(!$outsideScan['ok'] && str_contains($outsideScan['message'], 'luar radius'), 'Check-in di luar radius geofence ditolak');

    // Check-out tanpa check-in ditolak
    $issuedOut = $qrTokens->issueToken('checkout', '127.0.0.1', $mondayEvening);
    $outWithoutIn = $attService->processScan($emp3Id, $issuedOut['token'], -6.200100, 106.816666, 15.0, $mondayEvening);
    assertTrue(!$outWithoutIn['ok'], 'Check-out tanpa check-in ditolak');

    // Karyawan 1 check-out valid
    $co1 = $attService->processScan($emp1Id, $issuedOut['token'], -6.200050, 106.816660, 18.0, $mondayEvening);
    assertTrue($co1['ok'], 'Check-out karyawan 1 berhasil');

    // Double check-out ditolak
    $issuedOut2 = $qrTokens->issueToken('checkout', '127.0.0.1', $mondayEvening + 60);
    $doubleOut = $attService->processScan($emp1Id, $issuedOut2['token'], -6.200050, 106.816660, 18.0, $mondayEvening + 60);
    assertTrue(!$doubleOut['ok'] && $doubleOut['status'] === 409, 'Double check-out ditolak (409)');

    // =========================================================================
    // TAHAP 4: Izin & Sakit, Dashboard Analytics, Ranking Kedisiplinan
    // =========================================================================
    fwrite(STDOUT, "\n=== TAHAP 4: Izin/Sakit, Analytics Dashboard, Ranking Kedisiplinan ===\n");
    $leaveId = $leaves->create($emp3Id, 'sick', '2026-11-16', '2026-11-16', 'Demam tinggi');
    $reviewRes = $attService->reviewLeaveRequest($leaveId, 'approved', $adminId, 'Semoga lekas sembuh');
    assertTrue($reviewRes['ok'], 'Persetujuan pengajuan sakit berhasil');

    $emp3Att = $attendances->findByUserAndDate($emp3Id, '2026-11-16');
    assertEquals('sick', $emp3Att['status'] ?? null, 'Status sakit otomatis masuk ke tabel attendances');

    $analytics = new AnalyticsService($db, $users, $settings, $holidays);
    $dash = $analytics->dashboardData(['date' => '2026-11-16', 'month' => '2026-11']);
    assertEquals(3, $dash['kpis']['total_employees'], 'Total karyawan aktif = 3');
    assertEquals(2, $dash['kpis']['present_today'], 'Hadir hari itu = 2');
    assertEquals(1, $dash['kpis']['on_time_today'], 'Tepat waktu = 1');
    assertEquals(1, $dash['kpis']['late_today'], 'Terlambat = 1');
    assertEquals(1, $dash['kpis']['sick_today'], 'Sakit = 1');
    assertEquals(1, $dash['kpis']['not_checked_out_count'], 'Belum check-out = 1 (karyawan 2)');
    assertEquals('Karyawan Satu', $dash['discipline_ranking'][0]['full_name'], 'Peringkat #1 kedisiplinan adalah Karyawan Satu (tepat waktu)');

    // =========================================================================
    // TAHAP 5: Backup SQLite Online API, USB Sync, Restore, HTTP Router Security
    // =========================================================================
    fwrite(STDOUT, "\n=== TAHAP 5: Backup Online API, USB Sync, Restore, Keamanan HTTP ===\n");
    $backupService = new BackupService($dbPath, $localBackupDir, $usbBackupDir, 30);
    $bk = $backupService->createBackup('test');
    assertTrue($bk['ok'] && is_file($bk['local_path']), 'Backup lokal berhasil dibuat');
    assertTrue($bk['usb_synced'] && is_file((string) $bk['usb_path']), 'Backup tersalin otomatis ke direktori USB');
    assertTrue($backupService->verifyIntegrity($bk['local_path']), 'Backup lolos PRAGMA integrity_check');

    // Modifikasi data lalu restore dari backup
    $users->create(['full_name' => 'Sementara', 'username' => 'temp.user', 'password' => 'TempPass123!', 'role' => 'employee']);
    assertTrue($users->findByUsername('temp.user') !== null, 'User sementara dibuat sebelum restore');

    $restoreRes = $backupService->restoreFromFile($bk['local_path']);
    assertTrue($restoreRes['ok'], 'Restore database berhasil dengan pre-restore snapshot');
    assertTrue($users->findByUsername('temp.user') === null, 'Data pasca-restore kembali ke kondisi backup');

    // Uji perlindungan route HTTP (guest redirect, API 401, Kiosk key auth)
    $_SESSION = [];
    ['router' => $router] = Bootstrap::create($config);
    $healthRes = $router->dispatch(new Request('GET', '/health'));
    assertEquals(200, $healthRes->status(), 'GET /health mengembalikan 200');

    $adminGuest = $router->dispatch(new Request('GET', '/admin'));
    assertEquals(302, $adminGuest->status(), 'GET /admin tanpa login dialihkan ke /login (302)');

    $apiGuest = $router->dispatch(new Request('GET', '/api/admin/dashboard'));
    assertEquals(401, $apiGuest->status(), 'GET /api/admin/dashboard tanpa login mengembalikan 401');

    $kioskBadKey = $router->dispatch(new Request('GET', '/api/kiosk/qr', ['key' => 'kunci-salah']));
    assertEquals(403, $kioskBadKey->status(), 'GET /api/kiosk/qr dengan key salah ditolak (403)');

    $kioskGoodKey = $router->dispatch(new Request('GET', '/api/kiosk/qr', ['key' => 'test-kiosk-key-1234']));
    assertEquals(200, $kioskGoodKey->status(), 'GET /api/kiosk/qr dengan key valid mengembalikan 200');
    $kioskJson = json_decode($kioskGoodKey->body(), true);
    assertTrue(($kioskJson['ok'] ?? false) === true && str_contains((string) ($kioskJson['svg'] ?? ''), '<svg'), 'Endpoint kiosk QR mengembalikan payload SVG valid');
    assertTrue(isset($kioskJson['recent_attendances']) && is_array($kioskJson['recent_attendances']), 'Endpoint kiosk QR menyertakan daftar recent_attendances');

    // Uji endpoint aktivitas TV /api/kiosk/activity dan view monitor /kiosk
    $actBadKey = $router->dispatch(new Request('GET', '/api/kiosk/activity', ['key' => 'kunci-salah']));
    assertEquals(403, $actBadKey->status(), 'GET /api/kiosk/activity dengan key salah ditolak (403)');

    $actGoodKey = $router->dispatch(new Request('GET', '/api/kiosk/activity', ['key' => 'test-kiosk-key-1234']));
    assertEquals(200, $actGoodKey->status(), 'GET /api/kiosk/activity dengan key valid mengembalikan 200');
    $actJson = json_decode($actGoodKey->body(), true);
    assertTrue(($actJson['ok'] ?? false) === true && isset($actJson['recent_attendances']), 'Endpoint /api/kiosk/activity mengembalikan payload live activity');

    $kioskViewRes = $router->dispatch(new Request('GET', '/kiosk', ['key' => 'test-kiosk-key-1234']));
    assertEquals(200, $kioskViewRes->status(), 'GET /kiosk mengembalikan 200 dengan key valid');
    assertTrue(str_contains($kioskViewRes->body(), 'id="kioskSoundBtn"'), 'Monitor Kiosk memuat tombol toggle suara');
    assertTrue(str_contains($kioskViewRes->body(), 'id="kioskAttendanceAlert"'), 'Monitor Kiosk memuat kontainer alert pop-up kehadiran TV');
    assertTrue(str_contains($kioskViewRes->body(), 'id="kioskRecentList"'), 'Monitor Kiosk memuat daftar kehadiran terbaru hari ini');

    // Uji Leaflet OSM CSP dan kebersihan PWA layout
    $cspHeader = $healthRes->headers()['Content-Security-Policy'] ?? '';
    assertTrue(str_contains($cspHeader, 'https://*.tile.openstreetmap.org'), 'CSP mengizinkan tile OpenStreetMap');

    // Login admin untuk render view /admin/settings
    Session::set('_auth_user_id', (int) $users->findByUsername('admin')['id']);
    $adminSettingsRes = $router->dispatch(new Request('GET', '/admin/settings'));
    assertEquals(200, $adminSettingsRes->status(), 'GET /admin/settings mengembalikan 200 untuk admin');
    assertTrue(str_contains($adminSettingsRes->body(), 'id="officeMap"'), 'Halaman settings memuat kontainer peta #officeMap');
    assertTrue(str_contains($adminSettingsRes->body(), 'leaflet.css') && str_contains($adminSettingsRes->body(), 'leaflet.js'), 'Halaman settings memuat aset Leaflet');

    // Uji tampilan employee tidak memuat tombol install aplikasi
    Session::set('_auth_user_id', (int) $users->findByUsername('emp.satu')['id']);
    $empDashboardRes = $router->dispatch(new Request('GET', '/app'));
    assertEquals(200, $empDashboardRes->status(), 'GET /app mengembalikan 200 untuk karyawan');
    assertTrue(!str_contains($empDashboardRes->body(), 'id="pwaInstallBtn"'), 'Header employee PWA bebas dari tombol Install Aplikasi');

    // Uji Cetak Laporan & kolom foto selfie di laporan admin
    Session::set('_auth_user_id', (int) $users->findByUsername('admin')['id']);
    $reportsRes = $router->dispatch(new Request('GET', '/admin/reports'));
    assertEquals(200, $reportsRes->status(), 'GET /admin/reports mengembalikan 200');
    assertTrue(str_contains($reportsRes->body(), 'Cetak Laporan'), 'Halaman laporan memuat tombol Cetak Laporan');
    assertTrue(str_contains($reportsRes->body(), 'Foto Selfie'), 'Halaman laporan memuat kolom Foto Selfie');
    assertTrue(str_contains($reportsRes->body(), 'print-signatures'), 'Halaman laporan memuat bagian tanda tangan cetak');
} finally {
    // Cleanup temp directory
    $cleanDir = static function (string $dir) use (&$cleanDir): void {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($p)) {
                $cleanDir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    };
    $cleanDir($tmpRoot);
}

fwrite(STDOUT, "\n========================================\n");
fwrite(STDOUT, "TOTAL LULUS: {$passed} | GAGAL: {$failed}\n");
fwrite(STDOUT, "========================================\n");
exit($failed > 0 ? 1 : 0);
