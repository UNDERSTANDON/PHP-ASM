<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

auth_start_session();
if (auth_user() !== null) {
    header('Location: ' . php_asm_url('index.php'), true, 302);
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $result = auth_login($email, $pass);
    if ($result === true) {
        header('Location: ' . php_asm_url('index.php'), true, 302);
        exit;
    }
    $error = $result;
}

$flash = $_GET['registered'] ?? null;

layout_render_head('Login — PHP-ASM LMS');
layout_nav('login');
?>
    <?php if ($flash !== null): ?>
        <p class="flash ok">Registration complete. Sign in below.</p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="form-errors"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <h1>Login</h1>
    <form method="post" class="card" autocomplete="on">
        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required value="<?= htmlspecialchars(trim((string) ($_POST['email'] ?? ''))) ?>">
        </div>
        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required autocomplete="current-password">
        </div>
        <p><button type="submit">Sign in</button></p>
    </form>
    <p class="note"><a href="<?= htmlspecialchars(php_asm_url('register.php')) ?>">Create an account</a></p>
<?php layout_render_footer();
