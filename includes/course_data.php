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
        'SELECT m.module_number, m.module_title,
                mat.material_id, mat.material_title, mat.description, mat.file_path, mat.file_type
         FROM modules m
         INNER JOIN materials mat ON mat.module_id = m.module_id
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
        'SELECT f.forum_id, f.forum_title, f.description, t.thread_id 
         FROM forums f 
         INNER JOIN threads t ON t.forum_id = f.forum_id 
         WHERE f.course_id = :cid 
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
