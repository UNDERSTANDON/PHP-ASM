<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

auth_require_login();
$user = auth_user();

layout_render_head('Dashboard — PHP-ASM LMS');
layout_nav('dashboard');
?>

    <h1>Dashboard</h1>
    <p>Signed in as <strong><?= htmlspecialchars($user['user_name']) ?></strong>
        (<span class="role role-<?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars($user['role']) ?></span>)
        — <?= htmlspecialchars($user['user_email']) ?></p>

    <div class="card dash-role-block">
        <h2 style="border:none;margin-top:0;padding:0;">Everyone</h2>
        <p class="note">Use <a href="<?= htmlspecialchars(php_asm_url('index.php')) ?>">DB tests</a> for stored procedures (use your real user / student / instructor ids where needed).</p>
    </div>

    <?php if ($user['role'] === 'student') : ?>
        <div class="card dash-role-block">
            <h2 style="border:none;margin-top:0;padding:0;">Student</h2>
            <p class="note">Enrollments, submissions, and forums use your <code>student_id</code> and <code>user_id</code> from the database.</p>
        </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'instructor') : ?>
        <div class="card dash-role-block">
            <h2 style="border:none;margin-top:0;padding:0;">Instructor</h2>
            <p class="note">Grading and course forums use your <code>instructor_id</code>. You have a row in <code>instructors</code> linked to this account.</p>
        </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'admin') : ?>
        <div class="card dash-role-block">
            <h2 style="border:none;margin-top:0;padding:0;">Admin</h2>
            <p class="note">You can use <code>sp_create_forum</code> and other tools with any permitted user id. Self-registering as admin is only for this prototype.</p>
        </div>
    <?php endif; ?>

<?php layout_render_footer();
