<?php
// Panel hasil AI pada detail tiket (hanya tampil untuk petugas)
function render_ai_panel(array $user, string $ticketId, array $ticket, array $kbById, array $similar = []): void
{
    if (!is_staff($user)) {
        return;
    }
    $analysis = is_array($ticket['ai'] ?? null) ? $ticket['ai'] : null;
    $replyDraft = is_array($ticket['ai_reply'] ?? null) ? $ticket['ai_reply'] : null;
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
        <form method="post" action="<?= e(url('ai/analyze.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($ticketId) ?>"><input type="hidden" name="mode" value="similar">
            <button type="submit" class="btn btn-small btn-light">Cek tiket serupa dengan AI</button>
        </form>
    </div>

    <?php if ($similar): ?>
    <div class="ai-box">
        <h4>Tiket serupa dari merchant yang sama</h4>
        <ul class="similar-list">
        <?php foreach ($similar as $candidate): ?>
            <li>
                <a href="<?= e(url('tickets/view.php?id=' . urlencode($candidate['id']))) ?>"><?= e($candidate['title']) ?></a>
                <span class="badge s-<?= e($candidate['status']) ?>"><?= e(label(STATUSES, $candidate['status'])) ?></span>
                <span class="muted"><?= e(implode(', ', $candidate['reasons'])) ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php $similarAnalysis = is_array($ticket['ai_similar'] ?? null) ? $ticket['ai_similar'] : null;
        if ($similarAnalysis): ?>
            <p><strong>Penilaian AI</strong> <span class="muted"><?= e($similarAnalysis['checked_at'] ?? '') ?></span></p>
            <ul class="similar-list">
            <?php foreach ((array) ($similarAnalysis['results'] ?? []) as $row): ?>
                <li><a href="<?= e(url('tickets/view.php?id=' . urlencode($row['id']))) ?>">Tiket <?= e($row['id']) ?></a>:
                    <strong><?= !empty($row['same_issue']) ? 'kasus yang sama' : 'kasus berbeda' ?></strong> - <?= e($row['reason'] ?? '') ?></li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($analysis): $extracted = $analysis['extracted'] ?? []; ?>
    <div class="ai-box">
        <h4>Hasil analisis <span class="muted"><?= e($analysis['analyzed_at'] ?? '') ?></span></h4>
        <dl>
            <dt>Kategori disarankan</dt><dd><?= e(label(CATEGORIES, $analysis['category'] ?? '')) ?></dd>
            <dt>Prioritas disarankan</dt><dd><span class="badge p-<?= e($analysis['priority'] ?? '') ?>"><?= e(label(PRIORITIES, $analysis['priority'] ?? '')) ?></span> <?= e($analysis['priority_reason'] ?? '') ?></dd>
            <dt>ID transaksi</dt><dd><?= e($extracted['transaction_id'] ?? '') ?: '-' ?></dd>
            <dt>Nominal</dt><dd><?= e(rupiah($extracted['amount'] ?? null)) ?></dd>
            <dt>Metode bayar</dt><dd><?= e(label(PAYMENT_METHODS, $extracted['payment_method'] ?? '') ?: '-') ?></dd>
            <dt>Waktu kejadian</dt><dd><?= e($extracted['event_time'] ?? '') ?: '-' ?></dd>
            <dt>Kode error</dt><dd><?= e($extracted['error_code'] ?? '') ?: '-' ?></dd>
            <dt>Data yang masih kurang</dt><dd><?= !empty($analysis['missing_fields']) ? e(implode(', ', $analysis['missing_fields'])) : 'Lengkap' ?></dd>
        </dl>
        <form method="post" action="<?= e(url('ai/analyze.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($ticketId) ?>"><input type="hidden" name="mode" value="apply">
            <button type="submit" class="btn btn-small">Terapkan kategori dan prioritas</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($replyDraft): ?>
    <div class="ai-box">
        <h4>Ringkasan dan draf balasan <span class="muted"><?= e($replyDraft['summarized_at'] ?? '') ?></span></h4>
        <p><strong>Ringkasan kronologi:</strong><br><?= nl2br(e($replyDraft['summary'] ?? '')) ?></p>
        <p><strong>Draf balasan:</strong></p>
        <textarea id="ai-draft" rows="6" readonly><?= e($replyDraft['suggested_reply'] ?? '') ?></textarea>
        <p><button type="button" class="btn btn-small btn-light" id="ai-use-draft">Pakai draf pada catatan tindakan</button></p>
        <?php if (!empty($replyDraft['related_kb_ids'])): ?>
        <p><strong>Artikel panduan terkait:</strong>
        <?php foreach ($replyDraft['related_kb_ids'] as $kbId): if (!isset($kbById[$kbId])) {
            continue;
        } ?>
            <a href="<?= e(url('knowledge/view.php?id=' . urlencode($kbId))) ?>"><?= e($kbById[$kbId]) ?></a>
        <?php endforeach; ?></p>
        <?php endif; ?>
    </div>
    <script>
    (function () {
        var useDraftButton = document.getElementById('ai-use-draft');
        if (!useDraftButton) return;
        useDraftButton.addEventListener('click', function () {
            var followupForm = document.getElementById('followup-form');
            if (!followupForm) return;
            followupForm.querySelector('textarea[name=content]').value = document.getElementById('ai-draft').value;
            followupForm.querySelector('select[name=type]').value = 'balasan';
            followupForm.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    })();
    </script>
    <?php endif;
}
