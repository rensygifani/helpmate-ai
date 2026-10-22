<?php
// Fungsi-fungsi pendukung untuk modul Tiket (validasi input, hak akses, lampiran, dan form)

function ticket_input(array $input, bool $staff): array
{
    $errors = [];
    $clean = [
        'title' => trim((string) ($input['title'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
        'category' => (string) ($input['category'] ?? 'lainnya'),
        'payment_method' => (string) ($input['payment_method'] ?? ''),
        'transaction_id' => trim((string) ($input['transaction_id'] ?? '')),
        'amount' => trim((string) ($input['amount'] ?? '')),
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
        $priority = (string) ($input['priority'] ?? 'sedang');
        $status = (string) ($input['status'] ?? 'baru');
        if (!isset(PRIORITIES[$priority])) {
            $errors[] = 'Prioritas tidak valid.';
        }
        if (!isset(STATUSES[$status])) {
            $errors[] = 'Status tidak valid.';
        }
        $clean['priority'] = $priority;
        $clean['status'] = $status;
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
function render_ticket_form(array $ticket, bool $staff, string $submitLabel, ?array $files = null): void
{
    ?>
<form method="post" class="card form">
    <?= csrf_field() ?>
    <label>Judul kendala
        <input type="text" name="title" value="<?= e($ticket['title'] ?? '') ?>" maxlength="120" required>
    </label>
    <label>Kategori
        <select name="category"><?php render_options(CATEGORIES, $ticket['category'] ?? 'lainnya'); ?></select>
    </label>
    <label>Deskripsi (ceritakan kronologi kejadian)
        <textarea name="description" rows="6" maxlength="3000" required><?= e($ticket['description'] ?? '') ?></textarea>
    </label>
    <p class="hint">Jangan menulis nomor kartu lengkap, PIN, OTP, atau password. Data seperti itu otomatis disamarkan sebelum disimpan.</p>
    <?php if ($files !== null): ?>
    <div id="kb-suggest" class="kb-suggest" hidden></div>
    <script>
    (function () {
        var box = document.getElementById('kb-suggest');
        var titleInput = document.querySelector('[name=title]'), descriptionInput = document.querySelector('[name=description]'), timer = null;
        if (!box || !titleInput || !descriptionInput) return;
        function render(articles) {
            box.innerHTML = '';
            if (!articles.length) { box.hidden = true; return; }
            var heading = document.createElement('h4');
            heading.textContent = 'Panduan yang mungkin membantu sebelum Anda mengirim tiket';
            var ul = document.createElement('ul');
            articles.forEach(function (article) {
                var listItem = document.createElement('li'), link = document.createElement('a'), snippet = document.createElement('span');
                link.href = article.url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = article.title;
                snippet.className = 'snip'; snippet.textContent = article.snippet;
                listItem.appendChild(link); listItem.appendChild(snippet); ul.appendChild(listItem);
            });
            box.appendChild(heading); box.appendChild(ul); box.hidden = false;
        }
        function run() {
            var query = (titleInput.value + ' ' + descriptionInput.value).trim();
            if (query.length < 8) { box.hidden = true; return; }
            fetch(<?= json_encode(url('knowledge/suggest.php')) ?> + '?q=' + encodeURIComponent(query), {credentials: 'same-origin'})
                .then(function (response) { return response.json(); })
                .then(function (result) { render(result.items || []); })
                .catch(function () {});
        }
        [titleInput, descriptionInput].forEach(function (field) {
            field.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 600); });
        });
    })();
    </script>
    <?php endif; ?>
    <div class="row">
        <label>Metode bayar
            <select name="payment_method"><?php render_options(PAYMENT_METHODS, $ticket['payment_method'] ?? '', true); ?></select>
        </label>
        <label>ID transaksi
            <input type="text" name="transaction_id" value="<?= e($ticket['transaction_id'] ?? '') ?>" maxlength="60">
        </label>
        <label>Nominal (rupiah, angka saja)
            <input type="text" name="amount" value="<?= e((string) ($ticket['amount'] ?? '')) ?>" inputmode="numeric">
        </label>
    </div>
    <?php if ($files !== null) {
        render_attachment_field($files);
    } ?>
    <?php if ($staff): ?>
    <div class="row">
        <label>Prioritas
            <select name="priority"><?php render_options(PRIORITIES, $ticket['priority'] ?? 'sedang'); ?></select>
        </label>
        <label>Status
            <select name="status"><?php render_options(STATUSES, $ticket['status'] ?? 'baru'); ?></select>
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
    $names = $post['att_name'] ?? [];
    $datas = $post['att_data'] ?? [];
    $files = [];
    $errors = [];
    if (!is_array($names) || !is_array($datas)) {
        return [[], []];
    }
    foreach ($datas as $index => $dataUri) {
        if (count($files) >= 3) {
            $errors[] = 'Maksimal 3 lampiran.';
            break;
        }
        $dataUri = (string) $dataUri;
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $dataUri, $matches)) {
            $errors[] = 'Lampiran harus berupa gambar JPG, PNG, atau WebP.';
            continue;
        }
        if (strlen($dataUri) > 450000) {
            $errors[] = 'Salah satu lampiran terlalu besar.';
            continue;
        }
        $raw = base64_decode(substr($dataUri, strlen($matches[0])), true);
        if ($raw === false || @getimagesizefromstring($raw) === false) {
            $errors[] = 'Salah satu lampiran bukan gambar yang valid.';
            continue;
        }
        $name = mb_substr((string) preg_replace('/[^\w .()-]/u', '', (string) ($names[$index] ?? '')), 0, 60);
        $files[] = ['name' => $name !== '' ? $name : 'bukti', 'data' => $dataUri];
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
        var fileInput = document.getElementById('att-input');
        var MAX = 3, LIMIT = 420000;
        var items = <?= $json ?: '[]' ?>;
        var previewBox = document.getElementById('att-preview');
        var hiddenBox = document.getElementById('att-hidden');
        var errorBox = document.getElementById('att-error');

        function render() {
            previewBox.innerHTML = ''; hiddenBox.innerHTML = '';
            items.forEach(function (item, index) {
                var wrapper = document.createElement('div'); wrapper.className = 'att-item';
                var image = document.createElement('img'); image.src = item.data; image.alt = item.name;
                var removeButton = document.createElement('button'); removeButton.type = 'button'; removeButton.textContent = '\u00d7';
                removeButton.setAttribute('aria-label', 'Hapus lampiran');
                removeButton.onclick = function () { items.splice(index, 1); render(); };
                wrapper.appendChild(image); wrapper.appendChild(removeButton); previewBox.appendChild(wrapper);
                ['name', 'data'].forEach(function (key) {
                    var hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden'; hiddenInput.name = 'att_' + key + '[]'; hiddenInput.value = item[key];
                    hiddenBox.appendChild(hiddenInput);
                });
            });
        }

        // Kecilkan gambar ke maks. 1280 px dan kompres JPEG sampai di bawah batas ukuran
        function shrink(imageFile) {
            return new Promise(function (resolve, reject) {
                var img = new Image(), url = URL.createObjectURL(imageFile);
                img.onload = function () {
                    var scale = Math.min(1, 1280 / Math.max(img.width, img.height));
                    var width = Math.round(img.width * scale), height = Math.round(img.height * scale), quality = 0.8, dataUrl = '';
                    for (var attempt = 0; attempt < 8; attempt++) {
                        var canvas = document.createElement('canvas'); canvas.width = width; canvas.height = height;
                        var ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, width, height); ctx.drawImage(img, 0, 0, width, height);
                        dataUrl = canvas.toDataURL('image/jpeg', quality);
                        if (dataUrl.length <= LIMIT) break;
                        quality = Math.max(0.4, quality - 0.1);
                        if (quality <= 0.5) { width = Math.round(width * 0.8); height = Math.round(height * 0.8); }
                    }
                    URL.revokeObjectURL(url);
                    dataUrl.length <= LIMIT ? resolve(dataUrl) : reject();
                };
                img.onerror = function () { URL.revokeObjectURL(url); reject(); };
                img.src = url;
            });
        }

        fileInput.addEventListener('change', function () {
            errorBox.textContent = '';
            var chain = Promise.resolve();
            Array.prototype.slice.call(fileInput.files).forEach(function (file) {
                chain = chain.then(function () {
                    if (items.length >= MAX) { errorBox.textContent = 'Maksimal ' + MAX + ' gambar.'; return; }
                    if (!/^image\//.test(file.type)) { errorBox.textContent = 'Hanya file gambar (JPG, PNG, WebP).'; return; }
                    return shrink(file).then(
                        function (dataUrl) { items.push({ name: file.name, data: dataUrl }); },
                        function () { errorBox.textContent = 'Gambar "' + file.name + '" tidak bisa diproses.'; }
                    );
                });
            });
            chain.then(function () { render(); fileInput.value = ''; });
        });
        render();
    })();
    </script>
    <?php
}
