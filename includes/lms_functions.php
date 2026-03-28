<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

/**
 * LMS PHP layer: wrappers around PostgreSQL functions (plpgsql / SQL).
 */

function lms_pg_bool(mixed $value): bool
{
    if ($value === true || $value === 1) {
        return true;
    }
    if ($value === false || $value === null || $value === 0 || $value === '') {
        return false;
    }
    if (is_string($value)) {
        return strtolower($value) === 't' || $value === '1';
    }

    return (bool) $value;
}

function lms_fn_enrollment_count(int $courseId): int
{
    $sql = 'SELECT fn_enrollment_count(:cid) AS c';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['cid' => $courseId]);
    $row = $stmt->fetch();
    return (int) ($row['c'] ?? 0);
}

function lms_fn_student_enrolled(int $studentId, int $courseId): bool
{
    $sql = 'SELECT fn_student_enrolled(:sid, :cid) AS e';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
    $row = $stmt->fetch();
    return lms_pg_bool($row['e'] ?? false);
}

function lms_fn_course_avg_score(int $courseId): float
{
    $sql = 'SELECT fn_course_avg_score(:cid) AS a';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['cid' => $courseId]);
    $row = $stmt->fetch();
    return (float) ($row['a'] ?? 0);
}

/**
 * @return array{message: string}
 */
function lms_sp_enroll_student(int $studentId, int $courseId): array
{
    $sql = 'SELECT * FROM sp_enroll_student(:sid, :cid)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
    $row = $stmt->fetch() ?: [];
    return ['message' => (string) ($row['p_message'] ?? '')];
}

/**
 * @return array{message: string}
 */
function lms_sp_drop_enrollment(int $studentId, int $courseId): array
{
    $sql = 'SELECT * FROM sp_drop_enrollment(:sid, :cid)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
    $row = $stmt->fetch() ?: [];
    return ['message' => (string) ($row['p_message'] ?? '')];
}

/**
 * @return array{message: string}
 */
function lms_sp_record_material_access(int $materialId, int $studentId): array
{
    $sql = 'SELECT * FROM sp_record_material_access(:mid, :sid)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['mid' => $materialId, 'sid' => $studentId]);
    $row = $stmt->fetch() ?: [];
    return ['message' => (string) ($row['p_message'] ?? '')];
}

/**
 * @return array{submission_id: ?int, message: string}
 */
function lms_sp_upsert_submission(
    int $assessmentId,
    int $studentId,
    ?string $content,
    ?string $filePath
): array {
    $sql = 'SELECT * FROM sp_upsert_submission(:aid, :sid, :content, :fpath)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'aid'     => $assessmentId,
        'sid'     => $studentId,
        'content' => $content,
        'fpath'   => $filePath,
    ]);
    $row = $stmt->fetch() ?: [];
    $sid = $row['p_submission_id'] ?? null;
    return [
        'submission_id' => $sid !== null && $sid !== '' ? (int) $sid : null,
        'message'       => (string) ($row['p_message'] ?? ''),
    ];
}

/**
 * @return array{message: string}
 */
function lms_sp_grade_submission(
    int $submissionId,
    int $instructorId,
    int $score,
    ?string $feedback
): array {
    $sql = 'SELECT * FROM sp_grade_submission(:subid, :iid, :score, :fb)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'subid' => $submissionId,
        'iid'   => $instructorId,
        'score' => $score,
        'fb'    => $feedback,
    ]);
    $row = $stmt->fetch() ?: [];
    return ['message' => (string) ($row['p_message'] ?? '')];
}

/**
 * @return array{message: string}
 */
function lms_sp_refresh_course_analytics(int $courseId): array
{
    $sql = 'SELECT sp_refresh_course_analytics(:cid)';
    get_pdo()->prepare($sql)->execute(['cid' => $courseId]);
    return ['message' => 'ok'];
}

/**
 * @return array{message: string}
 */
function lms_sp_refresh_student_progress(int $studentId, int $courseId): array
{
    $sql = 'SELECT sp_refresh_student_progress(:sid, :cid)';
    get_pdo()->prepare($sql)->execute(['sid' => $studentId, 'cid' => $courseId]);
    return ['message' => 'ok'];
}

/**
 * Create a forum (board) and thread for a course. Returns new forum_id and thread_id.
 *
 * @return array{forum_id: ?int, thread_id: ?int, message: string}
 */
function lms_sp_create_forum(
    int $courseId,
    int $userId,
    string $forumTitle,
    ?string $description
): array {
    $sql = 'SELECT * FROM sp_create_forum(:cid, :uid, :title, :descr)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'cid'    => $courseId,
        'uid'    => $userId,
        'title'  => $forumTitle,
        'descr'  => $description,
    ]);
    $row = $stmt->fetch() ?: [];
    $fid = $row['p_forum_id'] ?? null;
    $tid = $row['p_thread_id'] ?? null;
    return [
        'forum_id'  => $fid !== null && $fid !== '' ? (int) $fid : null,
        'thread_id' => $tid !== null && $tid !== '' ? (int) $tid : null,
        'message'   => (string) ($row['p_message'] ?? ''),
    ];
}

/**
 * Create a thread inside an existing forum (not a new forum).
 *
 * @return array{thread_id: ?int, message: string}
 */
function lms_sp_create_forum_thread(int $forumId, int $userId, string $title): array
{
    $sql = 'SELECT * FROM sp_create_forum_thread(:fid, :uid, :title)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['fid' => $forumId, 'uid' => $userId, 'title' => $title]);
    $row = $stmt->fetch() ?: [];
    $tid = $row['p_thread_id'] ?? null;
    return [
        'thread_id' => $tid !== null && $tid !== '' ? (int) $tid : null,
        'message'   => (string) ($row['p_message'] ?? ''),
    ];
}

/**
 * @return array{message_id: ?int, message: string}
 */
function lms_sp_send_message(
    int $senderId,
    int $recipientId,
    ?string $subject,
    string $content
): array {
    $sql = 'SELECT * FROM sp_send_message(:from_u, :to_u, :subj, :body)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'from_u' => $senderId,
        'to_u'   => $recipientId,
        'subj'   => $subject,
        'body'   => $content,
    ]);
    $row = $stmt->fetch() ?: [];
    $mid = $row['p_message_id'] ?? null;
    return [
        'message_id' => $mid !== null && $mid !== '' ? (int) $mid : null,
        'message'    => (string) ($row['p_message'] ?? ''),
    ];
}

/**
 * @return array{post_id: ?int, message: string}
 */
function lms_sp_add_forum_post(
    int $threadId,
    int $userId,
    string $content
): array {
    $content = trim($content);
    if ($content === '') {
        return ['post_id' => null, 'message' => 'empty_content'];
    }

    $sql = 'SELECT * FROM sp_add_forum_post(:tid, :uid, :content)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'tid'     => $threadId,
        'uid'     => $userId,
        'content' => $content,
    ]);
    $row = $stmt->fetch() ?: [];
    $pid = $row['p_post_id'] ?? null;
    return [
        'post_id' => $pid !== null && $pid !== '' ? (int) $pid : null,
        'message' => (string) ($row['p_message'] ?? ''),
    ];
}
