-- Seed data for php_asm (PostgreSQL)
--
-- Why you saw user_id 4,5,6: SERIAL uses a sequence. Deleting rows does NOT rewind it;
-- the next INSERT still uses MAX+1 from the sequence. Re-run this file on a fresh schema,
-- or use OVERRIDING SYSTEM VALUE below so IDs stay 1..n, then setval(...) at the end of each table.
--
-- Run after PHP-ASM.sql (schema must exist).

-- Clear demo data if re-applying (optional; comment out if you keep production data)
-- TRUNCATE submissions, assessments, materials, modules, forums, threads, posts,
--   messages, material_access, enrollments, course_analytics, student_progress,
--   sessions, courses, students, instructors, users RESTART IDENTITY CASCADE;

-- Demo login password for all three: password  (bcrypt via PHP password_hash)
INSERT INTO users (user_id, user_name, user_email, password_hash, role)
OVERRIDING SYSTEM VALUE VALUES
  (1, 'admin', 'admin@example.com', '$2y$10$CvXhTlhfYsXWd1H2/3jV9OyhbIRq/GgPsg/QeyowMD4jtNbcAzYLm', 'admin'), -- The password is '12345678'
  (2, 'instructor', 'instructor@example.com', '$2y$10$CvXhTlhfYsXWd1H2/3jV9OyhbIRq/GgPsg/QeyowMD4jtNbcAzYLm', 'instructor'), -- The password is '12345678'
  (3, 'student', 'student@example.com', '$2y$10$CvXhTlhfYsXWd1H2/3jV9OyhbIRq/GgPsg/QeyowMD4jtNbcAzYLm', 'student'); -- The password is '12345678'

SELECT setval(pg_get_serial_sequence('users', 'user_id'), (SELECT COALESCE(MAX(user_id), 1) FROM users));

INSERT INTO instructors (instructor_id, user_id, bio, department)
OVERRIDING SYSTEM VALUE VALUES
  (1, 1, 'I am an admin', 'Admin'),
  (2, 2, 'I am an instructor', 'Instructor');

SELECT setval(pg_get_serial_sequence('instructors', 'instructor_id'), (SELECT COALESCE(MAX(instructor_id), 1) FROM instructors));

INSERT INTO students (student_id, user_id, enrollment_number, major)
OVERRIDING SYSTEM VALUE VALUES
  (1, 3, '123456', 'Computer Science');

SELECT setval(pg_get_serial_sequence('students', 'student_id'), (SELECT COALESCE(MAX(student_id), 1) FROM students));

INSERT INTO courses (course_id, course_code, course_name, description, credit, instructor_id, capacity)
OVERRIDING SYSTEM VALUE VALUES
  (1, 'WEB101', 'Introduction to Web Development', 'This is a course about website development', 2, 2, 30);
  INSERT INTO courses (course_id, course_code, course_name, description, credit, instructor_id, capacity)
OVERRIDING SYSTEM VALUE VALUES
  (2, 'CS101', 'Introduction to Computer Science', 'This is a course about computer science', 3, 1, 30);


SELECT setval(pg_get_serial_sequence('courses', 'course_id'), (SELECT COALESCE(MAX(course_id), 1) FROM courses));

INSERT INTO modules (module_id, course_id, module_number, module_title, description)
OVERRIDING SYSTEM VALUE VALUES
  (1, 1, 1, 'Introduction to Computer Science', 'This is a module about computer science');

SELECT setval(pg_get_serial_sequence('modules', 'module_id'), (SELECT COALESCE(MAX(module_id), 1) FROM modules));

INSERT INTO materials (material_id, module_id, material_title, description, file_name, file_path, file_type, upload_date)
OVERRIDING SYSTEM VALUE VALUES
  (1, 1, 'Introduction to Computer Science', 'This is a material about computer science',
   'introduction.pdf', 'https://example.com/introduction.pdf', 'pdf', CURRENT_TIMESTAMP);

SELECT setval(pg_get_serial_sequence('materials', 'material_id'), (SELECT COALESCE(MAX(material_id), 1) FROM materials));

INSERT INTO forums (forum_id, course_id, forum_title, description)
OVERRIDING SYSTEM VALUE VALUES
  (1, 1, 'General discussion', 'Course Q&A');

SELECT setval(pg_get_serial_sequence('forums', 'forum_id'), (SELECT COALESCE(MAX(forum_id), 1) FROM forums));

INSERT INTO assessments (assessment_id, course_id, assessment_type, title, description, total_points, due_date)
OVERRIDING SYSTEM VALUE VALUES
  (1, 1, 'quiz', 'Quiz 1', 'This is a quiz about computer science', 100, CURRENT_TIMESTAMP + INTERVAL '7 days');

SELECT setval(pg_get_serial_sequence('assessments', 'assessment_id'), (SELECT COALESCE(MAX(assessment_id), 1) FROM assessments));

INSERT INTO submissions (
  submission_id, assessment_id, student_id, content, file_path,
  submitted_at, status, score, feedback, graded_at, graded_by
)
OVERRIDING SYSTEM VALUE VALUES
  (1, 1, 1, 'I am a student', 'https://example.com/submission.pdf',
   CURRENT_TIMESTAMP, 'graded', 100, 'Good job', CURRENT_TIMESTAMP, 1);

SELECT setval(pg_get_serial_sequence('submissions', 'submission_id'), (SELECT COALESCE(MAX(submission_id), 1) FROM submissions));

INSERT INTO enrollments (student_id, course_id, status)
VALUES (1, 1, 'enrolled');

-- ---------------------------------------------------------------------------
-- Inspect IDs
-- ---------------------------------------------------------------------------
SELECT user_id FROM users ORDER BY user_id;
SELECT instructor_id FROM instructors ORDER BY instructor_id;
SELECT student_id FROM students ORDER BY student_id;
SELECT course_id FROM courses ORDER BY course_id;
SELECT forum_id FROM forums ORDER BY forum_id;
SELECT module_id FROM modules ORDER BY module_id;
SELECT material_id FROM materials ORDER BY material_id;
SELECT assessment_id FROM assessments ORDER BY assessment_id;
SELECT submission_id FROM submissions ORDER BY submission_id;
SELECT thread_id FROM threads ORDER BY thread_id;
SELECT message_id FROM messages ORDER BY message_id;
SELECT enrollment_id FROM enrollments ORDER BY enrollment_id;
