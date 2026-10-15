<?php
function render_header(string $title, string $active = '', string $subtitle = ''): void
{
    $user    = current_user();
    $flashes = take_flash();
    $GLOBALS['hm_guest'] = !$user;
    $logo = '<span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v10H9l-5 4z"/><path d="M8.5 10l2.2 2.2L15.5 8"/></svg></span>HelpMate AI';
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - HelpMate AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<?php if ($user): ?>
<div class="shell">
<aside class="sidebar">
    <a class="brand" href="<?= e(url('dashboard.php')) ?>"><?= $logo ?></a>
    <nav>
        <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>Dashboard</a>
        <a class="<?= $active === 'tickets' ? 'active' : '' ?>" href="<?= e(url('tickets/index.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 0 0 0 6v3a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-3a2 2 0 0 1 0-6V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1z"/><path d="M14 5v14" stroke-dasharray="2 3"/></svg>Tiket</a>
        <a class="<?= $active === 'knowledge' ? 'active' : '' ?>" href="<?= e(url('knowledge/index.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5M9 8h6"/></svg>Knowledge Base</a>
        <?php if (is_super_admin($user)): ?>
        <a class="<?= $active === 'users' ? 'active' : '' ?>" href="<?= e(url('admin/users.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/></svg>Pengguna</a>
        <?php endif; ?>
    </nav>
    <form class="userbox" method="post" action="<?= e(url('logout.php')) ?>">
        <?= csrf_field() ?>
        <a class="who" href="<?= e(url('profile.php')) ?>" title="Lihat profil">
                        <?php render_avatar($user); ?>
            <span class="who-text">
                <span class="who-name"><?= e($user['name']) ?></span>
                <span class="who-role"><?= e(label(ROLES, $user['role'])) ?></span>
                <span class="who-email"><?= e($user['email']) ?></span>
            </span>
        </a>
        <button type="submit" class="btn btn-small">Logout</button>
    </form>
</aside>
<div class="content">
<main class="container">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <?php if ($subtitle !== ''): ?><p class="page-sub"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
    </div>
<?php else: ?>
<div class="auth">
    <section class="auth-brand">
        <a class="brand" href="<?= e(url('login.php')) ?>"><?= $logo ?></a>
        <div>
            <h2>Kendala pembayaran, ditangani di satu tempat.</h2>
            <p>Laporkan transaksi gagal, pending, atau saldo terpotong sebagai tiket, lalu pantau statusnya sampai selesai.</p>
        </div>
        <small>Proyek ALP Cloud Computing</small>
    </section>
    <section class="auth-form">
    <main class="auth-box">
        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
        <h1><?= e($title) ?></h1>
<?php endif;
}

function render_footer(): void
{
    if (!empty($GLOBALS['hm_guest'])) {
        echo "</main>\n</section>\n</div>\n";
    } else {
        echo '</main><footer class="footer">HelpMate AI - proyek ALP Cloud Computing. Seluruh data pada sistem adalah data dummy.</footer></div></div>' . "\n";
    }
    render_ui_scripts();
    echo "</body>\n</html>\n";
}

// Script UI umum: ikon mata untuk semua kolom password, dan sembunyikan kolom institusi untuk akun perorangan
function render_ui_scripts(): void
{
    ?>
<script>
(function () {
    var eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    var off = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.6 10.6 0 0 1 12 19c-6.5 0-10-7-10-7a18 18 0 0 1 4.1-5M9.9 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-2.2 3.2M1 1l22 22"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';

    document.querySelectorAll('input[type="password"]').forEach(function (inp) {
        var wrap = document.createElement('div');
        wrap.className = 'pw-wrap';
        inp.parentNode.insertBefore(wrap, inp);
        wrap.appendChild(inp);
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'pw-toggle';
        b.setAttribute('aria-label', 'Tampilkan password');
        b.innerHTML = eye;
        b.addEventListener('click', function () {
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            b.innerHTML = show ? off : eye;
            b.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        });
        wrap.appendChild(b);
    });

    document.querySelectorAll('select[name="account_type"]').forEach(function (sel) {
        var box = sel.form && sel.form.querySelector('.org-field');
        if (!box) return;
        function sync() { box.style.display = sel.value === 'individu' ? 'none' : ''; }
        sel.addEventListener('change', sync);
        sync();
    });
})();
</script>
<?php
}

function render_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="alert alert-error"><ul>';
    foreach ($errors as $err) {
        echo '<li>' . e($err) . '</li>';
    }
    echo '</ul></div>';
}

function render_options(array $options, ?string $selected, bool $withEmpty = false): void
{
    if ($withEmpty) {
        echo '<option value="">- pilih -</option>';
    }
    foreach ($options as $value => $text) {
        $sel = ((string) $selected === (string) $value) ? ' selected' : '';
        echo '<option value="' . e((string) $value) . '"' . $sel . '>' . e($text) . '</option>';
    }
}