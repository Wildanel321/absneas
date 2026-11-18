<?php

declare(strict_types=1);

/**
 * Opsional: Script pengisi data uji untuk lingkungan pengembangan/demo lokal.
 * Jangan dijalankan pada database produksi.
 */

use App\Core\Application;
use App\Core\Autoloader;
use App\Core\Env;
use App\Models\AttendanceModel;
use App\Models\LeaveRequestModel;
use App\Models\UserModel;

$basePath = dirname(__DIR__);
require_once $basePath . '/app/Core/Autoloader.php';
Autoloader::register($basePath);
Env::load($basePath . '/.env');

$config = require $basePath . '/config/app.php';
$app = new Application($config);
$db = $app->db();

$users = new UserModel($db);
$attendances = new AttendanceModel($db);
$leaves = new LeaveRequestModel($db);

$deptRows = $db->fetchAll('SELECT id, name FROM departments');
$deptByName = [];
foreach ($deptRows as $d) {
    $deptByName[(string) $d['name']] = (int) $d['id'];
}

$sampleEmployees = [
    ['full_name' => 'Rina Kartika', 'username' => 'rina.kartika', 'dept' => 'Operasional', 'pos' => 'Koordinator Operasional'],
    ['full_name' => 'Budi Santoso', 'username' => 'budi.santoso', 'dept' => 'Teknologi', 'pos' => 'Teknisi Jaringan'],
    ['full_name' => 'Dewi Anggraini', 'username' => 'dewi.anggraini', 'dept' => 'Keuangan', 'pos' => 'Staf Akuntansi'],
    ['full_name' => 'Fajar Pratama', 'username' => 'fajar.pratama', 'dept' => 'Teknologi', 'pos' => 'Pengembang Web'],
    ['full_name' => 'Siti Rahmawati', 'username' => 'siti.rahmawati', 'dept' => 'SDM', 'pos' => 'Admin Personalia'],
    ['full_name' => 'Hendra Wijaya', 'username' => 'hendra.wijaya', 'dept' => 'Operasional', 'pos' => 'Staf Logistik'],
];

$createdIds = [];
foreach ($sampleEmployees as $emp) {
    $existing = $users->findByUsername($emp['username']);
    if ($existing !== null) {
        $createdIds[] = (int) $existing['id'];
        continue;
    }
    $id = $users->create([
        'full_name' => $emp['full_name'],
        'username' => $emp['username'],
        'email' => $emp['username'] . '@kantor.local',
        'phone' => '08120000' . random_int(1000, 9999),
        'password' => 'Karyawan123!',
        'role' => 'employee',
        'department_id' => $deptByName[$emp['dept']] ?? null,
        'position' => $emp['pos'],
        'is_active' => 1,
    ]);
    $createdIds[] = $id;
}

// Isi riwayat absensi 10 hari kerja terakhir agar grafik & ranking terisi saat pengujian
$today = new DateTimeImmutable('today');
for ($offset = 12; $offset >= 0; $offset--) {
    $dt = $today->modify("-{$offset} days");
    if ((int) $dt->format('N') > 5) {
        continue;
    }
    $dateStr = $dt->format('Y-m-d');
    foreach ($createdIds as $idx => $uid) {
        if ($attendances->findByUserAndDate($uid, $dateStr) !== null) {
            continue;
        }
        // Variasi realistis: mayoritas tepat waktu, sebagian terlambat, sesekali izin/sakit, 1 belum check-out hari ini
        if ($offset === 0 && $idx === 5) {
            continue; // 1 karyawan belum hadir hari ini
        }
        if ($offset === 2 && $idx === 3) {
            $attendances->upsertLeaveOrSick($uid, $dateStr, 'sick', 'Demam dan istirahat di rumah');
            continue;
        }
        if ($offset === 4 && $idx === 4) {
            $attendances->upsertLeaveOrSick($uid, $dateStr, 'leave', 'Keperluan keluarga');
            continue;
        }

        $isLate = (($offset + $idx) % 4 === 0);
        $inMinute = $isLate ? (18 + (($offset * 3 + $idx * 5) % 25)) : (45 + (($offset + $idx) % 14));
        $inTime = $isLate
            ? sprintf('%s 08:%02d:12', $dateStr, $inMinute)
            : sprintf('%s 07:%02d:05', $dateStr, $inMinute);
        $lateMinutes = $isLate ? $inMinute : 0;
        $status = $isLate ? 'late' : 'on_time';

        $attId = $attendances->recordCheckin(
            $uid,
            $dateStr,
            $inTime,
            -6.200010 + ($idx * 0.00001),
            106.816670 + ($idx * 0.00001),
            18.5,
            12.4 + ($idx * 3.1),
            $lateMinutes,
            $status
        );

        // Pada hari ini, biarkan 2 karyawan belum check-out agar kartu "Belum Check-out" terlihat nyata
        if ($offset > 0 || $idx < 3) {
            $outTime = sprintf('%s 17:%02d:10', $dateStr, 5 + ($idx * 4));
            $attendances->recordCheckout(
                $attId,
                $outTime,
                -6.200015,
                106.816675,
                20.0,
                14.2
            );
        }
    }
}

// 1 pengajuan izin menunggu review admin
if (isset($createdIds[1])) {
    $tomorrow = $today->modify('+1 day')->format('Y-m-d');
    if (!$leaves->hasOverlap($createdIds[1], $tomorrow, $tomorrow)) {
        $leaves->create($createdIds[1], 'leave', $tomorrow, $tomorrow, 'Pengurusan dokumen kendaraan bermotor');
    }
}

fwrite(STDOUT, "[SEED] Data demo berhasil disiapkan. Password karyawan demo: Karyawan123!\n");
