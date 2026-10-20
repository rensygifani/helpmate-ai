<?php
// Fungsi-fungsi pendukung untuk modul Tiket (validasi input, hak akses, lampiran, dan form)

function ticket_input(array $in, bool $staff): array
{
    $errors = [];
    $clean  = [
        'title'          => trim((string) ($in['title'] ?? '')),
        'description'    => trim((string) ($in['description'] ?? '')),
        'category'       => (string) ($in['category'] ?? 'lainnya'),
        'payment_method' => (string) ($in['payment_method'] ?? ''),
        'transaction_id' => trim((string) ($in['transaction_id'] ?? '')),
        'amount'         => trim((string) ($in['amount'] ?? '')),
    ];

    if ($clean['title'] === '' || mb_strlen($clean['title']) > 120) {
        $errors[] = 'Judul wajib diisi (maksimal 120 karakter).';
    }
    if ($clean['description'] === '' || mb_strlen($clean['description']) > 3000) {
        $errors[] = 'Deskripsi wajib diisi (maksimal 3000 karakter).';
    }
    if (!isset(CATEGORIES[$clean['category']])) {
        $errors[] = 'Kategori tidak valid.';
    }
    if ($clean['payment_method'] !== '' && !isset(PAYMENT_METHODS[$clean['payment_method']])) {
        $errors[] = 'Metode bayar tidak valid.';
    }
    if (mb_strlen($clean['transaction_id']) > 60) {
        $errors[] = 'ID transaksi maksimal 60 karakter.';
    }
    if ($clean['amount'] === '') {
        $clean['amount'] = null;
    } elseif (!ctype_digit($clean['amount'])) {
        $errors[] = 'Nominal harus berupa angka tanpa titik atau koma.';
    } else {
        $clean['amount'] = (int) $clean['amount'];
    }

    if ($staff) {
        $priority = (string) ($in['priority'] ?? 'sedang');
        $status   = (string) ($in['status'] ?? 'baru');
        if (!isset(PRIORITIES[$priority])) {
            $errors[] = 'Prioritas tidak valid.';
        }
        if (!isset(STATUSES[$status])) {
            $errors[] = 'Status tidak valid.';
        }
        $clean['priority'] = $priority;
        $clean['status']   = $status;
    }
    return [$clean, $errors];
}

function can_edit_ticket(array $user, array $ticket): bool
{
    if (is_staff($user)) {
        return true;
    }
    return ($ticket['owner_uid'] ?? '') === $user['uid'] && ($ticket['status'] ?? '') === 'baru';
}

function can_delete_ticket(array $user, array $ticket): bool
{
    if (in_array($user['role'] ?? '', ['admin', 'super_admin'], true)) {
        return true;
    }
    return ($ticket['owner_uid'] ?? '') === $user['uid'] && ($ticket['status'] ?? '') === 'baru';
}

function can_view_ticket(array $user, array $ticket): bool
{
    return is_staff($user) || ($ticket['owner_uid'] ?? '') === $user['uid'];
}

// $files = null  -> form tanpa kolom lampiran (mis. halaman Ubah)
// $files = array -> form dengan kolom lampiran, berisi lampiran yang sudah dipilih
function render_ticket_form(array $t, bool $staff, string $submitLabel, ?array $files = null): void
{
    ?>
<form method="post" class="card form">
    <?= csrf_field() ?>
    <label>Judul kendala
        <input type="text" name="title" value="<?= e($t['title'] ?? '') ?>" maxlength="120" required>
    </label>
    <label>Kategori
        <select name="category"><?php render_options(CATEGORIES, $t['category'] ?? 'lainnya'); ?></select>
    </label>
    <label>Deskripsi (ceritakan kronologi kejadian)
        <textarea name="description" rows="6" maxlength="3000" required><?= e($t['description'] ?? '') ?></textarea>
    </label>
    <p class="hint">Jangan menulis nomor kartu lengkap, PIN, OTP, atau password. Data seperti itu otomatis disamarkan sebelum disimpan.</p>
    <?php if ($files !== null): ?>
    <div id="kb-suggest" class="kb-suggest" hidden></div>
    <script>
    (function () {
        var box = document.getElementById('kb-suggest');
        var t = document.querySelector('[name=title]'), d = document.querySelector('[name=description]'), timer = null;
        if (!box || !t || !d) return;
        function render(items) {
            box.innerHTML = '';
            if (!items.length) { box.hidden = true; return; }
            var h = document.createElement('h4');
            h.textContent = 'Panduan yang mungkin membantu sebelum Anda mengirim tiket';
            var ul = document.createElement('ul');
            items.forEach(function (it) {
                var li = document.createElement('li'), a = document.createElement('a'), s = document.createElement('span');
                a.href = it.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = it.title;
                s.className = 'snip'; s.textContent = it.snippet;
                li.appendChild(a); li.appendChild(s); ul.appendChild(li);
            });
            box.appendChild(h); box.appendChild(ul); box.hidden = false;
        }
        function run() {
            var q = (t.value + ' ' + d.value).trim();
            if (q.length < 8) { box.hidden = true; return; }
            fetch(<?= json_encode(url('knowledge/suggest.php')) ?> + '?q=' + encodeURIComponent(q), {credentials: 'same-origin'})
                .then(function (r) { return r.json(); })
                .then(function (j) { render(j.items || []); })
                .catch(function () {});
        }
        [t, d].forEach(function (el) {
            el.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 600); });
        });
    })();
    </script>
    <?php endif; ?>
    <div class="row">
        <label>Metode bayar
            <select name="payment_method"><?php render_options(PAYMENT_METHODS, $t['payment_method'] ?? '', true); ?></select>
        </label>
        <label>ID transaksi
            <input type="text" name="transaction_id" value="<?= e($t['transaction_id'] ?? '') ?>" maxlength="60">
        </label>
        <label>Nominal (rupiah, angka saja)
            <input type="text" name="amount" value="<?= e((string) ($t['amount'] ?? '')) ?>" inputmode="numeric">
        </label>
    </div>
    <?php if ($files !== null) { render_attachment_field($files); } ?>
    <?php if ($staff): ?>
    <div class="row">
        <label>Prioritas
            <select name="priority"><?php render_options(PRIORITIES, $t['priority'] ?? 'sedang'); ?></select>
        </label>
        <label>Status
            <select name="status"><?php render_options(STATUSES, $t['status'] ?? 'baru'); ?></select>
        </label>
    </div>
    <?php endif; ?>
    <div>
        <button type="submit" class="btn"><?= e($submitLabel) ?></button>
        <a class="btn btn-light" href="<?= e(url('tickets/index.php')) ?>">Batal</a>
    </div>
</form>
<?php
}

// Ambil dan validasi lampiran gambar yang dikirim dari browser (sudah dikecilkan oleh JavaScript)
function ticket_attachments(array $post): array
{
    $names  = $post['att_name'] ?? [];
    $datas  = $post['att_data'] ?? [];
    $files  = [];
    $errors = [];
    if (!is_array($names) || !is_array($datas)) {
        return [[], []];
    }
    foreach ($datas as $i => $d) {
        if (count($files) >= 3) {
            $errors[] = 'Maksimal 3 lampiran.';
            break;
        }
        $d = (string) $d;
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $d, $m)) {
            $errors[] = 'Lampiran harus berupa gambar JPG, PNG, atau WebP.';
            continue;
        }
        if (strlen($d) > 450000) {
            $errors[] = 'Salah satu lampiran terlalu besar.';
            continue;
        }
        $raw = base64_decode(substr($d, strlen($m[0])), true);
        if ($raw === false || @getimagesizefromstring($raw) === false) {
            $errors[] = 'Salah satu lampiran bukan gambar yang valid.';
            continue;
        }
        $name = mb_substr((string) preg_replace('/[^\w .()-]/u', '', (string) ($names[$i] ?? '')), 0, 60);
        $files[] = ['name' => $name !== '' ? $name : 'bukti', 'data' => $d];
    }
    return [$files, array_values(array_unique($errors))];
}

function render_attachment_field(array $files = []): void
{
    $json = json_encode($files, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div class="attach">
        <span class="attach-title">Bukti transaksi / foto kendala <span class="muted">(opsional, maksimal 3 gambar)</span></span>
        <input type="file" id="att-input" accept="image/*" multiple>
        <div class="att-preview" id="att-preview"></div>
        <div id="att-hidden"></div>
        <div class="field-error" id="att-error"></div>
    </div>
    <script>
    (function () {
        var input = document.getElementById('att-input');
        var MAX = 3, LIMIT = 420000;
        var items = <?= $json ?: '[]' ?>;
        var prev = document.getElementById('att-preview');
        var hid = document.getElementById('att-hidden');
        var err = document.getElementById('att-error');

        function render() {
            prev.innerHTML = ''; hid.innerHTML = '';
            items.forEach(function (it, i) {
                var d = document.createElement('div'); d.className = 'att-item';
                var im = document.createElement('img'); im.src = it.data; im.alt = it.name;
                var x = document.createElement('button'); x.type = 'button'; x.textContent = '\u00d7';
                x.setAttribute('aria-label', 'Hapus lampiran');
                x.onclick = function () { items.splice(i, 1); render(); };
                d.appendChild(im); d.appendChild(x); prev.appendChild(d);
                ['name', 'data'].forEach(function (k) {
                    var h = document.createElement('input');
                    h.type = 'hidden'; h.name = 'att_' + k + '[]'; h.value = it[k];
                    hid.appendChild(h);
                });
            });
        }

        // Kecilkan gambar ke maks. 1280 px dan kompres JPEG sampai di bawah batas ukuran
        function shrink(file) {
            return new Promise(function (res, rej) {
                var img = new Image(), url = URL.createObjectURL(file);
                img.onload = function () {
                    var s = Math.min(1, 1280 / Math.max(img.width, img.height));
                    var w = Math.round(img.width * s), h = Math.round(img.height * s), q = 0.8, data = '';
                    for (var n = 0; n < 8; n++) {
                        var c = document.createElement('canvas'); c.width = w; c.height = h;
                        var ctx = c.getContext('2d');
                        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h); ctx.drawImage(img, 0, 0, w, h);
                        data = c.toDataURL('image/jpeg', q);
                        if (data.length <= LIMIT) break;
                        q = Math.max(0.4, q - 0.1);
                        if (q <= 0.5) { w = Math.round(w * 0.8); h = Math.round(h * 0.8); }
                    }
                    URL.revokeObjectURL(url);
                    data.length <= LIMIT ? res(data) : rej();
                };
                img.onerror = function () { URL.revokeObjectURL(url); rej(); };
                img.src = url;
            });
        }

        input.addEventListener('change', function () {
            err.textContent = '';
            var chain = Promise.resolve();
            Array.prototype.slice.call(input.files).forEach(function (f) {
                chain = chain.then(function () {
                    if (items.length >= MAX) { err.textContent = 'Maksimal ' + MAX + ' gambar.'; return; }
                    if (!/^image\//.test(f.type)) { err.textContent = 'Hanya file gambar (JPG, PNG, WebP).'; return; }
                    return shrink(f).then(
                        function (d) { items.push({ name: f.name, data: d }); },
                        function () { err.textContent = 'Gambar "' + f.name + '" tidak bisa diproses.'; }
                    );
                });
            });
            chain.then(function () { render(); input.value = ''; });
        });
        render();
    })();
    </script>
    <?php
}