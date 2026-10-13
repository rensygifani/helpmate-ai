<?php
function render_header(string $title, string $active = ''): void
{
    $user    = current_user();
    $flashes = take_flash();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - HelpMate AI</title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(url('dashboard.php')) ?>">HelpMate AI</a>
    <?php if ($user): ?>
    <nav>
        <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard.php')) ?>">Dashboard</a>
        <a class="<?= $active === 'tickets' ? 'active' : '' ?>" href="<?= e(url('tickets/index.php')) ?>">Tiket</a>
        <a class="<?= $active === 'knowledge' ? 'active' : '' ?>" href="<?= e(url('knowledge/index.php')) ?>">Knowledge Base</a>
    </nav>
    <form class="userbox" method="post" action="<?= e(url('logout.php')) ?>">
        <?= csrf_field() ?>
        <span><?= e($user['name']) ?> (<?= e(label(ROLES, $user['role'])) ?>)</span>
        <button type="submit" class="btn btn-small btn-light">Logout</button>
    </form>
    <?php endif; ?>
</header>
<main class="container">
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <h1><?= e($title) ?></h1>
<?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="footer">HelpMate AI - proyek ALP Cloud Computing. Seluruh data pada sistem adalah data dummy.</footer>
</body>
</html>
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
