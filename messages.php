<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/lms_functions.php';
require_once __DIR__ . '/includes/course_data.php';
require_once __DIR__ . '/includes/layout.php';

auth_require_login();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function post_int(string $key, int $default = 0): int
{
    if (!isset($_POST[$key]) || $_POST[$key] === '') {
        return $default;
    }

    return (int) $_POST[$key];
}

function post_str(string $key): string
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
}

function post_opt_str(string $key): ?string
{
    $s = post_str($key);
    return $s === '' ? null : $s;
}

$user = auth_user();

$testOutput = null;
$testError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];

    if ($action === 'student_send_message') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $testError = 'Invalid or missing CSRF token.';
        }
    }

    if ($testError === null) {
        try {
            if ($action === 'student_send_message') {
                $testOutput = lms_sp_send_message(
                    $user['user_id'],
                    post_int('recipient_id'),
                    post_opt_str('subject'),
                    post_str('content') !== '' ? post_str('content') : '—'
                );
            } else {
                $testError = 'Unknown action.';
            }
        } catch (Throwable $e) {
            error_log('Exception in messages.php: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            $testError = 'An error occurred while processing your request.';
        }
    }
}

$studentMessages = lms_list_user_messages($user['user_id']);
$messageRecipients = lms_list_message_recipients($user['user_id']);

layout_render_head('Messages - PHP-ASM LMS');
layout_nav('messages');
?>
<h1>Messages</h1>

<?php if ($testError !== null): ?>
    <div class="card err"><strong>Error</strong>
        <pre><?= htmlspecialchars($testError) ?></pre>
    </div>
<?php endif; ?>

<?php if ($testOutput !== null): ?>
    <div class="card">
        <strong>Result</strong>
        <pre><?= htmlspecialchars(print_r($testOutput, true)) ?></pre>
    </div>
<?php endif; ?>

<section class="card">
    <h2 class="course-heading">Compose New Message</h2>
    <form method="post" class="submit-form" style="margin-bottom: 20px;">
        <input type="hidden" name="action" value="student_send_message">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label>To</label>
            <select name="recipient_id" required>
                <option value="">-- Select Recipient --</option>
                <?php foreach ($messageRecipients as $r): ?>
                    <option value="<?= (int) $r['user_id'] ?>">
                        <?= htmlspecialchars($r['user_name']) ?> (<?= htmlspecialchars($r['role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row" style="flex-direction:column;align-items:stretch;">
            <label>Message</label>
            <textarea name="content" rows="4" required></textarea>
        </div>
        <button type="submit">Send</button>
    </form>
</section>

<section class="card">
    <h3 class="sub-heading">Inbox / Outbox</h3>
    <?php if (empty($studentMessages)): ?>
        <p class="note">No messages.</p>
    <?php else: ?>
        <?php foreach ($studentMessages as $msg):
            $isSent = ((int) $msg['sender_id'] === $user['user_id']);
            ?>
            <div style="border-bottom: 1px solid #eee; padding: 15px 0; font-size: 1em;">
                <strong><?= $isSent ? 'To: ' . htmlspecialchars($msg['recipient_name']) : 'From: ' . htmlspecialchars($msg['sender_name']) ?></strong>
                <span class="note" style="float:right; font-size: 0.85em;"><?= htmlspecialchars($msg['created_at']) ?></span>
                <div style="margin-top: 5px; white-space: pre-wrap; color: #444;"><?= htmlspecialchars($msg['content']) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
    
<?php layout_render_footer(); ?>