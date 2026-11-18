<?php
use App\Services\AttendanceService;
$exportQuery = http_build_query([
    'month' => $filters['month'],
    'date' => $filters['date'],
    'department_id' => $filters['department_id'],
    'status' => $filters['status'],
    'search' => $filters['search'],
]);
?>

<section class="card accent-top">
  <div class="card-header">
    <div class="card-title-group">
      <h1>Laporan Kehadiran</h1>
      <p>Saring data kehadiran berdasarkan tanggal, bulan, departemen, status, atau nama karyawan lalu ekspor ke CSV.</p>
    </div>
    <a href="/admin/reports/export?<?= $e($exportQuery) ?>" class="btn btn-primary">Unduh CSV</a>
  </div>

  <form method="get" action="/admin/reports" class="filter-bar" style="margin-bottom:16px;">
    <div class="field">
      <label for="repMonth">Bulan</label>
      <input id="repMonth" name="month" type="month" value="<?= $e($filters['month']) ?>">
    </div>
    <div class="field">
      <label for="repDate">Tanggal Spesifik (opsional)</label>
      <input id="repDate" name="date" type="date" value="<?= $e($filters['date']) ?>">
    </div>
    <div class="field">
      <label for="repDept">Departemen</label>
      <select id="repDept" name="department_id">
        <option value="">Semua Departemen</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= $filters['department_id'] === (string) $d['id'] ? 'selected' : '' ?>><?= $e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="repStatus">Status</label>
      <select id="repStatus" name="status">
        <option value="">Semua Status</option>
        <option value="on_time" <?= $filters['status'] === 'on_time' ? 'selected' : '' ?>>Hadir Tepat Waktu</option>
        <option value="late" <?= $filters['status'] === 'late' ? 'selected' : '' ?>>Terlambat</option>
        <option value="not_checked_out" <?= $filters['status'] === 'not_checked_out' ? 'selected' : '' ?>>Belum Check-out</option>
        <option value="leave" <?= $filters['status'] === 'leave' ? 'selected' : '' ?>>Izin</option>
        <option value="sick" <?= $filters['status'] === 'sick' ? 'selected' : '' ?>>Sakit</option>
      </select>
    </div>
    <div class="field">
      <label for="repSearch">Nama / Kode</label>
      <input id="repSearch" name="search" type="search" value="<?= $e($filters['search']) ?>" placeholder="Cari karyawan...">
    </div>
    <div class="action-row">
      <button type="submit" class="btn btn-secondary">Tampilkan</button>
      <a href="/admin/reports" class="btn btn-ghost">Reset</a>
    </div>
  </form>

  <?php if (empty($records)): ?>
    <div class="empty-state">
      <strong>Tidak ada catatan absensi pada filter ini</strong>
      <span>Ubah bulan, tanggal, atau status pencarian untuk melihat data lain.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Karyawan</th>
            <th>Departemen</th>
            <th>Masuk</th>
            <th>Pulang</th>
            <th>Terlambat</th>
            <th>Jarak / Akurasi</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r):
            $desc = AttendanceService::describeStatus($r);
          ?>
            <tr>
              <td class="tabular"><?= $e($r['attendance_date']) ?></td>
              <td>
                <a href="/admin/employees/<?= (int) $r['user_id'] ?>" style="color:inherit;text-decoration:none;">
                  <strong><?= $e($r['full_name']) ?></strong>
                </a>
                <div class="muted tabular"><?= $e($r['employee_code']) ?></div>
              </td>
              <td><?= $e($r['department_name'] ?? '-') ?></td>
              <td class="tabular"><?= !empty($r['checkin_at']) ? $e(substr((string) $r['checkin_at'], 11, 5)) : '-' ?></td>
              <td class="tabular"><?= !empty($r['checkout_at']) ? $e(substr((string) $r['checkout_at'], 11, 5)) : '-' ?></td>
              <td class="tabular"><?= (int) $r['late_minutes'] ?> mnt</td>
              <td class="tabular">
                <?= $r['checkin_distance_m'] !== null ? $e(round((float) $r['checkin_distance_m'])) . ' m' : '-' ?>
                <?= $r['checkin_accuracy_m'] !== null ? ' (+/-' . $e(round((float) $r['checkin_accuracy_m'])) . 'm)' : '' ?>
              </td>
              <td><span class="badge badge-<?= $e($desc['tone']) ?>"><?= $e($desc['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
