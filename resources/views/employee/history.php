<?php use App\Services\AttendanceService; ?>

<section class="card accent-top">
  <div class="card-header">
    <div class="card-title-group">
      <h1>Riwayat Kehadiran</h1>
      <p>Daftar jam masuk, jam pulang, dan jarak lokasi tercatat untuk akun Anda.</p>
    </div>
  </div>

  <form method="get" action="/app/history" class="action-row" style="margin-bottom:16px;">
    <div class="field" style="flex:1;min-width:180px;">
      <label for="monthFilter">Pilih Bulan</label>
      <input id="monthFilter" name="month" type="month" value="<?= $e($month) ?>">
    </div>
    <div style="align-self:flex-end;">
      <button type="submit" class="btn btn-primary">Tampilkan</button>
    </div>
  </form>

  <?php if (empty($records)): ?>
    <div class="empty-state">
      <strong>Tidak ada data absensi pada bulan <?= $e($month) ?></strong>
      <span>Pilih bulan lain atau lakukan scan QR pada hari kerja untuk melihat catatan kehadiran.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Masuk</th>
            <th>Pulang</th>
            <th>Jarak</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r):
            $desc = AttendanceService::describeStatus($r);
          ?>
            <tr>
              <td class="tabular"><?= $e($r['attendance_date']) ?></td>
              <td class="tabular"><?= !empty($r['checkin_at']) ? $e(substr((string) $r['checkin_at'], 11, 5)) : '-' ?></td>
              <td class="tabular"><?= !empty($r['checkout_at']) ? $e(substr((string) $r['checkout_at'], 11, 5)) : '-' ?></td>
              <td class="tabular"><?= $r['checkin_distance_m'] !== null ? $e(round((float) $r['checkin_distance_m'])) . ' m' : '-' ?></td>
              <td><span class="badge badge-<?= $e($desc['tone']) ?>"><?= $e($desc['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
