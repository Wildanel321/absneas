<?php
use App\Services\AttendanceService;
$checkinTime = !empty($todayAtt['checkin_at']) ? substr((string) $todayAtt['checkin_at'], 11, 5) : '--:--';
$checkoutTime = !empty($todayAtt['checkout_at']) ? substr((string) $todayAtt['checkout_at'], 11, 5) : '--:--';
$needsCheckout = !empty($todayAtt['checkin_at']) && empty($todayAtt['checkout_at']);
$alreadyDone = !empty($todayAtt['checkin_at']) && !empty($todayAtt['checkout_at']);
$ctaLabel = $needsCheckout ? 'Scan QR Check-out' : ($alreadyDone ? 'Absensi Hari Ini Selesai' : 'Scan QR Check-in');
?>

<section class="emp-status-hero">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
    <div>
      <h1 style="margin-bottom:2px;">Halo, <?= $e($user['full_name']) ?></h1>
      <div class="muted" style="font-size:0.86rem;">
        <?= $e($user['employee_code']) ?>
        <?php if (!empty($user['department_name'])): ?>
          &middot; <?= $e($user['department_name']) ?>
        <?php endif; ?>
        &middot; <?= $e($today) ?>
      </div>
    </div>
    <span class="badge badge-<?= $e($todayDesc['tone']) ?>"><?= $e($todayDesc['label']) ?></span>
  </div>

  <div class="emp-times-row">
    <div class="time-box">
      <div class="time-box-label">Jam Masuk</div>
      <div class="time-box-val tabular"><?= $e($checkinTime) ?></div>
      <div class="muted" style="font-size:0.78rem;">Jadwal <?= $e($schedule['work_start_time']) ?></div>
    </div>
    <div class="time-box">
      <div class="time-box-label">Jam Pulang</div>
      <div class="time-box-val tabular"><?= $e($checkoutTime) ?></div>
      <div class="muted" style="font-size:0.78rem;">Jadwal <?= $e($schedule['work_end_time']) ?></div>
    </div>
  </div>

  <div class="action-row">
    <a href="/app/scan" class="btn btn-primary btn-block"><?= $e($ctaLabel) ?></a>
  </div>
</section>

<section class="card">
  <div class="card-header" style="margin-bottom:12px;">
    <h2>Ringkasan Bulan Ini</h2>
    <a href="/app/history" class="btn btn-ghost btn-sm">Lihat Semua</a>
  </div>
  <div class="emp-summary-grid">
    <div class="time-box">
      <div class="time-box-label">Tepat Waktu</div>
      <div class="time-box-val tabular"><?= (int) $summary['on_time'] ?></div>
    </div>
    <div class="time-box">
      <div class="time-box-label">Terlambat</div>
      <div class="time-box-val tabular"><?= (int) $summary['late'] ?></div>
    </div>
    <div class="time-box">
      <div class="time-box-label">Izin</div>
      <div class="time-box-val tabular"><?= (int) $summary['leave'] ?></div>
    </div>
    <div class="time-box">
      <div class="time-box-label">Sakit</div>
      <div class="time-box-val tabular"><?= (int) $summary['sick'] ?></div>
    </div>
  </div>
</section>

<section class="card">
  <div class="card-header">
    <h2>Kehadiran Terakhir</h2>
    <a href="/app/leaves" class="btn btn-secondary btn-sm">Ajukan Izin / Sakit</a>
  </div>

  <?php if (empty($recentHistory)): ?>
    <div class="empty-state">
      <strong>Belum ada riwayat absensi bulan ini</strong>
      <span>Tekan tombol Scan QR saat tiba di kantor untuk mencatat kehadiran pertama Anda.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Masuk</th>
            <th>Pulang</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentHistory as $row):
            $desc = AttendanceService::describeStatus($row);
          ?>
            <tr>
              <td class="tabular"><?= $e($row['attendance_date']) ?></td>
              <td class="tabular"><?= !empty($row['checkin_at']) ? $e(substr((string) $row['checkin_at'], 11, 5)) : '-' ?></td>
              <td class="tabular"><?= !empty($row['checkout_at']) ? $e(substr((string) $row['checkout_at'], 11, 5)) : '-' ?></td>
              <td><span class="badge badge-<?= $e($desc['tone']) ?>"><?= $e($desc['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
