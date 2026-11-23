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
  <div class="card-header no-print">
    <div class="card-title-group">
      <h1>Laporan Kehadiran</h1>
      <p>Saring data kehadiran berdasarkan tanggal, bulan, departemen, status, atau nama karyawan lalu cetak atau ekspor ke CSV.</p>
    </div>
    <div class="action-row">
      <button type="button" onclick="window.print()" class="btn btn-secondary">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        <span>Cetak Laporan</span>
      </button>
      <a href="/admin/reports/export?<?= $e($exportQuery) ?>" class="btn btn-primary">Unduh CSV</a>
    </div>
  </div>

  <div class="print-only print-header">
    <div style="border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px;">
      <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
          <h2 style="margin:0 0 4px 0; font-size:1.35rem; text-transform:uppercase; letter-spacing:0.5px;">Smart Attendance</h2>
          <div style="font-size:1.05rem; font-weight:700; color:#1a1a1a;"><?= $e($officeName ?? 'Kantor Pusat') ?></div>
          <div style="font-size:0.85rem; color:#444; margin-top:2px;">LAPORAN KEHADIRAN DAN KEDISIPLINAN KARYAWAN</div>
        </div>
        <div style="text-align:right; font-size:0.82rem; color:#333;">
          <div><strong>Periode:</strong> <?= $e($filters['month'] ?: 'Semua') ?><?= !empty($filters['date']) ? ' (' . $e($filters['date']) . ')' : '' ?></div>
          <div><strong>Departemen:</strong> <?= !empty($filters['department_id']) ? 'Sesuai Filter' : 'Semua Departemen' ?></div>
          <div><strong>Dicetak pada:</strong> <?= date('d/m/Y H:i') ?></div>
        </div>
      </div>
    </div>
  </div>

  <form method="get" action="/admin/reports" class="filter-bar no-print" style="margin-bottom:16px;">
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
            <th>Foto Selfie</th>
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
              <td class="tabular"><?= (int) $r['late_minutes'] > 0 ? (int) $r['late_minutes'] . ' mnt' : '-' ?></td>
              <td class="tabular">
                <?= $r['checkin_distance_m'] !== null ? $e(round((float) $r['checkin_distance_m'])) . ' m' : '-' ?>
                <?= $r['checkin_accuracy_m'] !== null ? ' (+/-' . $e(round((float) $r['checkin_accuracy_m'])) . 'm)' : '' ?>
              </td>
              <td style="text-align:center;">
                <?php if (!empty($r['checkin_photo'])): ?>
                  <a href="/photo/<?= $e($r['checkin_photo']) ?>" target="_blank" rel="noopener" title="Lihat Foto Selfie">
                    <img src="/photo/<?= $e($r['checkin_photo']) ?>" alt="Selfie" width="36" height="36" style="border-radius:4px;object-fit:cover;border:1px solid var(--border);vertical-align:middle;">
                  </a>
                <?php else: ?>
                  <span class="muted" style="font-size:0.75rem;">-</span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= $e($desc['tone']) ?>"><?= $e($desc['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="print-only print-signatures" style="margin-top:36px;page-break-inside:avoid;">
      <div style="display:flex;justify-content:space-between;align-items:flex-end;">
        <div style="text-align:center;min-width:220px;">
          <div style="font-size:0.88rem;">Mengetahui,</div>
          <div style="font-size:0.95rem;font-weight:700;margin-top:2px;">Pimpinan / Kepala Kantor</div>
          <div style="height:65px;"></div>
          <div style="border-bottom:1px solid #000;width:100%;"></div>
          <div style="font-size:0.8rem;margin-top:4px;color:#555;">( Tanda Tangan &amp; Cap )</div>
        </div>
        <div style="text-align:center;min-width:220px;">
          <div style="font-size:0.88rem;"><?= $e($officeName ?? 'Kantor') ?>, <?= date('d M Y') ?></div>
          <div style="font-size:0.95rem;font-weight:700;margin-top:2px;">Bagian Kepegawaian / HRD</div>
          <div style="height:65px;"></div>
          <div style="border-bottom:1px solid #000;width:100%;"></div>
          <div style="font-size:0.8rem;margin-top:4px;color:#555;">( Administrator )</div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>
