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
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
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
    return $user !== null && in_array($user['role'] ?? '', ['cs', 'tim_teknis', 'admin'], true);
}

function is_kb_editor(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && in_array($user['role'] ?? '', ['cs', 'admin'], true);
}

function require_kb_editor(): array
{
    $user = require_login();
    if (!is_kb_editor($user)) {
        http_response_code(403);
        exit('Hanya CS atau Admin yang dapat mengelola Knowledge Base.');
    }
    return $user;
}

function now_string(): string
{
    return date('Y-m-d H:i:s');
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
    usort($list, fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    return $list;
}
