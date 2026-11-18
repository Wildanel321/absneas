<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\HolidayModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

final class AnalyticsService
{
    public function __construct(
        private Database $db,
        private UserModel $users,
        private SettingModel $settings,
        private HolidayModel $holidays
    ) {
    }

    /**
     * Menghasilkan seluruh metrik real-time dashboard admin, 5 grafik, daftar belum check-out,
     * dan tabel absensi sesuai filter (tanggal, bulan, departemen, nama karyawan).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function dashboardData(array $filters = []): array
    {
        $today = date('Y-m-d');
        $selectedDate = (!empty($filters['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['date']))
            ? (string) $filters['date']
            : $today;

        $selectedMonth = (!empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', (string) $filters['month']))
            ? (string) $filters['month']
            : substr($selectedDate, 0, 7);

        $departmentId = !empty($filters['department_id']) ? (int) $filters['department_id'] : null;
        $search = trim((string) ($filters['search'] ?? ''));

        $employees = $this->users->activeEmployees($departmentId, $search !== '' ? $search : null);
        $empIds = array_map(static fn (array $e): int => (int) $e['id'], $employees);
        $totalEmployees = count($employees);

        $dayRows = $this->attendancesForUsersInRange($empIds, $selectedDate, $selectedDate);
        $byUser = [];
        foreach ($dayRows as $r) {
            $byUser[(int) $r['user_id']] = $r;
        }

        $presentCount = 0;
        $onTimeCount = 0;
        $lateCount = 0;
        $leaveCount = 0;
        $sickCount = 0;
        $absentCount = 0;
        $notCheckedOutList = [];
        $dailyRoster = [];

        $workingDays = $this->settings->workingDays();
        $isWorkingDay = in_array((int) date('N', strtotime($selectedDate) ?: time()), $workingDays, true)
            && !$this->holidays->isHoliday($selectedDate);

        foreach ($employees as $emp) {
            $uid = (int) $emp['id'];
            $att = $byUser[$uid] ?? null;

            if ($att === null) {
                if ($isWorkingDay) {
                    $absentCount++;
                }
                $dailyRoster[] = [
                    'user_id' => $uid,
                    'employee_code' => $emp['employee_code'],
                    'full_name' => $emp['full_name'],
                    'department_name' => $emp['department_name'] ?? 'Tanpa Departemen',
                    'checkin_at' => null,
                    'checkout_at' => null,
                    'late_minutes' => 0,
                    'checkin_distance_m' => null,
                    'status' => $isWorkingDay ? 'absent' : 'off_day',
                    'status_label' => $isWorkingDay ? 'Tidak hadir' : 'Hari libur',
                    'status_tone' => $isWorkingDay ? 'danger' : 'neutral',
                ];
                continue;
            }

            $st = (string) $att['status'];
            if ($st === 'on_time' || $st === 'late') {
                $presentCount++;
                if ($st === 'late') {
                    $lateCount++;
                } else {
                    $onTimeCount++;
                }
                if (empty($att['checkout_at'])) {
                    $notCheckedOutList[] = [
                        'user_id' => $uid,
                        'employee_code' => $emp['employee_code'],
                        'full_name' => $emp['full_name'],
                        'department_name' => $emp['department_name'] ?? 'Tanpa Departemen',
                        'checkin_at' => $att['checkin_at'],
                        'late_minutes' => (int) $att['late_minutes'],
                    ];
                }
            } elseif ($st === 'leave') {
                $leaveCount++;
            } elseif ($st === 'sick') {
                $sickCount++;
            } else {
                if ($isWorkingDay) {
                    $absentCount++;
                }
            }

            $desc = AttendanceService::describeStatus($att);
            $dailyRoster[] = [
                'user_id' => $uid,
                'employee_code' => $emp['employee_code'],
                'full_name' => $emp['full_name'],
                'department_name' => $emp['department_name'] ?? 'Tanpa Departemen',
                'checkin_at' => $att['checkin_at'],
                'checkout_at' => $att['checkout_at'],
                'late_minutes' => (int) $att['late_minutes'],
                'checkin_distance_m' => $att['checkin_distance_m'],
                'status' => $desc['code'],
                'status_label' => $desc['label'],
                'status_tone' => $desc['tone'],
            ];
        }

        // 1 & 2. Grafik Kehadiran Harian + Keterlambatan (14 hari terakhir hingga selectedDate)
        $dailySeries = $this->buildDailySeries($empIds, $selectedDate, 14, $workingDays);

        // 3. Grafik Bulanan (12 bulan pada tahun terpilih)
        $selectedYear = (int) substr($selectedMonth, 0, 4);
        $monthlySeries = $this->buildMonthlySeries($empIds, $selectedYear);

        // 4. Grafik Izin dan Sakit pada bulan terpilih (per minggu / per departemen)
        $leaveSickBreakdown = $this->buildLeaveSickBreakdown($empIds, $selectedMonth);

        // 5. Ranking Kedisiplinan Karyawan pada bulan terpilih
        $disciplineRanking = $this->buildDisciplineRanking($employees, $selectedMonth, $workingDays);

        $pendingLeaves = (int) $this->db->fetchValue("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'", [], 0);

        return [
            'filters' => [
                'date' => $selectedDate,
                'month' => $selectedMonth,
                'department_id' => $departmentId,
                'search' => $search,
            ],
            'is_working_day' => $isWorkingDay,
            'kpis' => [
                'total_employees' => $totalEmployees,
                'present_today' => $presentCount,
                'on_time_today' => $onTimeCount,
                'late_today' => $lateCount,
                'leave_today' => $leaveCount,
                'sick_today' => $sickCount,
                'absent_today' => $absentCount,
                'not_checked_out_count' => count($notCheckedOutList),
                'pending_leaves' => $pendingLeaves,
            ],
            'not_checked_out' => $notCheckedOutList,
            'daily_roster' => $dailyRoster,
            'charts' => [
                'daily_attendance' => $dailySeries,
                'daily_lateness' => array_map(static fn (array $d): array => [
                    'date' => $d['date'],
                    'label' => $d['label'],
                    'late' => $d['late'],
                    'avg_late_minutes' => $d['avg_late_minutes'],
                ], $dailySeries),
                'monthly_trend' => $monthlySeries,
                'leave_sick' => $leaveSickBreakdown,
            ],
            'discipline_ranking' => $disciplineRanking,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param list<int> $empIds
     * @return list<array<string, mixed>>
     */
    private function attendancesForUsersInRange(array $empIds, string $start, string $end): array
    {
        if ($empIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($empIds), '?'));
        $params = array_merge($empIds, [$start, $end]);
        return $this->db->fetchAll(
            "SELECT * FROM attendances WHERE user_id IN ({$placeholders}) AND attendance_date BETWEEN ? AND ?",
            $params
        );
    }

    /**
     * @param list<int> $empIds
     * @param list<int> $workingDays
     * @return list<array<string, mixed>>
     */
    private function buildDailySeries(array $empIds, string $endDate, int $days, array $workingDays): array
    {
        $end = new DateTimeImmutable($endDate);
        $start = $end->modify('-' . ($days - 1) . ' days');
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        $rows = $this->attendancesForUsersInRange($empIds, $startStr, $endStr);
        $holidays = $this->holidays->mapInRange($startStr, $endStr);
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[(string) $r['attendance_date']][] = $r;
        }

        $totalEmp = count($empIds);
        $series = [];
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));

        foreach ($period as $dt) {
            $d = $dt->format('Y-m-d');
            $isoDay = (int) $dt->format('N');
            $workDay = in_array($isoDay, $workingDays, true) && !isset($holidays[$d]);

            $onTime = 0;
            $late = 0;
            $leave = 0;
            $sick = 0;
            $lateMinSum = 0;

            foreach ($grouped[$d] ?? [] as $r) {
                $st = (string) $r['status'];
                if ($st === 'on_time') {
                    $onTime++;
                } elseif ($st === 'late') {
                    $late++;
                    $lateMinSum += (int) $r['late_minutes'];
                } elseif ($st === 'leave') {
                    $leave++;
                } elseif ($st === 'sick') {
                    $sick++;
                }
            }

            $accounted = $onTime + $late + $leave + $sick;
            $absent = $workDay ? max(0, $totalEmp - $accounted) : 0;

            $series[] = [
                'date' => $d,
                'label' => $dt->format('d/m'),
                'is_working_day' => $workDay,
                'on_time' => $onTime,
                'late' => $late,
                'present' => $onTime + $late,
                'leave' => $leave,
                'sick' => $sick,
                'absent' => $absent,
                'avg_late_minutes' => $late > 0 ? (int) round($lateMinSum / $late) : 0,
            ];
        }

        return $series;
    }

    /**
     * @param list<int> $empIds
     * @return list<array<string, mixed>>
     */
    private function buildMonthlySeries(array $empIds, int $year): array
    {
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $startStr = sprintf('%04d-01-01', $year);
        $endStr = sprintf('%04d-12-31', $year);
        $rows = $this->attendancesForUsersInRange($empIds, $startStr, $endStr);

        $buckets = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            $buckets[$key] = [
                'month' => $key,
                'label' => $monthNames[$m - 1],
                'on_time' => 0,
                'late' => 0,
                'present' => 0,
                'leave' => 0,
                'sick' => 0,
            ];
        }

        foreach ($rows as $r) {
            $mKey = substr((string) $r['attendance_date'], 0, 7);
            if (!isset($buckets[$mKey])) {
                continue;
            }
            $st = (string) $r['status'];
            if ($st === 'on_time') {
                $buckets[$mKey]['on_time']++;
                $buckets[$mKey]['present']++;
            } elseif ($st === 'late') {
                $buckets[$mKey]['late']++;
                $buckets[$mKey]['present']++;
            } elseif ($st === 'leave') {
                $buckets[$mKey]['leave']++;
            } elseif ($st === 'sick') {
                $buckets[$mKey]['sick']++;
            }
        }

        return array_values($buckets);
    }

    /**
     * @param list<int> $empIds
     * @return array<string, mixed>
     */
    private function buildLeaveSickBreakdown(array $empIds, string $month): array
    {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start) ?: time());
        $rows = $this->attendancesForUsersInRange($empIds, $start, $end);

        $totalLeave = 0;
        $totalSick = 0;
        $weeks = [
            ['label' => 'Minggu 1', 'leave' => 0, 'sick' => 0],
            ['label' => 'Minggu 2', 'leave' => 0, 'sick' => 0],
            ['label' => 'Minggu 3', 'leave' => 0, 'sick' => 0],
            ['label' => 'Minggu 4+', 'leave' => 0, 'sick' => 0],
        ];

        foreach ($rows as $r) {
            $dayNum = (int) substr((string) $r['attendance_date'], 8, 2);
            $wIdx = min(3, intdiv(max(0, $dayNum - 1), 7));
            if ($r['status'] === 'leave') {
                $totalLeave++;
                $weeks[$wIdx]['leave']++;
            } elseif ($r['status'] === 'sick') {
                $totalSick++;
                $weeks[$wIdx]['sick']++;
            }
        }

        return [
            'month' => $month,
            'total_leave' => $totalLeave,
            'total_sick' => $totalSick,
            'by_week' => $weeks,
        ];
    }

    /**
     * @param list<array<string, mixed>> $employees
     * @param list<int> $workingDays
     * @return list<array<string, mixed>>
     */
    private function buildDisciplineRanking(array $employees, string $month, array $workingDays): array
    {
        if ($employees === []) {
            return [];
        }

        $startStr = $month . '-01';
        $monthEndStr = date('Y-m-t', strtotime($startStr) ?: time());
        $today = date('Y-m-d');
        $effectiveEndStr = $monthEndStr > $today ? $today : $monthEndStr;

        // Hitung jumlah hari kerja yang sudah berlangsung pada bulan terpilih
        $elapsedWorkingDays = 0;
        if ($effectiveEndStr >= $startStr) {
            $holidays = $this->holidays->mapInRange($startStr, $effectiveEndStr);
            $period = new DatePeriod(
                new DateTimeImmutable($startStr),
                new DateInterval('P1D'),
                (new DateTimeImmutable($effectiveEndStr))->modify('+1 day')
            );
            foreach ($period as $dt) {
                $d = $dt->format('Y-m-d');
                if (in_array((int) $dt->format('N'), $workingDays, true) && !isset($holidays[$d])) {
                    $elapsedWorkingDays++;
                }
            }
        }

        $empIds = array_map(static fn (array $e): int => (int) $e['id'], $employees);
        $rows = $this->attendancesForUsersInRange($empIds, $startStr, $monthEndStr);

        $statsByUser = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            if (!isset($statsByUser[$uid])) {
                $statsByUser[$uid] = ['on_time' => 0, 'late' => 0, 'late_minutes' => 0, 'leave' => 0, 'sick' => 0, 'checked_out' => 0];
            }
            $st = (string) $r['status'];
            if ($st === 'on_time') {
                $statsByUser[$uid]['on_time']++;
                if (!empty($r['checkout_at'])) {
                    $statsByUser[$uid]['checked_out']++;
                }
            } elseif ($st === 'late') {
                $statsByUser[$uid]['late']++;
                $statsByUser[$uid]['late_minutes'] += (int) $r['late_minutes'];
                if (!empty($r['checkout_at'])) {
                    $statsByUser[$uid]['checked_out']++;
                }
            } elseif ($st === 'leave') {
                $statsByUser[$uid]['leave']++;
            } elseif ($st === 'sick') {
                $statsByUser[$uid]['sick']++;
            }
        }

        $ranking = [];
        foreach ($employees as $emp) {
            $uid = (int) $emp['id'];
            $s = $statsByUser[$uid] ?? ['on_time' => 0, 'late' => 0, 'late_minutes' => 0, 'leave' => 0, 'sick' => 0, 'checked_out' => 0];
            $present = $s['on_time'] + $s['late'];
            $accounted = $present + $s['leave'] + $s['sick'];
            $absent = max(0, $elapsedWorkingDays - $accounted);

            // Skor kedisiplinan (0-100): berbasis kehadiran tepat waktu, pengurangan penalti keterlambatan & absen
            if ($elapsedWorkingDays === 0) {
                $score = 0.0;
            } else {
                $expectedActiveDays = max(1, $elapsedWorkingDays - $s['leave'] - $s['sick']);
                $onTimeRatio = ($s['on_time'] / $expectedActiveDays) * 100.0;
                $lateCredit = ($s['late'] / $expectedActiveDays) * 65.0;
                $minutePenalty = min(20.0, $s['late_minutes'] * 0.25);
                $score = max(0.0, min(100.0, round($onTimeRatio + $lateCredit - $minutePenalty, 1)));
            }

            $ranking[] = [
                'user_id' => $uid,
                'employee_code' => $emp['employee_code'],
                'full_name' => $emp['full_name'],
                'department_name' => $emp['department_name'] ?? 'Tanpa Departemen',
                'on_time_days' => $s['on_time'],
                'late_days' => $s['late'],
                'late_minutes' => $s['late_minutes'],
                'leave_days' => $s['leave'],
                'sick_days' => $s['sick'],
                'absent_days' => $absent,
                'working_days' => $elapsedWorkingDays,
                'discipline_score' => $score,
            ];
        }

        usort($ranking, static function (array $a, array $b): int {
            if ($a['discipline_score'] !== $b['discipline_score']) {
                return $b['discipline_score'] <=> $a['discipline_score'];
            }
            if ($a['late_minutes'] !== $b['late_minutes']) {
                return $a['late_minutes'] <=> $b['late_minutes'];
            }
            return strcmp((string) $a['full_name'], (string) $b['full_name']);
        });

        return $ranking;
    }
}
