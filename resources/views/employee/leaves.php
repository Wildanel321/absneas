<section class="card accent-top">
  <div class="card-header">
    <div class="card-title-group">
      <h1>Pengajuan Izin &amp; Sakit</h1>
      <p>Kirim permohonan izin atau sakit agar tercatat resmi oleh administrator.</p>
    </div>
  </div>

  <form method="post" action="/app/leaves" class="form-grid">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

    <div class="field">
      <label for="leaveType">Jenis Pengajuan</label>
      <select id="leaveType" name="type" required>
        <option value="leave">Izin</option>
        <option value="sick">Sakit</option>
      </select>
    </div>

    <div class="field">
      <label for="startDate">Tanggal Mulai</label>
      <input id="startDate" name="start_date" type="date" required value="<?= $e(date('Y-m-d')) ?>">
    </div>

    <div class="field">
      <label for="endDate">Tanggal Selesai</label>
      <input id="endDate" name="end_date" type="date" required value="<?= $e(date('Y-m-d')) ?>">
    </div>

    <div class="field" style="grid-column:1 / -1;">
      <label for="leaveReason">Alasan / Keterangan</label>
      <textarea id="leaveReason" name="reason" required minlength="5" maxlength="500" placeholder="Jelaskan keperluan izin atau kondisi sakit Anda"></textarea>
    </div>

    <div style="grid-column:1 / -1;">
      <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Daftar Pengajuan Anda</h2>
  <?php if (empty($requests)): ?>
    <div class="empty-state">
      <strong>Belum ada pengajuan izin atau sakit</strong>
      <span>Pengajuan yang Anda kirim akan tampil di sini beserta status persetujuannya.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Jenis</th>
            <th>Tanggal</th>
            <th>Alasan</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $req):
            $stMap = [
              'pending' => ['Menunggu', 'warning'],
              'approved' => ['Disetujui', 'success'],
              'rejected' => ['Ditolak', 'danger'],
            ];
            [$stLabel, $stTone] = $stMap[$req['status']] ?? ['Menunggu', 'neutral'];
          ?>
            <tr>
              <td><strong><?= $req['type'] === 'sick' ? 'Sakit' : 'Izin' ?></strong></td>
              <td class="tabular"><?= $e($req['start_date']) ?><?= $req['end_date'] !== $req['start_date'] ? ' s/d ' . $e($req['end_date']) : '' ?></td>
              <td>
                <?= $e($req['reason']) ?>
                <?php if (!empty($req['review_note'])): ?>
                  <div class="muted" style="font-size:0.8rem;">Catatan admin: <?= $e($req['review_note']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= $stTone ?>"><?= $stLabel ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
