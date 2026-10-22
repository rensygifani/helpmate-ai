<?php
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path): string
{
    return rtrim((string) cfg('BASE_URL', ''), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sentToken = $_POST['_csrf'] ?? '';
    if (!is_string($sentToken) || !hash_equals(csrf_token(), $sentToken)) {
        http_response_code(419);
        exit('Permintaan tidak valid (token CSRF salah). Muat ulang halaman lalu coba lagi.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    if (empty($_SESSION['user']) || current_token() === null) {
        flash('error', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }
    return $_SESSION['user'];
}

function is_staff(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && in_array($user['role'] ?? '', ['cs', 'tim_teknis', 'admin', 'super_admin'], true);
}

function is_kb_editor(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && in_array($user['role'] ?? '', ['cs', 'admin', 'super_admin'], true);
}

function require_kb_editor(): array
{
    $user = require_login();
    if (!is_kb_editor($user)) {
        http_response_code(403);
        exit('Hanya CS, Admin, atau Super Admin yang dapat mengelola Knowledge Base.');
    }
    return $user;
}

function is_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && in_array($user['role'] ?? '', ['admin', 'super_admin'], true);
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        http_response_code(403);
        exit('Halaman ini khusus Admin atau Super Admin.');
    }
    return $user;
}

function is_super_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && ($user['role'] ?? '') === 'super_admin';
}

function require_super_admin(): array
{
    $user = require_login();
    if (!is_super_admin($user)) {
        http_response_code(403);
        exit('Halaman ini khusus Super Admin.');
    }
    return $user;
}

function now_string(): string
{
    return date('Y-m-d H:i:s');
}

// ID dari Firebase hanya berisi huruf, angka, tanda minus, dan garis bawah.
// Dipakai untuk menolak ID aneh dari URL sebelum dipakai sebagai path database.
function valid_id(string $id): bool
{
    return (bool) preg_match('/^[-A-Za-z0-9_]{1,60}$/', $id);
}

function label(array $map, ?string $key): string
{
    return $map[$key ?? ''] ?? (string) $key;
}

function rupiah($amount): string
{
    if ($amount === null || $amount === '') {
        return '-';
    }
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

// Ubah hasil db_get jadi array biasa yang berisi 'id', diurutkan dari yang terbaru
function collection_to_list($data): array
{
    if (!is_array($data)) {
        return [];
    }
    $list = [];
    foreach ($data as $id => $row) {
        if (is_array($row)) {
            $row['id'] = (string) $id;
            $list[] = $row;
        }
    }
    usort($list, fn ($firstRow, $secondRow) => strcmp((string) ($secondRow['created_at'] ?? ''), (string) ($firstRow['created_at'] ?? '')));
    return $list;
}

// Validasi password dan akun (dipakai register, profil, dan admin/users)
function pw_rules(): array
{
    return [
        'len' => ['ok' => 'Minimal 8 karakter', 'bad' => 'Password kurang dari 8 karakter'],
        'upper' => ['ok' => 'Ada huruf kapital', 'bad' => 'Tidak ada huruf kapital (A-Z)'],
        'lower' => ['ok' => 'Ada huruf kecil', 'bad' => 'Tidak ada huruf kecil (a-z)'],
        'digit' => ['ok' => 'Ada angka', 'bad' => 'Tidak ada angka (0-9)'],
        'special' => ['ok' => 'Ada karakter khusus', 'bad' => 'Tidak ada karakter khusus (contoh: ! @ # $ %)'],
    ];
}

function pw_failed(string $password): array
{
    $failedRules = [];
    if (mb_strlen($password) < 8) {
        $failedRules[] = 'len';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $failedRules[] = 'upper';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $failedRules[] = 'lower';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $failedRules[] = 'digit';
    }
    if (!preg_match('/[^A-Za-z0-9\s]/', $password)) {
        $failedRules[] = 'special';
    }
    return $failedRules;
}

function field_errors(array $errors, string $key): void
{
    $msgs = array_map('e', $errors[$key] ?? []);
    echo '<div class="field-error" data-for="' . e($key) . '">' . implode('<br>', $msgs) . '</div>';
}

// Validasi jenis akun + nama institusi. Mengembalikan [data bersih, error per kolom]
function account_input(array $input): array
{
    $errors = [];
    $type = (string) ($input['account_type'] ?? '');
    $organization = trim((string) preg_replace('/\s+/', ' ', (string) ($input['organization'] ?? '')));
    if (!isset(ACCOUNT_TYPES[$type])) {
        $errors['account_type'][] = 'Pilih jenis akun.';
    } elseif ($type === 'individu') {
        $organization = '';
    } elseif ($organization === '') {
        $errors['organization'][] = 'Nama sekolah / perusahaan / institusi wajib diisi.';
    }
    if (mb_strlen($organization) > 100) {
        $errors['organization'][] = 'Nama institusi maksimal 100 karakter.';
    }
    return [['account_type' => $type, 'organization' => $organization], $errors];
}

function render_account_fields(string $type, string $organization, array $errors = []): void
{
    ?>
    <label>Jenis akun
        <select name="account_type" class="<?= !empty($errors['account_type']) ? 'invalid' : '' ?>">
            <option value="">Pilih jenis akun</option>
            <?php foreach (ACCOUNT_TYPES as $typeKey => $typeLabel): ?>
                <option value="<?= e($typeKey) ?>"<?= $typeKey === $type ? ' selected' : '' ?>><?= e($typeLabel) ?></option>
            <?php endforeach; ?>
        </select>
        <?php field_errors($errors, 'account_type'); ?>
    </label>
    <label class="org-field">Nama sekolah / perusahaan / institusi
        <input type="text" name="organization" value="<?= e($organization) ?>" maxlength="100"
               placeholder="Contoh: SMA Negeri 1 Surabaya / PT Maju Net"
               class="<?= !empty($errors['organization']) ? 'invalid' : '' ?>">
        <?php field_errors($errors, 'organization'); ?>
    </label>
    <?php
}

// Tampilkan avatar: foto profil jika ada, jika tidak huruf pertama nama
function render_avatar(array $account, string $extra = ''): void
{
    $photo = (string) ($account['photo'] ?? '');
    $cssClass = trim('avatar ' . $extra);
    if ($photo !== '' && str_starts_with($photo, 'data:image/jpeg;base64,')) {
        echo '<img class="' . e($cssClass) . '" src="' . e($photo) . '" alt="Foto profil">';
    } else {
        echo '<span class="' . e($cssClass) . '">' . e(strtoupper(mb_substr((string) ($account['name'] ?? '?'), 0, 1))) . '</span>';
    }
}

// Daftar tiket yang boleh dilihat pengguna. Petugas melihat semua tiket; merchant hanya tiket miliknya
// (difilter di sisi Firebase memakai orderBy owner_uid, sesuai Security Rules).
function tickets_for_user(array $user): array
{
    $response = is_staff($user)
        ? db_get('tickets')
        : db_get('tickets', null, ['orderBy' => 'owner_uid', 'equalTo' => $user['uid']]);
    return $response['ok'] ? collection_to_list($response['data']) : [];
}

// Semua tiket milik satu merchant (dipakai deteksi tiket serupa)
function tickets_of_owner(string $ownerUid): array
{
    $response = db_get('tickets', null, ['orderBy' => 'owner_uid', 'equalTo' => $ownerUid]);
    return $response['ok'] ? collection_to_list($response['data']) : [];
}

// Riwayat tindak lanjut satu tiket. Merchant hanya menerima catatan yang boleh dibaca merchant.
function followups_for(array $user, string $ticketId): array
{
    $response = is_staff($user)
        ? db_get('followups/' . $ticketId)
        : db_get('followups/' . $ticketId, null, ['orderBy' => 'visible_to_merchant', 'equalTo' => true]);
    return ['ok' => $response['ok'], 'error' => $response['error'], 'list' => $response['ok'] ? collection_to_list($response['data']) : []];
}
