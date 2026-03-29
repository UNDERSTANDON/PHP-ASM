<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * @return array{student_id: int, user_id: int, enrollment_number: string, major: ?string}|null
 */
function lms_get_student_by_user_id(int $userId): ?array
{
    $stmt = get_pdo()->prepare(
        'SELECT student_id, user_id, enrollment_number, major FROM students WHERE user_id = :uid LIMIT 1'
    );
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function lms_list_courses_with_instructor(): array
{
    $sql = 'SELECT c.course_id, c.course_code, c.course_name, c.description, c.credit, c.capacity,
                   u.user_name AS instructor_name
            FROM courses c
            INNER JOIN instructors i ON i.instructor_id = c.instructor_id
            INNER JOIN users u ON u.user_id = i.user_id
            ORDER BY c.course_code';
    return get_pdo()->query($sql)->fetchAll();
}

/**
 * @return array{enrollment_id: int, status: string, enrollment_date: string}|null
 */
function lms_get_enrollment(int $studentId, int $courseId): ?array
{
    $stmt = get_pdo()->prepare(
        'SELECT enrollment_id, status::text AS status, enrollment_date
         FROM enrollments WHERE student_id = :sid AND course_id = :cid LIMIT 1'
    );
    $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function lms_list_materials_for_course(int $courseId): array
{
    $stmt = get_pdo()->prepare(
        'SELECT m.module_id, m.module_number, m.module_title, m.description AS module_desc,
                mat.material_id, mat.material_title, mat.description, mat.file_name, mat.file_path, mat.file_type
         FROM modules m
         LEFT JOIN materials mat ON mat.module_id = m.module_id
         WHERE m.course_id = :cid
         ORDER BY m.module_number, mat.material_id'
    );
    $stmt->execute(['cid' => $courseId]);
    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function lms_list_assessments_for_course(int $courseId): array
{
    $stmt = get_pdo()->prepare(
        'SELECT assessment_id, assessment_type::text AS assessment_type, title, description,
                total_points, due_date
         FROM assessments WHERE course_id = :cid
         ORDER BY due_date NULLS LAST, assessment_id'
    );
    $stmt->execute(['cid' => $courseId]);
    return $stmt->fetchAll();
}

/**
 * @return array{submission_id: int, status: string, score: ?int, submitted_at: string}|null
 */
function lms_get_student_submission(int $assessmentId, int $studentId): ?array
{
    $stmt = get_pdo()->prepare(
        'SELECT submission_id, status::text AS status, score, submitted_at
         FROM submissions WHERE assessment_id = :aid AND student_id = :sid LIMIT 1'
    );
    $stmt->execute(['aid' => $assessmentId, 'sid' => $studentId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

function lms_student_may_access_course_content(?array $enrollment): bool
{
    if ($enrollment === null) {
        return false;
    }
    $s = $enrollment['status'];
    return $s === 'enrolled' || $s === 'completed';
}

/**
 * @return list<array<string, mixed>>
 */
function lms_list_course_forums(int $courseId): array
{
    $stmt = get_pdo()->prepare(
        'SELECT f.forum_id, f.forum_title, f.description, MIN(t.thread_id) AS thread_id, COUNT(t.thread_id) AS thread_count 
         FROM forums f 
         LEFT JOIN threads t ON t.forum_id = f.forum_id 
         WHERE f.course_id = :cid 
         GROUP BY f.forum_id 
         ORDER BY f.created_at DESC'
    );
    $stmt->execute(['cid' => $courseId]);
    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function lms_list_forum_posts(int $threadId): array
{
    $stmt = get_pdo()->prepare(
        'SELECT p.post_id, p.content, p.created_at, u.user_name, u.role::text as role 
         FROM posts p 
         INNER JOIN users u ON u.user_id = p.user_id 
         WHERE p.thread_id = :tid 
         ORDER BY p.created_at ASC'
    );
    $stmt->execute(['tid' => $threadId]);
    return $stmt->fetchAll();
}

/**
 * @return list<array{user_id: int, user_name: string, role: string}>
 */
function lms_list_message_recipients(int $currentUserId): array
{
    $stmt = get_pdo()->prepare(
        "SELECT user_id, user_name, role::text as role FROM users 
         WHERE role IN ('student', 'instructor') AND user_id != :uid 
         ORDER BY role DESC, user_name ASC"
    );
    $stmt->execute(['uid' => $currentUserId]);
    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function lms_list_user_messages(int $userId): array
{
    $stmt = get_pdo()->prepare(
        "SELECT m.message_id, m.subject, m.content, m.created_at, 
                sender.user_name AS sender_name, 
                recipient.user_name AS recipient_name, 
                m.sender_id, m.recipient_id 
         FROM messages m 
         INNER JOIN users sender ON m.sender_id = sender.user_id 
         INNER JOIN users recipient ON m.recipient_id = recipient.user_id 
         WHERE m.recipient_id = :uid OR m.sender_id = :uid 
         ORDER BY m.created_at DESC"
    );
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

/**
 * @return array{thread_id: int, forum_id: int, course_id: int, title: string}|null
 */
function lms_get_thread(int $threadId): ?array
{
    $stmt = get_pdo()->prepare(
        'SELECT t.thread_id, t.forum_id, f.course_id, t.title 
         FROM threads t
         INNER JOIN forums f ON f.forum_id = t.forum_id
         WHERE t.thread_id = :tid LIMIT 1'
    );
    $stmt->execute(['tid' => $threadId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * @return array{user_id: int, role: string}|null
 */
function lms_get_user(int $userId): ?array
{
    $stmt = get_pdo()->prepare('SELECT user_id, role::text as role FROM users WHERE user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * @return array{instructor_id: int, user_id: int, bio: ?string, department: ?string}|null
 */
function lms_get_instructor_by_user_id(int $userId): ?array
{
    $stmt = get_pdo()->prepare(
        'SELECT instructor_id, user_id, bio, department FROM instructors WHERE user_id = :uid LIMIT 1'
    );
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function lms_list_instructor_courses(int $instructorId): array
{
    $sql = 'SELECT c.course_id, c.course_code, c.course_name, c.description, c.credit, c.capacity
            FROM courses c
            WHERE c.instructor_id = :iid
            ORDER BY c.course_code';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['iid' => $instructorId]);
    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function lms_list_assessment_submissions(int $assessmentId): array
{
    $sql = 'SELECT s.submission_id, s.assessment_id, s.student_id, s.content, s.file_path, 
                   s.submitted_at, s.status::text as status, s.score, s.feedback, u.user_name as student_name
            FROM submissions s
            INNER JOIN students st ON st.student_id = s.student_id
            INNER JOIN users u ON u.user_id = st.user_id
            WHERE s.assessment_id = :aid
            ORDER BY s.submitted_at ASC';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['aid' => $assessmentId]);
    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function lms_get_detailed_course_analytics(int $courseId): array
{
    $sql = "
        SELECT 
            u.user_name as student_name,
            s.student_id,
            c.completion_rate,
            c.avg_score
        FROM enrollments e
        INNER JOIN students s ON s.student_id = e.student_id
        INNER JOIN users u ON u.user_id = s.user_id
        INNER JOIN student_course_completion c ON c.student_id = s.student_id AND c.course_id = e.course_id
        WHERE e.course_id = :cid 
          AND e.status = 'enrolled'
          AND c.completion_rate > 0
        ORDER BY u.user_name ASC
    ";
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute(['cid' => $courseId]);
    return $stmt->fetchAll();
}
