<?php
$kpis = $dashboard['kpis'];
$filters = $dashboard['filters'];
$notOut = $dashboard['not_checked_out'];
$roster = $dashboard['daily_roster'];
$ranking = $dashboard['discipline_ranking'];
?>

<section class="card" style="padding:16px 20px;">
  <div class="card-header" style="margin-bottom:12px;">
    <div class="card-title-group">
      <h1>Pemantauan Kehadiran</h1>
      <p id="dashboardUpdatedAt" class="tabular">Diperbarui <?= $e(substr((string) $dashboard['generated_at'], 11, 8)) ?></p>
    </div>
    <div class="action-row">
      <button type="button" id="refreshDashboardBtn" class="btn btn-secondary btn-sm">Muat Ulang</button>
      <button type="button" id="toggleLiveRefreshBtn" class="btn btn-ghost btn-sm" aria-pressed="true">Jeda Pembaruan Otomatis</button>
    </div>
  </div>

  <div id="dashboardLiveError" class="alert alert-warning" role="status" hidden></div>

  <form id="dashboardFilterForm" method="get" action="/admin" class="filter-bar">
    <div class="field">
      <label for="fDate">Tanggal Harian</label>
      <input id="fDate" name="date" type="date" value="<?= $e($filters['date']) ?>">
    </div>
    <div class="field">
      <label for="fMonth">Bulan Grafik &amp; Ranking</label>
      <input id="fMonth" name="month" type="month" value="<?= $e($filters['month']) ?>">
    </div>
    <div class="field">
      <label for="fDept">Departemen</label>
      <select id="fDept" name="department_id">
        <option value="">Semua Departemen</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= (int) $dept['id'] ?>" <?= (string) ($filters['department_id'] ?? '') === (string) $dept['id'] ? 'selected' : '' ?>>
            <?= $e($dept['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="fSearch">Nama / Kode Karyawan</label>
      <input id="fSearch" name="search" type="search" value="<?= $e($filters['search']) ?>" placeholder="Cari nama atau EMP-...">
    </div>
    <div class="action-row">
      <button type="submit" class="btn btn-primary">Terapkan Filter</button>
      <a href="/admin" class="btn btn-ghost">Reset</a>
    </div>
  </form>
</section>

<section class="kpi-layout" aria-label="Ringkasan Kehadiran Tanggal Terpilih">
  <div class="kpi-hero">
    <div>
      <div class="kpi-hero-label">Hadir pada <?= $e($filters['date']) ?></div>
      <div class="kpi-hero-value tabular">
        <span id="kpiPresent"><?= (int) $kpis['present_today'] ?></span>
        <span style="font-size:1.15rem;font-weight:500;color:#cbd5e1;"> / <span id="kpiTotal"><?= (int) $kpis['total_employees'] ?></span> karyawan</span>
      </div>
    </div>
    <div class="kpi-hero-meta">
      <span>Tepat waktu: <strong id="kpiOnTime" class="tabular" style="color:#fff;"><?= (int) $kpis['on_time_today'] ?></strong></span>
      <span>Belum check-out: <strong id="kpiNotOut" class="tabular" style="color:#fcd34d;"><?= (int) $kpis['not_checked_out_count'] ?></strong></span>
      <?php if (!$dashboard['is_working_day']): ?>
        <span class="badge badge-info">Hari non-kerja / libur</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="kpi-grid">
    <div class="kpi-card">
      <span class="kpi-label">Terlambat</span>
      <span id="kpiLate" class="kpi-value tabular"><?= (int) $kpis['late_today'] ?></span>
      <span class="kpi-sub">Melewati toleransi</span>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Izin</span>
      <span id="kpiLeave" class="kpi-value tabular"><?= (int) $kpis['leave_today'] ?></span>
      <span class="kpi-sub">Disetujui</span>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Sakit</span>
      <span id="kpiSick" class="kpi-value tabular"><?= (int) $kpis['sick_today'] ?></span>
      <span class="kpi-sub">Disetujui</span>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Tidak Hadir</span>
      <span id="kpiAbsent" class="kpi-value tabular"><?= (int) $kpis['absent_today'] ?></span>
      <span class="kpi-sub">Tanpa keterangan</span>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Menunggu Review</span>
      <span class="kpi-value tabular"><?= (int) $kpis['pending_leaves'] ?></span>
      <span class="kpi-sub"><a href="/admin/leaves" style="color:var(--accent);font-weight:600;">Tinjau izin/sakit</a></span>
    </div>
    <div class="kpi-card">
      <span class="kpi-label">Total Karyawan Aktif</span>
      <span class="kpi-value tabular"><?= (int) $kpis['total_employees'] ?></span>
      <span class="kpi-sub"><a href="/admin/employees" style="color:var(--accent);font-weight:600;">Kelola karyawan</a></span>
    </div>
  </div>
</section>

<section class="split-grid">
  <div class="card">
    <div class="card-header">
      <div class="card-title-group">
        <h2>Daftar Kehadiran (<?= $e($filters['date']) ?>)</h2>
        <p>Status masuk, pulang, dan jarak GPS setiap karyawan pada tanggal terpilih.</p>
      </div>
      <a href="/admin/reports?date=<?= urlencode((string) $filters['date']) ?>" class="btn btn-secondary btn-sm">Laporan Lengkap</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Karyawan</th>
            <th>Departemen</th>
            <th>Masuk</th>
            <th>Pulang</th>
            <th>Jarak</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="dailyRosterBody">
          <?php if (empty($roster)): ?>
            <tr>
              <td colspan="6">
                <div class="empty-state">
                  <strong>Belum ada karyawan aktif</strong>
                  <span>Tambahkan karyawan terlebih dahulu melalui menu Karyawan.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($roster as $r): ?>
              <tr>
                <td>
                  <a href="/admin/employees/<?= (int) $r['user_id'] ?>" style="color:inherit;text-decoration:none;">
                    <strong><?= $e($r['full_name']) ?></strong>
                  </a>
                  <div class="muted"><?= $e($r['employee_code']) ?></div>
                </td>
                <td><?= $e($r['department_name']) ?></td>
                <td class="tabular"><?= !empty($r['checkin_at']) ? $e(substr((string) $r['checkin_at'], 11, 5)) : '-' ?></td>
                <td class="tabular"><?= !empty($r['checkout_at']) ? $e(substr((string) $r['checkout_at'], 11, 5)) : '-' ?></td>
                <td class="tabular"><?= $r['checkin_distance_m'] !== null ? $e(round((float) $r['checkin_distance_m'])) . ' m' : '-' ?></td>
                <td><span class="badge badge-<?= $e($r['status_tone']) ?>"><?= $e($r['status_label']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card accent-top">
    <div class="card-header">
      <div class="card-title-group">
        <h2>Belum Check-out</h2>
        <p>Karyawan yang sudah check-in namun belum memindai QR pulang.</p>
      </div>
    </div>

    <div id="notCheckedOutBody">
      <?php if (empty($notOut)): ?>
        <div class="empty-state">
          <strong>Semua karyawan hadir sudah check-out</strong>
          <span>Atau belum ada karyawan yang check-in pada tanggal terpilih.</span>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Karyawan</th>
                <th>Departemen</th>
                <th>Jam Masuk</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($notOut as $item): ?>
                <tr>
                  <td>
                    <strong><?= $e($item['full_name']) ?></strong>
                    <div class="muted"><?= $e($item['employee_code']) ?></div>
                  </td>
                  <td><?= $e($item['department_name']) ?></td>
                  <td class="tabular"><?= $e(substr((string) $item['checkin_at'], 11, 5)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="charts-grid" aria-label="Grafik Kehadiran dan Keterlambatan">
  <div class="card">
    <div class="card-header">
      <div class="card-title-group">
        <h2>Kehadiran harian dalam 14 hari terakhir</h2>
        <p>Perbandingan hadir tepat waktu, terlambat, izin, dan sakit per tanggal.</p>
      </div>
    </div>
    <div id="chartDailyAttendance" class="chart-host"></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title-group">
        <h2>Jumlah keterlambatan harian (14 hari terakhir)</h2>
        <p>Berapa karyawan datang melewati batas toleransi setiap hari.</p>
      </div>
    </div>
    <div id="chartDailyLateness" class="chart-host"></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title-group">
        <h2>Tren kehadiran bulanan tahun <?= $e(substr((string) $filters['month'], 0, 4)) ?></h2>
        <p>Akumulasi kehadiran tepat waktu dan terlambat dari Januari hingga Desember.</p>
      </div>
    </div>
    <div id="chartMonthlyTrend" class="chart-host"></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title-group">
        <h2>Distribusi izin dan sakit (<?= $e($filters['month']) ?>)</h2>
        <p>Jumlah hari izin dan sakit disetujui per pekan dalam bulan terpilih.</p>
      </div>
    </div>
    <div id="chartLeaveSick" class="chart-host"></div>
  </div>
</section>

<section class="card">
  <div class="card-header">
    <div class="card-title-group">
      <h2>Ranking Kedisiplinan Karyawan (<?= $e($filters['month']) ?>)</h2>
      <p>Diurutkan berdasarkan rasio tepat waktu, total menit keterlambatan, dan hari tanpa keterangan.</p>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Peringkat</th>
          <th>Karyawan</th>
          <th>Tepat Waktu</th>
          <th>Terlambat</th>
          <th>Tidak Hadir</th>
          <th>Skor Kedisiplinan</th>
        </tr>
      </thead>
      <tbody id="rankingBody">
        <?php if (empty($ranking)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <strong>Belum ada data ranking</strong>
                <span>Tambahkan karyawan aktif untuk menghitung skor kedisiplinan bulanan.</span>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($ranking as $idx => $r): ?>
            <tr>
              <td class="tabular"><strong>#<?= $idx + 1 ?></strong></td>
              <td>
                <strong><?= $e($r['full_name']) ?></strong>
                <div class="muted"><?= $e($r['department_name']) ?></div>
              </td>
              <td class="tabular"><?= (int) $r['on_time_days'] ?></td>
              <td class="tabular"><?= (int) $r['late_days'] ?> (<?= (int) $r['late_minutes'] ?> mnt)</td>
              <td class="tabular"><?= (int) $r['absent_days'] ?></td>
              <td style="min-width:130px;">
                <div class="tabular" style="font-weight:700;margin-bottom:4px;"><?= number_format((float) $r['discipline_score'], 1) ?>%</div>
                <div class="progress-track">
                  <div class="progress-fill" style="width:<?= min(100, max(0, (float) $r['discipline_score'])) ?>%"></div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<script id="dashboardInitialData" type="application/json"><?= json_encode($dashboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
