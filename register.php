<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

auth_start_session();
if (auth_user() !== null) {
    header('Location: ' . php_asm_url('index.php'), true, 302);
    exit;
}

$errors = [];
$form = [
    'user_name'          => trim((string) ($_POST['user_name'] ?? '')),
    'email'              => trim((string) ($_POST['email'] ?? '')),
    'role'               => (string) ($_POST['role'] ?? 'student'),
    'enrollment_number'  => trim((string) ($_POST['enrollment_number'] ?? '')),
    'major'              => trim((string) ($_POST['major'] ?? '')),
    'department'         => trim((string) ($_POST['department'] ?? '')),
    'bio'                => trim((string) ($_POST['bio'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password_confirm'] ?? '');
    $errors = auth_validate_register_inputs(
        $form['user_name'],
        $form['email'],
        $pass,
        $pass2,
        $form['role'],
        $form['enrollment_number']
    );
    if ($errors === []) {
        $result = auth_register(
            $form['user_name'],
            $form['email'],
            $pass,
            $form['role'],
            $form['role'] === 'student' ? $form['enrollment_number'] : null,
            $form['role'] === 'student' ? ($form['major'] !== '' ? $form['major'] : null) : null,
            $form['role'] === 'instructor' ? ($form['department'] !== '' ? $form['department'] : null) : null,
            $form['role'] === 'instructor' ? ($form['bio'] !== '' ? $form['bio'] : null) : null
        );
        if ($result['ok'] === true) {
            header('Location: ' . php_asm_url('login.php?registered=1'), true, 302);
            exit;
        }
        $errors = $result['errors'];
    }
}

layout_render_head('Register — PHP-ASM LMS');
layout_nav('register');
?>

    <h1>Register</h1>
    <p class="note">Passwords are stored with <code>password_hash()</code> (bcrypt). Minimum <?= AUTH_MIN_PASSWORD_LEN ?> characters.</p>

    <?php if ($errors !== []) : ?>
        <ul class="form-errors">
            <?php foreach ($errors as $err) : ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="card" id="reg-form">
        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label for="user_name">Display name</label>
            <input type="text" name="user_name" id="user_name" required value="<?= htmlspecialchars($form['user_name']) ?>">
        </div>
        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required value="<?= htmlspecialchars($form['email']) ?>">
        </div>
        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label for="role">Role</label>
            <select name="role" id="role">
                <option value="student"<?= $form['role'] === 'student' ? ' selected' : '' ?>>Student</option>
                <option value="instructor"<?= $form['role'] === 'instructor' ? ' selected' : '' ?>>Instructor</option>
                <option value="admin"<?= $form['role'] === 'admin' ? ' selected' : '' ?>>Admin (prototype)</option>
            </select>
        </div>

        <div class="row student-only" style="flex-direction:column;align-items:stretch;">
            <label for="enrollment_number">Enrollment number</label>
            <input type="text" name="enrollment_number" id="enrollment_number" value="<?= htmlspecialchars($form['enrollment_number']) ?>">
        </div>
        <div class="row student-only" style="flex-direction:column;align-items:stretch;">
            <label for="major">Major (optional)</label>
            <input type="text" name="major" id="major" value="<?= htmlspecialchars($form['major']) ?>">
        </div>

        <div class="row instructor-only" style="flex-direction:column;align-items:stretch;display:none;">
            <label for="department">Department (optional)</label>
            <input type="text" name="department" id="department" value="<?= htmlspecialchars($form['department']) ?>">
        </div>
        <div class="row instructor-only" style="flex-direction:column;align-items:stretch;display:none;">
            <label for="bio">Bio (optional)</label>
            <textarea name="bio" id="bio"><?= htmlspecialchars($form['bio']) ?></textarea>
        </div>

        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required autocomplete="new-password" minlength="<?= AUTH_MIN_PASSWORD_LEN ?>">
        </div>
        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label for="password_confirm">Confirm password</label>
            <input type="password" name="password_confirm" id="password_confirm" required autocomplete="new-password" minlength="<?= AUTH_MIN_PASSWORD_LEN ?>">
        </div>
        <p><button type="submit">Create account</button></p>
    </form>
    <p class="note"><a href="<?= htmlspecialchars(php_asm_url('login.php')) ?>">Already have an account</a></p>
    <script>
        (function () {
            var role = document.getElementById('role');
            function sync() {
                var v = role.value;
                document.querySelectorAll('.student-only').forEach(function (el) {
                    el.style.display = v === 'student' ? '' : 'none';
                });
                document.querySelectorAll('.instructor-only').forEach(function (el) {
                    el.style.display = v === 'instructor' ? '' : 'none';
                });
            }
            role.addEventListener('change', sync);
            sync();
        })();
    </script>
<?php layout_render_footer();
