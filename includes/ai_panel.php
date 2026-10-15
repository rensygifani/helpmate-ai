<?php
// Panel hasil AI pada detail tiket (hanya tampil untuk petugas)
function render_ai_panel(array $user, string $ticketId, array $t, array $kbById): void
{
    if (!is_staff($user)) {
        return;
    }
    $ai = is_array($t['ai'] ?? null) ? $t['ai'] : null;
    $rp = is_array($t['ai_reply'] ?? null) ? $t['ai_reply'] : null;
    ?>
    <h3>Bantuan AI (Gemini)</h3>
    <p class="muted">AI hanya memberi saran. Petugas memeriksa dan menyetujui hasilnya.</p>
    <div class="ai-actions">
        <form method="post" action="<?= e(url('ai/analyze.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($ticketId) ?>"><input type="hidden" name="mode" value="extract">
            <button type="submit" class="btn btn-small">Analisis keluhan</button>
        </form>
        <form method="post" action="<?= e(url('ai/analyze.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($ticketId) ?>"><input type="hidden" name="mode" value="summary">
            <button type="submit" class="btn btn-small">Ringkasan dan draf balasan</button>
        </form>
    </div>

    <?php if ($ai): $x = $ai['extracted'] ?? []; ?>
    <div class="ai-box">
        <h4>Hasil analisis <span class="muted"><?= e($ai['analyzed_at'] ?? '') ?></span></h4>
        <dl>
            <dt>Kategori disarankan</dt><dd><?= e(label(CATEGORIES, $ai['category'] ?? '')) ?></dd>
            <dt>Prioritas disarankan</dt><dd><span class="badge p-<?= e($ai['priority'] ?? '') ?>"><?= e(label(PRIORITIES, $ai['priority'] ?? '')) ?></span> <?= e($ai['priority_reason'] ?? '') ?></dd>
            <dt>ID transaksi</dt><dd><?= e($x['transaction_id'] ?? '') ?: '-' ?></dd>
            <dt>Nominal</dt><dd><?= e(rupiah($x['amount'] ?? null)) ?></dd>
            <dt>Metode bayar</dt><dd><?= e(label(PAYMENT_METHODS, $x['payment_method'] ?? '') ?: '-') ?></dd>
            <dt>Waktu kejadian</dt><dd><?= e($x['event_time'] ?? '') ?: '-' ?></dd>
            <dt>Kode error</dt><dd><?= e($x['error_code'] ?? '') ?: '-' ?></dd>
            <dt>Data yang masih kurang</dt><dd><?= !empty($ai['missing_fields']) ? e(implode(', ', $ai['missing_fields'])) : 'Lengkap' ?></dd>
        </dl>
        <form method="post" action="<?= e(url('ai/analyze.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($ticketId) ?>"><input type="hidden" name="mode" value="apply">
            <button type="submit" class="btn btn-small">Terapkan kategori dan prioritas</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($rp): ?>
    <div class="ai-box">
        <h4>Ringkasan dan draf balasan <span class="muted"><?= e($rp['summarized_at'] ?? '') ?></span></h4>
        <p><strong>Ringkasan kronologi:</strong><br><?= nl2br(e($rp['summary'] ?? '')) ?></p>
        <p><strong>Draf balasan:</strong></p>
        <textarea id="ai-draft" rows="6" readonly><?= e($rp['suggested_reply'] ?? '') ?></textarea>
        <p><button type="button" class="btn btn-small btn-light" id="ai-use-draft">Pakai draf pada catatan tindakan</button></p>
        <?php if (!empty($rp['related_kb_ids'])): ?>
        <p><strong>Artikel panduan terkait:</strong>
        <?php foreach ($rp['related_kb_ids'] as $kid): if (!isset($kbById[$kid])) continue; ?>
            <a href="<?= e(url('knowledge/view.php?id=' . urlencode($kid))) ?>"><?= e($kbById[$kid]) ?></a>
        <?php endforeach; ?></p>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        var b = document.getElementById('ai-use-draft');
        if (!b) return;
        b.addEventListener('click', function () {
            var f = document.getElementById('followup-form');
            if (!f) return;
            f.querySelector('textarea[name=content]').value = document.getElementById('ai-draft').value;
            f.querySelector('select[name=type]').value = 'balasan';
            f.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    })();
    </script>
    <?php endif;
}
