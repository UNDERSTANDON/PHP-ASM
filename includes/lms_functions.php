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
    
    lms_recalculate_student_completion($studentId, $courseId);
    
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
    
    $sel = get_pdo()->prepare("SELECT course_id FROM assessments WHERE assessment_id = ?");
    $sel->execute([$assessmentId]);
    if ($courseId = $sel->fetchColumn()) {
        lms_recalculate_student_completion($studentId, (int)$courseId);
    }

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
    
    $sel = get_pdo()->prepare("SELECT s.student_id, a.course_id FROM submissions s INNER JOIN assessments a ON a.assessment_id = s.assessment_id WHERE s.submission_id = ?");
    $sel->execute([$submissionId]);
    if ($meta = $sel->fetch()) {
        lms_recalculate_student_completion((int)$meta['student_id'], (int)$meta['course_id']);
    }

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

/**
 * @return array{course_id: ?int, message: string}
 */
function lms_sp_create_course(
    string $courseCode,
    string $courseName,
    ?string $description,
    int $credit,
    int $instructorId,
    int $capacity
): array {
    $sql = 'SELECT * FROM sp_create_course(:code, :name, :descr, :credit, :instructor, :cap)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'code'       => $courseCode,
        'name'       => $courseName,
        'descr'      => $description,
        'credit'     => $credit,
        'instructor' => $instructorId,
        'cap'        => $capacity,
    ]);
    $row = $stmt->fetch() ?: [];
    $cid = $row['p_course_id'] ?? null;
    return [
        'course_id' => $cid !== null && $cid !== '' ? (int) $cid : null,
        'message'   => (string) ($row['p_message'] ?? ''),
    ];
}

/**
 * @return array{assessment_id: ?int, message: string}
 */
function lms_sp_create_assessment(
    int $courseId,
    int $instructorId,
    string $type,
    string $title,
    ?string $description,
    int $totalPoints,
    ?string $dueDate
): array {
    $sql = 'SELECT * FROM sp_create_assessment(:cid, :iid, cast(:type as assessment_type), :title, :descr, :points, cast(:due as timestamp))';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'cid'    => $courseId,
        'iid'    => $instructorId,
        'type'   => $type,
        'title'  => $title,
        'descr'  => $description,
        'points' => $totalPoints,
        'due'    => $dueDate ?: null,
    ]);
    $row = $stmt->fetch() ?: [];
    $aid = $row['p_assessment_id'] ?? null;
    return [
        'assessment_id' => $aid !== null && $aid !== '' ? (int) $aid : null,
        'message'       => (string) ($row['p_message'] ?? ''),
    ];
}

/**
 * @return array{module_id: ?int, message: string}
 */
function lms_sp_create_module(
    int $courseId,
    int $instructorId,
    string $moduleTitle,
    ?string $description
): array {
    $sql = 'SELECT * FROM sp_create_module(:cid, :iid, :title, :descr)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'cid'   => $courseId,
        'iid'   => $instructorId,
        'title' => $moduleTitle,
        'descr' => $description,
    ]);
    $row = $stmt->fetch() ?: [];
    $mid = $row['p_module_id'] ?? null;
    return [
        'module_id' => $mid !== null && $mid !== '' ? (int) $mid : null,
        'message'   => (string) ($row['p_message'] ?? ''),
    ];
}

/**
 * @return array{material_id: ?int, message: string}
 */
function lms_sp_create_material(
    int $moduleId,
    int $instructorId,
    string $materialTitle,
    ?string $description,
    ?string $fileName,
    ?string $filePath,
    ?string $fileType
): array {
    $sql = 'SELECT * FROM sp_create_material(:mid, :iid, :title, :descr, :fname, :fpath, :ftype)';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([
        'mid'   => $moduleId,
        'iid'   => $instructorId,
        'title' => $materialTitle,
        'descr' => $description,
        'fname' => $fileName,
        'fpath' => $filePath,
        'ftype' => $fileType,
    ]);
    $row = $stmt->fetch() ?: [];
    $matid = $row['p_material_id'] ?? null;
    return [
        'material_id' => $matid !== null && $matid !== '' ? (int) $matid : null,
        'message'     => (string) ($row['p_message'] ?? ''),
    ];
}

/**
 * Application-layer calculation for student course completion metrics.
 * Bypasses PostgreSQL dynamic calculation as per requirements.
 */
function lms_recalculate_student_completion(int $studentId, int $courseId): void
{
    $pdo = get_pdo();
    
    // 1. Get total valid assessments for the course
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assessments WHERE course_id = ? AND total_points IS NOT NULL");
    $stmt->execute([$courseId]);
    $totalAssessments = (int) $stmt->fetchColumn();
    
    // 2. Get the student's submitted assessments for this course
    $stmt = $pdo->prepare("
        SELECT s.score 
        FROM submissions s 
        INNER JOIN assessments a ON a.assessment_id = s.assessment_id 
        WHERE a.course_id = ? AND s.student_id = ? AND a.total_points IS NOT NULL
    ");
    $stmt->execute([$courseId, $studentId]);
    $submissions = $stmt->fetchAll();
    
    $completedCount = count($submissions);
    
    // 3. Math calculation for completion rate
    $completionRate = 0.00;
    if ($totalAssessments > 0) {
        $completionRate = round(($completedCount / $totalAssessments) * 100, 2);
    }
    
    // 4. Math calculation for average score
    $avgScore = null;
    $gradedSum = 0;
    $gradedCount = 0;
    foreach ($submissions as $sub) {
        if ($sub['score'] !== null) {
            $gradedSum += (float) $sub['score'];
            $gradedCount++;
        }
    }
    if ($gradedCount > 0) {
        $avgScore = round($gradedSum / $gradedCount, 2);
    }
    
    // 5. Explicitly UPSERT into the tracking table
    $upsertStmt = $pdo->prepare("
        INSERT INTO student_course_completion (student_id, course_id, completion_rate, avg_score, last_updated)
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (student_id, course_id) DO UPDATE SET 
            completion_rate = EXCLUDED.completion_rate,
            avg_score = EXCLUDED.avg_score,
            last_updated = EXCLUDED.last_updated
    ");
    $upsertStmt->execute([$studentId, $courseId, $completionRate, $avgScore]);
}

