<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function layout_render_head(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(php_asm_url('assets/app.css')) ?>">
</head>
<body>
<?php
}

function layout_render_footer(): void
{
    ?>
</body>
</html>
<?php
}

function layout_nav(string $current = ''): void
{
    auth_start_session();
    $user = auth_user();
    ?>
    <nav class="nav">
        <a href="<?= htmlspecialchars(php_asm_url('index.php')) ?>" class="brand">PHP-ASM LMS</a>
        <span class="nav-links">
            <a href="<?= htmlspecialchars(php_asm_url('index.php')) ?>"<?= $current === 'home' ? ' class="active"' : '' ?>>Home</a>
            <a href="<?= htmlspecialchars(php_asm_url('dashboard.php')) ?>"<?= $current === 'dashboard' ? ' class="active"' : '' ?>>Dashboard</a>
            <?php if ($user === null) : ?>
                <a href="<?= htmlspecialchars(php_asm_url('login.php')) ?>"<?= $current === 'login' ? ' class="active"' : '' ?>>Login</a>
                <a href="<?= htmlspecialchars(php_asm_url('register.php')) ?>"<?= $current === 'register' ? ' class="active"' : '' ?>>Register</a>
            <?php else : ?>
                <a href="<?= htmlspecialchars(php_asm_url('messages.php')) ?>"<?= $current === 'messages' ? ' class="active"' : '' ?>>Messages</a>
                <span class="nav-user">
                    <span class="role role-<?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars($user['role']) ?></span>
                    <?= htmlspecialchars($user['user_name']) ?>
                </span>
                <a href="<?= htmlspecialchars(php_asm_url('logout.php')) ?>">Logout</a>
            <?php endif; ?>
        </span>
    </nav>
<?php
}
