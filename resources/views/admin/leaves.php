<section class="card accent-top">
  <div class="card-header">
    <div class="card-title-group">
      <h1>Pengajuan Izin &amp; Sakit</h1>
      <p>Pengajuan yang disetujui otomatis memperbarui status kehadiran pada hari kerja terkait.</p>
    </div>
  </div>

  <form method="get" action="/admin/leaves" class="filter-bar" style="margin-bottom:16px;">
    <div class="field">
      <label for="lvStatus">Status</label>
      <select id="lvStatus" name="status">
        <option value="">Semua Status</option>
        <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Menunggu Review</option>
        <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>Disetujui</option>
        <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
      </select>
    </div>
    <div class="field">
      <label for="lvType">Jenis</label>
      <select id="lvType" name="type">
        <option value="">Izin &amp; Sakit</option>
        <option value="leave" <?= $filters['type'] === 'leave' ? 'selected' : '' ?>>Izin</option>
        <option value="sick" <?= $filters['type'] === 'sick' ? 'selected' : '' ?>>Sakit</option>
      </select>
    </div>
    <div class="field">
      <label for="lvDept">Departemen</label>
      <select id="lvDept" name="department_id">
        <option value="">Semua Departemen</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= $filters['department_id'] === (string) $d['id'] ? 'selected' : '' ?>><?= $e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="lvSearch">Nama / Kode</label>
      <input id="lvSearch" name="search" type="search" value="<?= $e($filters['search']) ?>" placeholder="Cari karyawan...">
    </div>
    <div class="action-row">
      <button type="submit" class="btn btn-secondary">Filter</button>
      <a href="/admin/leaves" class="btn btn-ghost">Reset</a>
    </div>
  </form>

  <?php if (empty($items)): ?>
    <div class="empty-state">
      <strong>Tidak ada pengajuan izin atau sakit</strong>
      <span>Pengajuan yang dikirim karyawan melalui PWA akan muncul di sini.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Karyawan</th>
            <th>Jenis</th>
            <th>Rentang Tanggal</th>
            <th>Alasan</th>
            <th>Status</th>
            <th>Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item):
            $stMap = [
              'pending' => ['Menunggu', 'warning'],
              'approved' => ['Disetujui', 'success'],
              'rejected' => ['Ditolak', 'danger'],
            ];
            [$stLabel, $stTone] = $stMap[$item['status']] ?? ['Menunggu', 'neutral'];
          ?>
            <tr>
              <td>
                <strong><?= $e($item['full_name']) ?></strong>
                <div class="muted"><?= $e($item['employee_code']) ?> &middot; <?= $e($item['department_name'] ?? '-') ?></div>
              </td>
              <td><span class="badge badge-info"><?= $item['type'] === 'sick' ? 'Sakit' : 'Izin' ?></span></td>
              <td class="tabular"><?= $e($item['start_date']) ?><?= $item['end_date'] !== $item['start_date'] ? ' s/d ' . $e($item['end_date']) : '' ?></td>
              <td>
                <div><?= $e($item['reason']) ?></div>
                <?php if (!empty($item['review_note'])): ?>
                  <div class="muted" style="font-size:0.8rem;">Catatan: <?= $e($item['review_note']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= $stTone ?>"><?= $stLabel ?></span></td>
              <td>
                <?php if ($item['status'] === 'pending'): ?>
                  <form method="post" action="/admin/leaves/<?= (int) $item['id'] ?>/review" style="display:flex;flex-direction:column;gap:6px;min-width:200px;">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <label for="note-<?= (int) $item['id'] ?>" class="sr-only">Catatan review</label>
                    <input id="note-<?= (int) $item['id'] ?>" name="review_note" type="text" placeholder="Catatan opsional" style="min-height:36px;">
                    <div class="action-row">
                      <button type="submit" name="decision" value="approved" class="btn btn-success btn-sm">Setujui</button>
                      <button type="submit" name="decision" value="rejected" class="btn btn-danger btn-sm">Tolak</button>
                    </div>
                  </form>
                <?php else: ?>
                  <span class="muted" style="font-size:0.82rem;">
                    Oleh <?= $e($item['reviewer_name'] ?? 'Admin') ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
