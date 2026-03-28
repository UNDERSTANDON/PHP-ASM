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
$studentId = null;
$studentRow = null;
if ($user['role'] === 'student') {
    $studentRow = lms_get_student_by_user_id($user['user_id']);
    if ($studentRow !== null) {
        $studentId = (int) $studentRow['student_id'];
    }
}

$instructorId = null;
if ($user['role'] === 'instructor') {
    $insRow = lms_get_instructor_by_user_id($user['user_id']);
    if ($insRow !== null) {
        $instructorId = (int) $insRow['instructor_id'];
    }
}

$courses = lms_list_courses_with_instructor();
$courseCount = count($courses);

$testOutput = null;
$testError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];

    if (str_starts_with($action, 'student_') || str_starts_with($action, 'instructor_')) {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            error_log("CSRF validation failed for action: $action");
            http_response_code(403);
            die('Invalid or missing CSRF token.');
        }
    }

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
                $courseId = post_int('course_id');
                $enrollment = lms_get_enrollment($studentId, $courseId);
                if (!lms_student_may_access_course_content($enrollment)) {
                    $testError = 'You do not have permission to access this course.';
                } else {
                    $testOutput = lms_sp_create_forum(
                        $courseId,
                        $user['user_id'],
                        post_str('forum_title') !== '' ? post_str('forum_title') : 'New forum',
                        post_opt_str('description')
                    );
                }
            } elseif ($action === 'student_add_post') {
                $threadId = post_int('thread_id');
                $thread = lms_get_thread($threadId);
                if ($thread === null) {
                    $testError = 'Thread not found.';
                } else {
                    $enrollment = lms_get_enrollment($studentId, (int) $thread['course_id']);
                    if (!lms_student_may_access_course_content($enrollment)) {
                        $testError = 'You do not have permission to post in this thread.';
                    } else {
                        $testOutput = lms_sp_add_forum_post(
                            $threadId,
                            $user['user_id'],
                            post_str('content')
                        );
                    }
                }
            } elseif ($action === 'student_send_message') {
                $recipientId = post_int('recipient_id');
                $recipient = lms_get_user($recipientId);
                if ($recipient === null || !in_array($recipient['role'], ['student', 'instructor'], true)) {
                    $testError = 'Invalid recipient.';
                } else {
                    $testOutput = lms_sp_send_message(
                        $user['user_id'],
                        $recipientId,
                        post_opt_str('subject'),
                        post_str('content') !== '' ? post_str('content') : '—'
                    );
                }
            } else {
                $testError = 'Unknown action.';
            }
        } elseif ($user['role'] === 'instructor') {
            if ($instructorId === null) {
                $testError = 'No instructor profile attached.';
            } elseif ($action === 'instructor_create_course') {
                $testOutput = lms_sp_create_course(post_str('course_code'), post_str('course_name'), post_opt_str('description'), post_int('credit'), $instructorId, post_int('capacity'));
            } elseif ($action === 'instructor_create_assessment') {
                $testOutput = lms_sp_create_assessment(post_int('course_id'), $instructorId, post_str('assessment_type'), post_str('title'), post_opt_str('description'), post_int('total_points'), post_opt_str('due_date'));
            } elseif ($action === 'instructor_create_module') {
                $testOutput = lms_sp_create_module(post_int('course_id'), $instructorId, post_str('module_title'), post_opt_str('description'));
            } elseif ($action === 'instructor_create_material') {
                $testOutput = lms_sp_create_material(post_int('module_id'), $instructorId, post_str('material_title'), post_opt_str('description'), post_opt_str('file_name'), null, null);
            } elseif ($action === 'instructor_grade_submission') {
                $testOutput = lms_sp_grade_submission(post_int('submission_id'), $instructorId, post_int('score'), post_opt_str('feedback'));
            } elseif ($action === 'student_create_forum') {
                $isOwner = false;
                foreach(lms_list_instructor_courses($instructorId) as $c) if ((int)$c['course_id'] === post_int('course_id')) $isOwner = true;
                if (!$isOwner) $testError = 'You do not own this course.';
                else $testOutput = lms_sp_create_forum(post_int('course_id'), $user['user_id'], post_str('forum_title') !== '' ? post_str('forum_title') : 'New forum', post_opt_str('description'));
            } elseif ($action === 'student_add_post') {
                $threadId = post_int('thread_id');
                $thread = lms_get_thread($threadId);
                if ($thread === null) {
                    $testError = 'Thread not found.';
                } else {
                    $isOwner = false;
                    foreach(lms_list_instructor_courses($instructorId) as $c) if ((int)$c['course_id'] === (int)$thread['course_id']) $isOwner = true;
                    if (!$isOwner) $testError = 'You do not own the course for this thread.';
                    else $testOutput = lms_sp_add_forum_post($threadId, $user['user_id'], post_str('content'));
                }
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
$instructorCoursesDetails = [];

if ($user['role'] === 'instructor' && $instructorId !== null) {
    $myCourses = lms_list_instructor_courses($instructorId);
    foreach ($myCourses as $c) {
        $cid = (int) $c['course_id'];
        $detail = ['course' => $c, 'materials' => lms_list_materials_for_course($cid), 'assessments' => [], 'forums' => []];
        $assessList = lms_list_assessments_for_course($cid);
        foreach ($assessList as $a) {
            $a['submissions'] = lms_list_assessment_submissions((int) $a['assessment_id']);
            $detail['assessments'][] = $a;
        }
        $forums = lms_list_course_forums($cid);
        foreach ($forums as &$f) {
            $f['posts'] = lms_list_forum_posts((int) $f['thread_id']);
        }
        $detail['forums'] = $forums;
        $instructorCoursesDetails[$cid] = $detail;
    }
}

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
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                                <?php foreach ($d['materials'] as $mat) : if (empty($mat['material_id'])) continue; ?>
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
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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

    <?php elseif ($user['role'] === 'instructor') : ?>
        <?php if ($instructorId === null) : ?>
            <div class="card err">No <code>instructors</code> row for your user. Contact an administrator.</div>
        <?php else : ?>
            <h2>Instructor Dashboard</h2>
            <div class="card">
                <h3 class="course-heading">Create a New Course</h3>
                <form method="post" class="submit-form">
                    <input type="hidden" name="action" value="instructor_create_course">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="row">
                        <label>Course Code</label> <input type="text" name="course_code" required placeholder="e.g. CS102">
                    </div>
                    <div class="row">
                        <label>Course Name</label> <input type="text" name="course_name" required placeholder="e.g. Intro to Databases">
                    </div>
                    <div class="row">
                        <label>Capacity</label> <input type="number" name="capacity" required min="1" value="30">
                    </div>
                    <div class="row">
                        <label>Credits</label> <input type="number" name="credit" required min="1" value="3">
                    </div>
                    <div class="row" style="flex-direction:column;align-items:stretch;">
                        <label>Description</label>
                        <textarea name="description" rows="2"></textarea>
                    </div>
                    <button type="submit">Create Course</button>
                </form>
            </div>

            <p class="note">You manage <strong><?= count($instructorCoursesDetails) ?></strong> course(s).</p>
            
            <?php foreach ($instructorCoursesDetails as $cid => $d) : $c = $d['course']; ?>
                <section class="card course-card">
                    <h2 class="course-heading"><?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_name']) ?></h2>
                    <p class="note"><?= htmlspecialchars((string) ($c['description'] ?? '')) ?></p>
                    <p>Credits: <?= (int) $c['credit'] ?> · Capacity: <?= (int) $c['capacity'] ?></p>
                    
                    <h3 class="sub-heading">Materials</h3>
                    <?php 
                    $mods = [];
                    if (!empty($d['materials'])) {
                        foreach ($d['materials'] as $m) $mods[$m['module_id']][] = $m;
                    }
                    ?>
                    <details style="margin-bottom: 15px;">
                        <summary style="cursor: pointer; padding: 4px 10px; background: #eee; border: 1px solid #ccc; display: inline-block; font-size: 0.9em; border-radius: 4px;">Add Module / Material</summary>
                        <div style="margin-top: 15px; border-left: 2px solid #ccc; padding-left: 10px;">
                            <p><strong>Add New Module</strong></p>
                            <form method="post" class="submit-form">
                                <input type="hidden" name="action" value="instructor_create_module">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="course_id" value="<?= $cid ?>">
                                <div class="row">
                                    <label style="min-width:auto;">Title</label> <input type="text" name="module_title" required>
                                </div>
                                <div class="row" style="flex-direction:column;align-items:stretch;">
                                    <label>Description</label> <textarea name="description" rows="1"></textarea>
                                </div>
                                <button type="submit">Create Module</button>
                            </form>
                            
                            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
                            
                            <p><strong>Add Material to Module</strong></p>
                            <?php if (empty($mods)) : ?>
                                <p class="note">Create a module first to add materials.</p>
                            <?php else : ?>
                            <form method="post" class="submit-form">
                                <input type="hidden" name="action" value="instructor_create_material">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <div class="row">
                                    <label>Module</label>
                                    <select name="module_id" required>
                                        <option value="">-- Select Module --</option>
                                        <?php foreach ($mods as $mid => $matList) : $m1 = $matList[0]; ?>
                                            <option value="<?= $mid ?>">Module <?= (int)$m1['module_number'] ?>: <?= htmlspecialchars((string)$m1['module_title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row"><label>Title</label> <input type="text" name="material_title" required></div>
                                <div class="row"><label>File Name</label> <input type="text" name="file_name" placeholder="e.g. week1.pdf"></div>
                                <button type="submit">Add Material</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </details>

                    <?php if (empty($mods)) : ?>
                        <p class="note">No materials mapped.</p>
                    <?php else : ?>
                        <?php
                        foreach ($mods as $mid => $matList) :
                            $m1 = $matList[0];
                        ?>
                            <div class="card assessment-block" style="background:#fdfdfd;">
                                <p><strong>Module <?= (int)$m1['module_number'] ?>: <?= htmlspecialchars((string)$m1['module_title']) ?></strong> <span class="note">(ID: <?= $mid ?>)</span></p>
                                <p class="note"><?= htmlspecialchars((string)($m1['module_desc'] ?? '')) ?></p>
                                <ul class="material-list">
                                <?php foreach ($matList as $mat) : if (!$mat['material_id']) continue; ?>
                                    <li>
                                        <strong><?= htmlspecialchars((string)$mat['material_title']) ?></strong>
                                        <?php if (!empty($mat['file_name'])) : ?>
                                            <span class="note">— <a href="#"><?= htmlspecialchars((string)$mat['file_name']) ?></a></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <h3 class="sub-heading">Assessments</h3>
                    <details style="margin-bottom: 15px;">
                        <summary style="cursor: pointer; padding: 4px 10px; background: #eee; border: 1px solid #ccc; display: inline-block; font-size: 0.9em; border-radius: 4px;">Add Assessment</summary>
                        <div style="margin-top: 15px; border-left: 2px solid #ccc; padding-left: 10px;">
                            <form method="post" class="submit-form">
                                <input type="hidden" name="action" value="instructor_create_assessment">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="course_id" value="<?= $cid ?>">
                                <div class="row"><label>Type</label>
                                    <select name="assessment_type" required>
                                        <option value="assignment">Assignment</option>
                                        <option value="quiz">Quiz</option>
                                        <option value="exam">Exam</option>
                                    </select>
                                </div>
                                <div class="row"><label>Title</label> <input type="text" name="title" required></div>
                                <div class="row"><label>Total Points</label> <input type="number" name="total_points" required min="1" value="100"></div>
                                <div class="row"><label>Due Date (optional)</label> <input type="datetime-local" name="due_date"></div>
                                <div class="row" style="flex-direction:column;align-items:stretch;">
                                    <label>Description (optional)</label> <textarea name="description" rows="2"></textarea>
                                </div>
                                <button type="submit">Add Assessment</button>
                            </form>
                        </div>
                    </details>
                    
                    <?php if (empty($d['assessments'])) : ?>
                        <p class="note">No assessments mapped.</p>
                    <?php else : ?>
                        <?php foreach ($d['assessments'] as $as) : ?>
                            <div class="card assessment-block" style="background:#fafafa;">
                                <p><strong><?= htmlspecialchars((string) $as['title']) ?></strong>
                                    <span class="note">(<?= htmlspecialchars((string) $as['assessment_type']) ?>, <?= (int) $as['total_points'] ?> pts)</span></p>
                                
                                <?php if (empty($as['submissions'])) : ?>
                                    <p class="note">No submissions yet.</p>
                                <?php else : ?>
                                    <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($as['submissions'] as $sub) : ?>
                                        <li style="margin-bottom: 10px; padding: 10px; border: 1px solid #eee; background:#fff;">
                                            <strong>Student: <?= htmlspecialchars($sub['student_name']) ?></strong> (<?= htmlspecialchars($sub['status']) ?>)
                                            <br><span class="note">Submitted: <?= htmlspecialchars($sub['submitted_at']) ?></span>
                                            <br>Content: <pre><?= htmlspecialchars($sub['content'] ?? '') ?></pre>
                                            
                                            <?php if ($sub['status'] === 'graded') : ?>
                                                <p class="note" style="color:#0a7;">Graded! Score: <?= (int)$sub['score'] ?> / <?= (int)$as['total_points'] ?></p>
                                            <?php else : ?>
                                                <form method="post" class="inline-form" style="margin-top: 10px;">
                                                    <input type="hidden" name="action" value="instructor_grade_submission">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="submission_id" value="<?= (int) $sub['submission_id'] ?>">
                                                    <input type="number" name="score" required min="0" max="<?= (int)$as['total_points'] ?>" placeholder="Score">
                                                    <input type="text" name="feedback" placeholder="Feedback...">
                                                    <button type="submit">Submit Grade</button>
                                                </form>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <h3 class="sub-heading">Forums</h3>
                    <details>
                        <summary style="cursor: pointer; padding: 4px 10px; background: #eee; border: 1px solid #ccc; display: inline-block; font-size: 0.9em; border-radius: 4px;">Show forums</summary>
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
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="course_id" value="<?= $cid ?>">
                                    <input type="text" name="forum_title" required placeholder="New forum title..." style="width: 50%; padding: 4px;">
                                    <button type="submit" style="padding: 4px 8px;">Create Forum</button>
                                </form>
                            </div>
                        </div>
                    </details>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php else : ?>
        <p class="note">Admin view: stored procedure test forms.</p>
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
