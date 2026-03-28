<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/lms_functions.php';
require_once __DIR__ . '/includes/course_data.php';
require_once __DIR__ . '/includes/layout.php';

auth_require_login();

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
$studentId = null;
$studentRow = null;
if ($user['role'] === 'student') {
    $studentRow = lms_get_student_by_user_id($user['user_id']);
    if ($studentRow !== null) {
        $studentId = (int) $studentRow['student_id'];
    }
}

$courses = lms_list_courses_with_instructor();
$courseCount = count($courses);

$testOutput = null;
$testError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    try {
        if ($user['role'] === 'student') {
            if ($studentId === null) {
                $testError = 'No student profile is linked to your account.';
            } elseif ($action === 'student_enroll') {
                $testOutput = lms_sp_enroll_student($studentId, post_int('course_id'));
            } elseif ($action === 'student_submit') {
                $testOutput = lms_sp_upsert_submission(
                    post_int('assessment_id'),
                    $studentId,
                    post_opt_str('submission_content'),
                    post_opt_str('submission_file_path')
                );
            } elseif ($action === 'student_create_forum') {
                $testOutput = lms_sp_create_forum(
                    post_int('course_id'),
                    $user['user_id'],
                    post_str('forum_title') !== '' ? post_str('forum_title') : 'New forum',
                    post_opt_str('description')
                );
            } elseif ($action === 'student_add_post') {
                $testOutput = lms_sp_add_forum_post(
                    post_int('thread_id'),
                    $user['user_id'],
                    post_str('content')
                );
            } elseif ($action === 'student_send_message') {
                $testOutput = lms_sp_send_message(
                    $user['user_id'],
                    post_int('recipient_id'),
                    post_opt_str('subject'),
                    post_str('content') !== '' ? post_str('content') : '—'
                );
            } else {
                $testError = 'Unknown action.';
            }
        } else {
            match ($action) {
                'fn_enrollment_count' => $testOutput = [
                    'fn_enrollment_count' => lms_fn_enrollment_count(post_int('course_id')),
                ],
                'fn_student_enrolled' => $testOutput = [
                    'fn_student_enrolled' => lms_fn_student_enrolled(post_int('student_id'), post_int('course_id')),
                ],
                'fn_course_avg_score' => $testOutput = [
                    'fn_course_avg_score' => lms_fn_course_avg_score(post_int('course_id')),
                ],
                'sp_enroll_student' => $testOutput = lms_sp_enroll_student(post_int('student_id'), post_int('course_id')),
                'sp_drop_enrollment' => $testOutput = lms_sp_drop_enrollment(post_int('student_id'), post_int('course_id')),
                'sp_record_material_access' => $testOutput = lms_sp_record_material_access(
                    post_int('material_id'),
                    post_int('student_id')
                ),
                'sp_upsert_submission' => $testOutput = lms_sp_upsert_submission(
                    post_int('assessment_id'),
                    post_int('student_id'),
                    post_opt_str('submission_content'),
                    post_opt_str('submission_file_path')
                ),
                'sp_grade_submission' => $testOutput = lms_sp_grade_submission(
                    post_int('submission_id'),
                    post_int('instructor_id'),
                    post_int('score'),
                    post_opt_str('feedback')
                ),
                'sp_refresh_course_analytics' => $testOutput = lms_sp_refresh_course_analytics(post_int('course_id')),
                'sp_refresh_student_progress' => $testOutput = lms_sp_refresh_student_progress(
                    post_int('student_id'),
                    post_int('course_id')
                ),
                'sp_create_forum' => $testOutput = lms_sp_create_forum(
                    post_int('course_id'),
                    post_int('user_id'),
                    post_str('forum_title') !== '' ? post_str('forum_title') : 'New forum',
                    post_opt_str('forum_description')
                ),
                'sp_create_forum_thread' => $testOutput = lms_sp_create_forum_thread(
                    post_int('forum_id'),
                    post_int('user_id'),
                    post_str('thread_title') !== '' ? post_str('thread_title') : '(no title)'
                ),
                'sp_send_message' => $testOutput = lms_sp_send_message(
                    post_int('sender_id'),
                    post_int('recipient_id'),
                    post_opt_str('msg_subject'),
                    post_str('msg_body') !== '' ? post_str('msg_body') : '—'
                ),
                default => $testError = 'Unknown action.',
            };
        }
    } catch (Throwable $e) {
        $testError = $e->getMessage();
    }
}

/** @var array<int, array{course: array, enrollment: ?array, materials: list, assessments: list, forums: list}> */
$courseDetails = [];
if ($user['role'] === 'student' && $studentId !== null) {

    foreach ($courses as $c) {
        $cid = (int) $c['course_id'];
        $en = lms_get_enrollment($studentId, $cid);
        $detail = [
            'course'      => $c,
            'enrollment'  => $en,
            'materials'   => [],
            'assessments' => [],
            'forums'      => [],
        ];
        if (lms_student_may_access_course_content($en)) {
            $detail['materials'] = lms_list_materials_for_course($cid);
            $assessList = lms_list_assessments_for_course($cid);
            foreach ($assessList as $a) {
                $a['submission'] = lms_get_student_submission((int) $a['assessment_id'], $studentId);
                $detail['assessments'][] = $a;
            }
            $forums = lms_list_course_forums($cid);
            foreach ($forums as &$f) {
                $f['posts'] = lms_list_forum_posts((int) $f['thread_id']);
            }
            $detail['forums'] = $forums;
        }
        $courseDetails[$cid] = $detail;
    }
}

function enrollment_status_label(?array $enrollment): string
{
    if ($enrollment === null) {
        return 'Not enrolled';
    }
    return 'Enrollment: ' . $enrollment['status'];
}

function student_can_use_enroll_button(?array $enrollment): bool
{
    if ($enrollment === null) {
        return true;
    }
    $s = $enrollment['status'];
    return $s !== 'enrolled' && $s !== 'completed';
}

layout_render_head('PHP-ASM LMS');
layout_nav('home');
?>
    <h1>Home</h1>

    <?php if ($testError !== null) : ?>
        <div class="card err"><strong>Error</strong>
            <pre><?= htmlspecialchars($testError) ?></pre>
        </div>
    <?php endif; ?>

    <?php if ($testOutput !== null) : ?>
        <div class="card">
            <strong>Last result</strong>
            <pre><?= htmlspecialchars(print_r($testOutput, true)) ?></pre>
        </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'student') : ?>
        <?php if ($studentId === null) : ?>
            <div class="card err">No <code>students</code> row for your user. Contact an administrator.</div>
        <?php else : ?>
            <p class="note">There <?= $courseCount === 1 ? 'is' : 'are' ?> <strong><?= (int) $courseCount ?></strong> course<?= $courseCount === 1 ? '' : 's' ?> available.</p>

                    <?php foreach ($courses as $c) :
                $cid = (int) $c['course_id'];
                $d = $courseDetails[$cid] ?? null;
                $en = $d['enrollment'] ?? null;
                ?>
                <section class="card course-card">
                    <h2 class="course-heading"><?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_name']) ?></h2>
                    <p class="note"><?= htmlspecialchars((string) ($c['description'] ?? '')) ?></p>
                    <p>Credits: <?= (int) $c['credit'] ?> · Capacity: <?= (int) $c['capacity'] ?> · Instructor: <?= htmlspecialchars((string) $c['instructor_name']) ?></p>
                    <p class="enrollment-label"><strong><?= htmlspecialchars(enrollment_status_label($en)) ?></strong></p>

                    <?php if (student_can_use_enroll_button($en)) : ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="action" value="student_enroll">
                            <input type="hidden" name="course_id" value="<?= $cid ?>">
                            <button type="submit">Enroll in this course</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($en !== null && lms_student_may_access_course_content($en)) : ?>
                        <h3 class="sub-heading">Materials</h3>
                        <?php if ($d['materials'] === []) : ?>
                            <p class="note">No materials yet.</p>
                        <?php else : ?>
                            <ul class="material-list">
                                <?php foreach ($d['materials'] as $mat) : ?>
                                    <li>
                                        <strong><?= htmlspecialchars((string) $mat['material_title']) ?></strong>
                                        (Module <?= (int) $mat['module_number'] ?>: <?= htmlspecialchars((string) $mat['module_title']) ?>)
                                        <?php if (!empty($mat['file_path'])) : ?>
                                            — <a href="<?= htmlspecialchars((string) $mat['file_path']) ?>" target="_blank" rel="noopener">Link / file</a>
                                        <?php endif; ?>
                                        <?php if (!empty($mat['description'])) : ?>
                                            <br><span class="note"><?= htmlspecialchars((string) $mat['description']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <h3 class="sub-heading">Assessments — submit only</h3>
                        <?php if ($d['assessments'] === []) : ?>
                            <p class="note">No assessments yet.</p>
                        <?php else : ?>
                            <?php foreach ($d['assessments'] as $as) :
                                $sub = $as['submission'] ?? null;
                                ?>
                                <div class="card assessment-block">
                                    <p><strong><?= htmlspecialchars((string) $as['title']) ?></strong>
                                        <span class="note">(<?= htmlspecialchars((string) $as['assessment_type']) ?>, <?= (int) $as['total_points'] ?> pts)</span></p>
                                    <?php if (!empty($as['description'])) : ?>
                                        <p class="note"><?= nl2br(htmlspecialchars((string) $as['description'])) ?></p>
                                    <?php endif; ?>
                                    <p class="note">Due: <?= $as['due_date'] !== null && $as['due_date'] !== ''
                                        ? htmlspecialchars((string) $as['due_date'])
                                        : '—' ?></p>
                                    <p><em>Your submission:</em>
                                        <?php if ($sub === null) : ?>
                                            <span class="note">None yet.</span>
                                        <?php else : ?>
                                            <span class="note"><?= htmlspecialchars($sub['status']) ?>
                                                <?php if ($sub['score'] !== null && $sub['score'] !== '') : ?>
                                                    — score <?= htmlspecialchars((string) $sub['score']) ?>
                                                <?php endif; ?>
                                                (<?= htmlspecialchars((string) $sub['submitted_at']) ?>)</span>
                                        <?php endif; ?>
                                    </p>
                                    <form method="post" class="submit-form">
                                        <input type="hidden" name="action" value="student_submit">
                                        <input type="hidden" name="assessment_id" value="<?= (int) $as['assessment_id'] ?>">
                                        <div class="row" style="flex-direction:column;align-items:stretch;">
                                            <label>Answer / notes</label>
                                            <textarea name="submission_content" rows="3" placeholder="Your work"></textarea>
                                        </div>
                                        <div class="row" style="flex-direction:column;align-items:stretch;">
                                            <label>File URL (optional)</label>
                                            <input type="text" name="submission_file_path" placeholder="https://…">
                                        </div>
                                        <button type="submit">Submit / update submission</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <br>
                        <h3 class="sub-heading">Forum</h3>
                        <details>
                            <summary style="cursor: pointer; padding: 4px 10px; background: #eee; border: 1px solid #ccc; display: inline-block; font-size: 0.9em; border-radius: 4px;">Show more</summary>
                            <div style="margin-top: 15px;">
                                <?php if (empty($d['forums'])) : ?>
                                    <p class="note">No forums for this course yet.</p>
                                <?php else : ?>
                                    <?php foreach ($d['forums'] as $forum) : ?>
                                        <div class="card assessment-block" style="background: #fafafa; margin-bottom: 10px;">
                                            <p><strong><?= htmlspecialchars((string) $forum['forum_title']) ?></strong></p>
                                            
                                            <div style="margin: 10px 0; padding-left: 10px; border-left: 3px solid #ddd;">
                                                <?php foreach ($forum['posts'] as $post) : ?>
                                                    <div style="margin-bottom: 8px;">
                                                        <strong><?= htmlspecialchars($post['user_name']) ?></strong> <span style="font-size:0.8em; color:#888;">(<?= htmlspecialchars($post['role']) ?>, <?= htmlspecialchars($post['created_at']) ?>)</span><br>
                                                        <span style="color: #333;"><?= nl2br(htmlspecialchars($post['content'])) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <form method="post" class="inline-form" style="margin-top: 10px;">
                                                <input type="hidden" name="action" value="student_add_post">
                                                <input type="hidden" name="thread_id" value="<?= (int) $forum['thread_id'] ?>">
                                                <input type="text" name="content" required placeholder="Type a message..." style="width: 60%; padding: 4px;">
                                                <button type="submit" style="padding: 4px 8px;">Reply</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="student_create_forum">
                                        <input type="hidden" name="course_id" value="<?= $cid ?>">
                                        <input type="text" name="forum_title" required placeholder="New forum title..." style="width: 50%; padding: 4px;">
                                        <button type="submit" style="padding: 4px 8px;">Create Forum</button>
                                    </form>
                                </div>
                            </div>
                        </details>

                    <?php elseif ($en !== null && ! lms_student_may_access_course_content($en)) : ?>
                        <p class="note">Materials and assessments appear after you are actively enrolled (status <code>enrolled</code> or <code>completed</code>).</p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else : ?>
        <p class="note">Staff / instructor view: stored procedure test forms.</p>
        <div class="card">
            <strong>PDO</strong>
            <?php
            try {
                get_pdo();
                echo '<p class="ok">Connected.</p>';
            } catch (Throwable $e) {
                echo '<p class="err">' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>

        <h2>Scalar functions</h2>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="fn_enrollment_count">
                <div class="row"><label>course_id</label><input type="number" name="course_id" value="1" min="0"></div>
                <button type="submit">fn_enrollment_count</button>
            </form>
        </div>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="fn_student_enrolled">
                <div class="row"><label>student_id</label><input type="number" name="student_id" value="1" min="0"></div>
                <div class="row"><label>course_id</label><input type="number" name="course_id" value="1" min="0"></div>
                <button type="submit">fn_student_enrolled</button>
            </form>
        </div>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="fn_course_avg_score">
                <div class="row"><label>course_id</label><input type="number" name="course_id" value="1" min="0"></div>
                <button type="submit">fn_course_avg_score</button>
            </form>
        </div>

        <h2>Enrollment &amp; refresh</h2>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_enroll_student">
                <div class="row"><label>student_id</label><input type="number" name="student_id" value="1" min="0"></div>
                <div class="row"><label>course_id</label><input type="number" name="course_id" value="1" min="0"></div>
                <button type="submit">sp_enroll_student</button>
            </form>
        </div>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_drop_enrollment">
                <div class="row"><label>student_id</label><input type="number" name="student_id" value="1" min="0"></div>
                <div class="row"><label>course_id</label><input type="number" name="course_id" value="1" min="0"></div>
                <button type="submit">sp_drop_enrollment</button>
            </form>
        </div>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_refresh_course_analytics">
                <div class="row"><label>course_id</label><input type="number" name="course_id" value="1" min="0"></div>
                <button type="submit">sp_refresh_course_analytics</button>
            </form>
        </div>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_refresh_student_progress">
                <div class="row"><label>student_id</label><input type="number" name="student_id" value="1" min="0"></div>
                <div class="row"><label>course_id</label><input type="number" name="course_id" value="1" min="0"></div>
                <button type="submit">sp_refresh_student_progress</button>
            </form>
        </div>

        <h2>Materials</h2>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_record_material_access">
                <div class="row"><label>material_id</label><input type="number" name="material_id" value="1" min="0"></div>
                <div class="row"><label>student_id</label><input type="number" name="student_id" value="1" min="0"></div>
                <button type="submit">sp_record_material_access</button>
            </form>
        </div>

        <h2>Submissions &amp; grading</h2>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_upsert_submission">
                <div class="row"><label>assessment_id</label><input type="number" name="assessment_id" value="1" min="0"></div>
                <div class="row"><label>student_id</label><input type="number" name="student_id" value="1" min="0"></div>
                <div class="row" style="flex-direction:column;align-items:stretch;">
                    <label>content (optional)</label>
                    <textarea name="submission_content" placeholder="plain text"></textarea>
                </div>
                <div class="row"><label>file_path</label><input class="w-wide" type="text" name="submission_file_path" placeholder="/uploads/hw1.pdf"></div>
                <button type="submit">sp_upsert_submission</button>
            </form>
        </div>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_grade_submission">
                <div class="row"><label>submission_id</label><input type="number" name="submission_id" value="1" min="0"></div>
                <div class="row"><label>instructor_id</label><input type="number" name="instructor_id" value="1" min="0"></div>
                <div class="row"><label>score</label><input type="number" name="score" value="85" min="0"></div>
                <div class="row" style="flex-direction:column;align-items:stretch;">
                    <label>feedback</label>
                    <textarea name="feedback" placeholder="optional"></textarea>
                </div>
                <button type="submit">sp_grade_submission</button>
            </form>
        </div>

        <h2>Forum</h2>
        <p class="note"><code>sp_create_forum</code> adds a new <strong>forum</strong> row (new <code>forum_id</code>).
            <code>sp_create_forum_thread</code> adds a <strong>thread</strong> inside an <em>existing</em> forum.</p>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_create_forum">
                <div class="row"><label>course_id</label><input type="number" name="course_id" value="1" min="0"></div>
                <div class="row"><label>user_id</label><input type="number" name="user_id" value="1" min="0"></div>
                <div class="row"><label>forum_title</label><input class="w-wide" type="text" name="forum_title" value="General discussion"></div>
                <div class="row" style="flex-direction:column;align-items:stretch;">
                    <label>description (optional)</label>
                    <textarea name="forum_description" placeholder="Course Q&amp;A"></textarea>
                </div>
                <button type="submit">sp_create_forum</button>
            </form>
        </div>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_create_forum_thread">
                <div class="row"><label>forum_id</label><input type="number" name="forum_id" value="1" min="0"></div>
                <div class="row"><label>user_id</label><input type="number" name="user_id" value="1" min="0"></div>
                <div class="row"><label>title</label><input class="w-wide" type="text" name="thread_title" value="Week 3 question"></div>
                <button type="submit">sp_create_forum_thread</button>
            </form>
        </div>

        <h2>Messages</h2>
        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="sp_send_message">
                <div class="row"><label>sender_id</label><input type="number" name="sender_id" value="1" min="0"></div>
                <div class="row"><label>recipient_id</label><input type="number" name="recipient_id" value="2" min="0"></div>
                <div class="row"><label>subject</label><input class="w-wide" type="text" name="msg_subject" placeholder="optional"></div>
                <div class="row" style="flex-direction:column;align-items:stretch;">
                    <label>body</label>
                    <textarea name="msg_body">Hello from prototype.</textarea>
                </div>
                <button type="submit">sp_send_message</button>
            </form>
        </div>
    <?php endif; ?>
<?php layout_render_footer();
