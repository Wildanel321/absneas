<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AttendanceModel;
use App\Models\HolidayModel;
use App\Models\LeaveRequestModel;
use App\Models\SettingModel;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

final class AttendanceService
{
    public function __construct(
        private Database $db,
        private AttendanceModel $attendances,
        private SettingModel $settings,
        private HolidayModel $holidays,
        private LeaveRequestModel $leaves,
        private QrTokenService $qrTokens,
        private GeoService $geo
    ) {
    }

    /**
     * Memproses scan QR (check-in atau check-out) beserta validasi GPS dan geofencing di server.
     *
     * @return array{ok: bool, status: int, message: string, data?: array<string, mixed>}
     */
    public function processScan(
        int $userId,
        string $rawToken,
        float $lat,
        float $lng,
        float $accuracyM,
        ?int $nowTimestamp = null
    ): array {
        $now = $nowTimestamp ?? time();
        $today = date('Y-m-d', $now);
        $nowSql = date('Y-m-d H:i:s', $now);

        // 1. Validasi token QR di backend
        $qrCheck = $this->qrTokens->validateToken($rawToken, $userId, $now);
        if (!$qrCheck['ok']) {
            return ['ok' => false, 'status' => 422, 'message' => $qrCheck['message']];
        }

        $mode = (string) $qrCheck['mode'];
        $tokenRow = $qrCheck['token_row'];

        // 2. Validasi GPS & geofencing Haversine di backend
        $officeLat = $this->settings->getFloat('office_lat', -6.200000);
        $officeLng = $this->settings->getFloat('office_lng', 106.816666);
        $radiusM = max(10.0, $this->settings->getFloat('geofence_radius_m', 100.0));
        $maxAccuracyM = max(20.0, $this->settings->getFloat('max_gps_accuracy_m', 150.0));

        $geoCheck = $this->geo->validateLocation($lat, $lng, $accuracyM, $officeLat, $officeLng, $radiusM, $maxAccuracyM);
        if (!$geoCheck['ok']) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => $geoCheck['message'],
                'data' => [
                    'distance_m' => $geoCheck['distance_m'],
                    'radius_m' => $geoCheck['radius_m'],
                    'accuracy_m' => $geoCheck['accuracy_m'],
                ],
            ];
        }

        // 3. Cek hari libur / hari kerja untuk check-in
        $existing = $this->attendances->findByUserAndDate($userId, $today);

        if ($mode === 'checkin') {
            $isoDay = (int) date('N', $now);
            $workingDays = $this->settings->workingDays();
            if (!in_array($isoDay, $workingDays, true)) {
                return ['ok' => false, 'status' => 422, 'message' => 'Hari ini bukan hari kerja terjadwal.'];
            }
            if ($this->holidays->isHoliday($today)) {
                return ['ok' => false, 'status' => 422, 'message' => 'Hari ini tercatat sebagai hari libur kantor.'];
            }

            if ($existing !== null) {
                if (in_array((string) $existing['status'], ['leave', 'sick'], true)) {
                    $label = $existing['status'] === 'sick' ? 'sakit' : 'izin';
                    return ['ok' => false, 'status' => 409, 'message' => "Anda sudah tercatat {$label} yang disetujui hari ini."];
                }
                if ($existing['checkin_at'] !== null) {
                    return ['ok' => false, 'status' => 409, 'message' => 'Anda sudah melakukan check-in hari ini pada ' . substr((string) $existing['checkin_at'], 11, 5) . '.'];
                }
            }

            [$status, $lateMinutes] = $this->evaluateArrival($now);

            try {
                $attendanceId = $this->db->transaction(function () use (
                    $userId,
                    $today,
                    $nowSql,
                    $lat,
                    $lng,
                    $accuracyM,
                    $geoCheck,
                    $lateMinutes,
                    $status,
                    $tokenRow
                ): int {
                    $this->qrTokens->markUsed((int) $tokenRow['id'], $userId);
                    return $this->attendances->recordCheckin(
                        $userId,
                        $today,
                        $nowSql,
                        $lat,
                        $lng,
                        $accuracyM,
                        (float) $geoCheck['distance_m'],
                        $lateMinutes,
                        $status
                    );
                });
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    return ['ok' => false, 'status' => 409, 'message' => 'Absensi check-in sudah tercatat atau token sudah digunakan.'];
                }
                throw $e;
            }

            $statusLabel = $status === 'late'
                ? "Terlambat {$lateMinutes} menit"
                : 'Tepat waktu';

            return [
                'ok' => true,
                'status' => 200,
                'message' => "Check-in berhasil pada " . substr($nowSql, 11, 5) . " ({$statusLabel}).",
                'data' => [
                    'attendance_id' => $attendanceId,
                    'mode' => 'checkin',
                    'time' => substr($nowSql, 11, 5),
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'distance_m' => $geoCheck['distance_m'],
                    'accuracy_m' => $geoCheck['accuracy_m'],
                ],
            ];
        }

        // Mode checkout
        if ($existing === null || $existing['checkin_at'] === null) {
            return ['ok' => false, 'status' => 422, 'message' => 'Anda belum melakukan check-in hari ini sehingga tidak dapat check-out.'];
        }

        if ($existing['checkout_at'] !== null) {
            return ['ok' => false, 'status' => 409, 'message' => 'Anda sudah melakukan check-out hari ini pada ' . substr((string) $existing['checkout_at'], 11, 5) . '.'];
        }

        try {
            $this->db->transaction(function () use ($existing, $nowSql, $lat, $lng, $accuracyM, $geoCheck, $tokenRow, $userId): void {
                $this->qrTokens->markUsed((int) $tokenRow['id'], $userId);
                $this->attendances->recordCheckout(
                    (int) $existing['id'],
                    $nowSql,
                    $lat,
                    $lng,
                    $accuracyM,
                    (float) $geoCheck['distance_m']
                );
            });
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return ['ok' => false, 'status' => 409, 'message' => 'Token QR ini sudah digunakan.'];
            }
            throw $e;
        }

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Check-out berhasil pada ' . substr($nowSql, 11, 5) . '.',
            'data' => [
                'attendance_id' => (int) $existing['id'],
                'mode' => 'checkout',
                'time' => substr($nowSql, 11, 5),
                'distance_m' => $geoCheck['distance_m'],
                'accuracy_m' => $geoCheck['accuracy_m'],
            ],
        ];
    }

    /**
     * Menghitung status keterlambatan otomatis berdasarkan jam masuk dan toleransi.
     *
     * @return array{0: string, 1: int} [status, lateMinutes]
     */
    public function evaluateArrival(int $timestamp): array
    {
        $workStart = $this->settings->get('work_start_time', '08:00');
        $toleranceMin = max(0, $this->settings->getInt('late_tolerance_minutes', 15));

        $todayStr = date('Y-m-d', $timestamp);
        $startTs = strtotime($todayStr . ' ' . $workStart . ':00');
        if ($startTs === false) {
            return ['on_time', 0];
        }

        $deadlineTs = $startTs + ($toleranceMin * 60);
        if ($timestamp <= $deadlineTs) {
            return ['on_time', 0];
        }

        $lateMinutes = (int) ceil(($timestamp - $startTs) / 60);
        return ['late', max(1, $lateMinutes)];
    }

    /**
     * Menyetujui atau menolak pengajuan izin/sakit; bila disetujui, otomatis menulis status ke tabel attendances
     * untuk setiap hari kerja dalam rentang tanggal.
     *
     * @return array{ok: bool, message: string}
     */
    public function reviewLeaveRequest(int $requestId, string $decision, int $reviewerId, string $note = ''): array
    {
        $req = $this->leaves->findById($requestId);
        if ($req === null) {
            return ['ok' => false, 'message' => 'Pengajuan tidak ditemukan.'];
        }
        if ($req['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya.'];
        }

        $normalized = $decision === 'approved' ? 'approved' : 'rejected';

        $this->db->transaction(function () use ($req, $requestId, $normalized, $reviewerId, $note): void {
            $this->leaves->review($requestId, $normalized, $reviewerId, $note);

            if ($normalized === 'approved') {
                $workingDays = $this->settings->workingDays();
                $holidays = $this->holidays->mapInRange((string) $req['start_date'], (string) $req['end_date']);
                $start = new DateTimeImmutable((string) $req['start_date']);
                $end = (new DateTimeImmutable((string) $req['end_date']))->modify('+1 day');
                $period = new DatePeriod($start, new DateInterval('P1D'), $end);

                foreach ($period as $dt) {
                    $isoDay = (int) $dt->format('N');
                    $dateStr = $dt->format('Y-m-d');
                    if (!in_array($isoDay, $workingDays, true) || isset($holidays[$dateStr])) {
                        continue;
                    }
                    $this->attendances->upsertLeaveOrSick(
                        (int) $req['user_id'],
                        $dateStr,
                        (string) $req['type'],
                        (string) $req['reason']
                    );
                }
            }
        });

        $label = $normalized === 'approved' ? 'disetujui' : 'ditolak';
        return ['ok' => true, 'message' => "Pengajuan berhasil {$label}."];
    }

    /**
     * Label status kehadiran yang terbaca oleh manusia (termasuk kondisi belum check-out).
     *
     * @param array<string, mixed>|null $row
     * @return array{code: string, label: string, tone: string}
     */
    public static function describeStatus(?array $row): array
    {
        if ($row === null) {
            return ['code' => 'absent', 'label' => 'Belum hadir', 'tone' => 'danger'];
        }

        $status = (string) ($row['status'] ?? 'absent');
        if ($status === 'leave') {
            return ['code' => 'leave', 'label' => 'Izin', 'tone' => 'info'];
        }
        if ($status === 'sick') {
            return ['code' => 'sick', 'label' => 'Sakit', 'tone' => 'info'];
        }
        if ($status === 'absent') {
            return ['code' => 'absent', 'label' => 'Tidak hadir', 'tone' => 'danger'];
        }

        $hasCheckout = !empty($row['checkout_at']);
        if ($status === 'late') {
            $late = (int) ($row['late_minutes'] ?? 0);
            $label = $hasCheckout ? "Terlambat ({$late} mnt)" : "Terlambat ({$late} mnt), belum pulang";
            return ['code' => $hasCheckout ? 'late' : 'not_checked_out', 'label' => $label, 'tone' => 'warning'];
        }

        if (!$hasCheckout) {
            return ['code' => 'not_checked_out', 'label' => 'Hadir, belum check-out', 'tone' => 'warning'];
        }

        return ['code' => 'on_time', 'label' => 'Hadir tepat waktu', 'tone' => 'success'];
    }
}
