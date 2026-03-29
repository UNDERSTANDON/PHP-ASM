-- PHP-ASM: LMS schema + functions (PostgreSQL)
--
-- 1) Create database (once, as superuser), e.g.:
--    CREATE DATABASE php_asm ENCODING 'UTF8';
-- 2) Connect to php_asm, then run this file:
--    psql -U postgres -d php_asm -f PHP-ASM.sql
--
SET client_encoding = 'UTF8';

-- ---------------------------------------------------------------------------
-- Clean slate (development) — dedicated DB recommended (php_asm only)
-- ---------------------------------------------------------------------------
DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO CURRENT_USER;
GRANT ALL ON SCHEMA public TO PUBLIC;

-- ---------------------------------------------------------------------------
-- ENUM types (match planning.txt)
-- ---------------------------------------------------------------------------
CREATE TYPE user_role AS ENUM ('student', 'instructor', 'admin');
CREATE TYPE enrollment_status AS ENUM ('enrolled', 'pending', 'dropped', 'completed');
CREATE TYPE assessment_type AS ENUM ('quiz', 'assignment', 'exam', 'project');
CREATE TYPE submission_status AS ENUM ('submitted', 'graded', 'pending', 'late');

-- ---------------------------------------------------------------------------
-- Trigger helper: ON UPDATE CURRENT_TIMESTAMP equivalent
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION trg_set_updated_at()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  NEW.updated_at := CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$;

-- ---------------------------------------------------------------------------
-- CORE TABLES
-- ---------------------------------------------------------------------------
CREATE TABLE users (
  user_id       SERIAL PRIMARY KEY,
  user_name     VARCHAR(120) NOT NULL,
  user_email    VARCHAR(255) NOT NULL UNIQUE,
  user_phone    VARCHAR(40),
  password_hash VARCHAR(255) NOT NULL,
  role          user_role NOT NULL DEFAULT 'student',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER users_updated_at
  BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE PROCEDURE trg_set_updated_at();

CREATE TABLE instructors (
  instructor_id SERIAL PRIMARY KEY,
  user_id       INTEGER NOT NULL UNIQUE REFERENCES users(user_id) ON DELETE CASCADE,
  bio           TEXT,
  department    VARCHAR(120),
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE students (
  student_id         SERIAL PRIMARY KEY,
  user_id            INTEGER NOT NULL UNIQUE REFERENCES users(user_id) ON DELETE CASCADE,
  enrollment_number  VARCHAR(64) NOT NULL UNIQUE,
  major              VARCHAR(120),
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE courses (
  course_id      SERIAL PRIMARY KEY,
  course_code    VARCHAR(32) NOT NULL UNIQUE,
  course_name    VARCHAR(255) NOT NULL,
  description    TEXT,
  credit         INTEGER NOT NULL DEFAULT 3 CHECK (credit >= 0),
  instructor_id  INTEGER NOT NULL REFERENCES instructors(instructor_id),
  capacity       INTEGER NOT NULL DEFAULT 30 CHECK (capacity >= 0),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER courses_updated_at
  BEFORE UPDATE ON courses
  FOR EACH ROW EXECUTE PROCEDURE trg_set_updated_at();

CREATE TABLE modules (
  module_id     SERIAL PRIMARY KEY,
  course_id     INTEGER NOT NULL REFERENCES courses(course_id) ON DELETE CASCADE,
  module_number INTEGER NOT NULL CHECK (module_number >= 0),
  module_title  VARCHAR(255) NOT NULL,
  description   TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (course_id, module_number)
);

CREATE TABLE enrollments (
  enrollment_id   SERIAL PRIMARY KEY,
  student_id      INTEGER NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
  course_id       INTEGER NOT NULL REFERENCES courses(course_id) ON DELETE CASCADE,
  enrollment_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status          enrollment_status NOT NULL DEFAULT 'pending',
  grade           VARCHAR(8),
  final_score     NUMERIC(5,2),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (student_id, course_id)
);

CREATE TRIGGER enrollments_updated_at
  BEFORE UPDATE ON enrollments
  FOR EACH ROW EXECUTE PROCEDURE trg_set_updated_at();

CREATE TABLE materials (
  material_id    SERIAL PRIMARY KEY,
  module_id      INTEGER NOT NULL REFERENCES modules(module_id) ON DELETE CASCADE,
  material_title VARCHAR(255) NOT NULL,
  description    TEXT,
  file_name      VARCHAR(255),
  file_path      VARCHAR(512),
  file_type      VARCHAR(64),
  upload_date    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE material_access (
  access_id   SERIAL PRIMARY KEY,
  material_id INTEGER NOT NULL REFERENCES materials(material_id) ON DELETE CASCADE,
  student_id  INTEGER NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
  accessed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE assessments (
  assessment_id   SERIAL PRIMARY KEY,
  course_id       INTEGER NOT NULL REFERENCES courses(course_id) ON DELETE CASCADE,
  assessment_type assessment_type NOT NULL,
  title           VARCHAR(255) NOT NULL,
  description     TEXT,
  total_points    INTEGER NOT NULL DEFAULT 100 CHECK (total_points >= 0),
  due_date        TIMESTAMP,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE submissions (
  submission_id SERIAL PRIMARY KEY,
  assessment_id INTEGER NOT NULL REFERENCES assessments(assessment_id) ON DELETE CASCADE,
  student_id    INTEGER NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
  content       TEXT,
  file_path     VARCHAR(512),
  submitted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status        submission_status NOT NULL DEFAULT 'pending',
  score         INTEGER CHECK (score IS NULL OR score >= 0),
  feedback      TEXT,
  graded_at     TIMESTAMP,
  graded_by     INTEGER REFERENCES instructors(instructor_id),
  UNIQUE (assessment_id, student_id)
);

CREATE TABLE forums (
  forum_id    SERIAL PRIMARY KEY,
  course_id   INTEGER NOT NULL REFERENCES courses(course_id) ON DELETE CASCADE,
  forum_title VARCHAR(255) NOT NULL,
  description TEXT,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (course_id, forum_title)
);

CREATE TABLE threads (
  thread_id  SERIAL PRIMARY KEY,
  forum_id   INTEGER NOT NULL REFERENCES forums(forum_id) ON DELETE CASCADE,
  created_by INTEGER NOT NULL REFERENCES users(user_id),
  title      VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE posts (
  post_id    SERIAL PRIMARY KEY,
  thread_id  INTEGER NOT NULL REFERENCES threads(thread_id) ON DELETE CASCADE,
  user_id    INTEGER NOT NULL REFERENCES users(user_id),
  content    TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER posts_updated_at
  BEFORE UPDATE ON posts
  FOR EACH ROW EXECUTE PROCEDURE trg_set_updated_at();

CREATE TABLE messages (
  message_id   SERIAL PRIMARY KEY,
  sender_id    INTEGER NOT NULL REFERENCES users(user_id),
  recipient_id INTEGER NOT NULL REFERENCES users(user_id),
  subject      VARCHAR(255),
  content      TEXT NOT NULL,
  is_read      BOOLEAN NOT NULL DEFAULT FALSE,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE course_analytics (
  analytics_id    SERIAL PRIMARY KEY,
  course_id       INTEGER NOT NULL UNIQUE REFERENCES courses(course_id) ON DELETE CASCADE,
  total_students  INTEGER NOT NULL DEFAULT 0 CHECK (total_students >= 0),
  avg_score       NUMERIC(5,2),
  completion_rate NUMERIC(5,2),
  last_updated    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE student_progress (
  progress_id           SERIAL PRIMARY KEY,
  student_id            INTEGER NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
  course_id             INTEGER NOT NULL REFERENCES courses(course_id) ON DELETE CASCADE,
  modules_completed     INTEGER NOT NULL DEFAULT 0 CHECK (modules_completed >= 0),
  assessments_submitted INTEGER NOT NULL DEFAULT 0 CHECK (assessments_submitted >= 0),
  avg_score             NUMERIC(5,2),
  last_access           TIMESTAMP,
  UNIQUE (student_id, course_id)
);

CREATE TABLE student_course_completion (
  student_id      INTEGER NOT NULL REFERENCES students(student_id) ON DELETE CASCADE,
  course_id       INTEGER NOT NULL REFERENCES courses(course_id) ON DELETE CASCADE,
  completion_rate NUMERIC(5,2) NOT NULL DEFAULT 0.00,
  avg_score       NUMERIC(5,2),
  last_updated    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id, course_id)
);

CREATE TABLE sessions (
  session_id SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
  token      VARCHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(45),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL
);

-- ---------------------------------------------------------------------------
-- Scalar functions
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_enrollment_count(p_course_id INTEGER)
RETURNS INTEGER
LANGUAGE sql
STABLE
AS $$
  SELECT COUNT(*)::INTEGER
  FROM enrollments e
  WHERE e.course_id = p_course_id AND e.status = 'enrolled';
$$;

CREATE OR REPLACE FUNCTION fn_student_enrolled(p_student_id INTEGER, p_course_id INTEGER)
RETURNS BOOLEAN
LANGUAGE sql
STABLE
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM enrollments e
    WHERE e.student_id = p_student_id
      AND e.course_id = p_course_id
      AND e.status IN ('enrolled', 'completed')
  );
$$;

CREATE OR REPLACE FUNCTION fn_course_avg_score(p_course_id INTEGER)
RETURNS NUMERIC(5,2)
LANGUAGE sql
STABLE
AS $$
  SELECT COALESCE(ROUND(AVG(s.score)::NUMERIC, 2), 0)
  FROM submissions s
  INNER JOIN assessments a ON a.assessment_id = s.assessment_id
  WHERE a.course_id = p_course_id
    AND s.status = 'graded'
    AND s.score IS NOT NULL;
$$;

-- ---------------------------------------------------------------------------
-- void helpers (internal / PHP may SELECT these)
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION sp_refresh_course_analytics(p_course_id INTEGER)
RETURNS VOID
LANGUAGE plpgsql
AS $$
DECLARE
  v_total    INTEGER;
  v_avg      NUMERIC(5,2);
  v_complete NUMERIC(5,2);
BEGIN
  SELECT COUNT(*)::INTEGER INTO v_total
  FROM enrollments
  WHERE course_id = p_course_id AND status IN ('enrolled', 'completed');

  v_avg := fn_course_avg_score(p_course_id);

  SELECT COALESCE(
    100.0 * SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END)::NUMERIC
    / NULLIF(COUNT(*)::NUMERIC, 0),
    0
  ) INTO v_complete
  FROM enrollments
  WHERE course_id = p_course_id
    AND status IN ('enrolled', 'completed', 'dropped');

  INSERT INTO course_analytics (
    course_id, total_students, avg_score, completion_rate, last_updated
  ) VALUES (
    p_course_id,
    v_total,
    NULLIF(v_avg, 0),
    v_complete,
    CURRENT_TIMESTAMP
  )
  ON CONFLICT (course_id) DO UPDATE SET
    total_students = EXCLUDED.total_students,
    avg_score = EXCLUDED.avg_score,
    completion_rate = EXCLUDED.completion_rate,
    last_updated = EXCLUDED.last_updated;
END;
$$;

CREATE OR REPLACE FUNCTION sp_refresh_student_progress(
  p_student_id INTEGER,
  p_course_id INTEGER
)
RETURNS VOID
LANGUAGE plpgsql
AS $$
DECLARE
  v_modules INTEGER;
  v_subs    INTEGER;
  v_avg     NUMERIC(5,2);
  v_last    TIMESTAMP;
BEGIN
  SELECT COUNT(DISTINCT m.module_id)::INTEGER INTO v_modules
  FROM materials mat
  INNER JOIN modules m ON m.module_id = mat.module_id
  INNER JOIN material_access ma
    ON ma.material_id = mat.material_id AND ma.student_id = p_student_id
  WHERE m.course_id = p_course_id;

  SELECT COUNT(*)::INTEGER INTO v_subs
  FROM submissions s
  INNER JOIN assessments a ON a.assessment_id = s.assessment_id
  WHERE a.course_id = p_course_id AND s.student_id = p_student_id;

  SELECT ROUND(AVG(s.score)::NUMERIC, 2) INTO v_avg
  FROM submissions s
  INNER JOIN assessments a ON a.assessment_id = s.assessment_id
  WHERE a.course_id = p_course_id
    AND s.student_id = p_student_id
    AND s.status = 'graded'
    AND s.score IS NOT NULL;

  SELECT MAX(ma.accessed_at) INTO v_last
  FROM materials mat
  INNER JOIN modules m ON m.module_id = mat.module_id
  INNER JOIN material_access ma
    ON ma.material_id = mat.material_id AND ma.student_id = p_student_id
  WHERE m.course_id = p_course_id;

  INSERT INTO student_progress (
    student_id,
    course_id,
    modules_completed,
    assessments_submitted,
    avg_score,
    last_access
  ) VALUES (
    p_student_id,
    p_course_id,
    v_modules,
    v_subs,
    NULLIF(v_avg, 0),
    v_last
  )
  ON CONFLICT (student_id, course_id) DO UPDATE SET
    modules_completed = EXCLUDED.modules_completed,
    assessments_submitted = EXCLUDED.assessments_submitted,
    avg_score = EXCLUDED.avg_score,
    last_access = EXCLUDED.last_access;
END;
$$;

CREATE OR REPLACE FUNCTION sp_enroll_student(
  p_student_id INTEGER,
  p_course_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
  v_count INTEGER;
  v_cap   INTEGER;
BEGIN
  IF fn_student_enrolled(p_student_id, p_course_id) THEN
    p_message := 'already_enrolled';
    RETURN;
  END IF;

  SELECT capacity INTO v_cap FROM courses WHERE course_id = p_course_id;
  IF v_cap IS NULL THEN
    p_message := 'course_not_found';
    RETURN;
  END IF;

  SELECT COUNT(*)::INTEGER INTO v_count
  FROM enrollments
  WHERE course_id = p_course_id AND status = 'enrolled';

  IF v_count >= v_cap THEN
    p_message := 'course_full';
    RETURN;
  END IF;

  -- Row may still exist (e.g. status dropped/pending); UNIQUE allows only one row per pair.
  UPDATE enrollments
  SET
    status = 'enrolled',
    enrollment_date = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
  WHERE student_id = p_student_id
    AND course_id = p_course_id
    AND status IN ('dropped', 'pending');

  IF FOUND THEN
    p_message := 'ok';
    PERFORM sp_refresh_course_analytics(p_course_id);
    PERFORM sp_refresh_student_progress(p_student_id, p_course_id);
    RETURN;
  END IF;

  INSERT INTO enrollments (student_id, course_id, status, enrollment_date)
  VALUES (p_student_id, p_course_id, 'enrolled', CURRENT_TIMESTAMP);

  p_message := 'ok';
  PERFORM sp_refresh_course_analytics(p_course_id);
  PERFORM sp_refresh_student_progress(p_student_id, p_course_id);
END;
$$;

CREATE OR REPLACE FUNCTION sp_drop_enrollment(
  p_student_id INTEGER,
  p_course_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
  v_affected INTEGER;
BEGIN
  UPDATE enrollments
  SET status = 'dropped', updated_at = CURRENT_TIMESTAMP
  WHERE student_id = p_student_id
    AND course_id = p_course_id
    AND status IN ('enrolled', 'pending');

  GET DIAGNOSTICS v_affected = ROW_COUNT;
  IF v_affected = 0 THEN
    p_message := 'not_found';
    RETURN;
  END IF;

  p_message := 'ok';
  PERFORM sp_refresh_course_analytics(p_course_id);
  PERFORM sp_refresh_student_progress(p_student_id, p_course_id);
END;
$$;

CREATE OR REPLACE FUNCTION sp_record_material_access(
  p_material_id INTEGER,
  p_student_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
BEGIN
  INSERT INTO material_access (material_id, student_id, accessed_at)
  VALUES (p_material_id, p_student_id, CURRENT_TIMESTAMP);
  p_message := 'ok';
END;
$$;

CREATE OR REPLACE FUNCTION sp_upsert_submission(
  p_assessment_id INTEGER,
  p_student_id INTEGER,
  p_content TEXT,
  p_file_path VARCHAR(512),
  OUT p_submission_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
  v_due       TIMESTAMP;
  v_course    INTEGER;
  v_sub_status submission_status;
BEGIN
  SELECT due_date, course_id INTO v_due, v_course
  FROM assessments
  WHERE assessment_id = p_assessment_id;

  IF v_course IS NULL THEN
    p_message := 'assessment_not_found';
    p_submission_id := NULL;
    RETURN;
  END IF;

  IF NOT fn_student_enrolled(p_student_id, v_course) THEN
    p_message := 'not_enrolled';
    p_submission_id := NULL;
    RETURN;
  END IF;

  IF v_due IS NOT NULL AND CURRENT_TIMESTAMP > v_due THEN
    v_sub_status := 'late';
  ELSE
    v_sub_status := 'submitted';
  END IF;

  INSERT INTO submissions (
    assessment_id, student_id, content, file_path, submitted_at, status
  ) VALUES (
    p_assessment_id, p_student_id, p_content, p_file_path, CURRENT_TIMESTAMP, v_sub_status
  )
  ON CONFLICT (assessment_id, student_id) DO UPDATE SET
    content = EXCLUDED.content,
    file_path = EXCLUDED.file_path,
    submitted_at = EXCLUDED.submitted_at,
    status = CASE
      WHEN submissions.status = 'graded' THEN submissions.status
      ELSE EXCLUDED.status
    END
  RETURNING submission_id INTO p_submission_id;

  p_message := 'ok';
  PERFORM sp_refresh_student_progress(p_student_id, v_course);
END;
$$;

CREATE OR REPLACE FUNCTION sp_grade_submission(
  p_submission_id INTEGER,
  p_instructor_id INTEGER,
  p_score INTEGER,
  p_feedback TEXT,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
  v_assessment INTEGER;
  v_course     INTEGER;
  v_student    INTEGER;
  v_max        INTEGER;
  v_status     submission_status;
BEGIN
  SELECT s.assessment_id, s.student_id, a.course_id, a.total_points, s.status
  INTO v_assessment, v_student, v_course, v_max, v_status
  FROM submissions s
  INNER JOIN assessments a ON a.assessment_id = s.assessment_id
  WHERE s.submission_id = p_submission_id;

  IF v_assessment IS NULL THEN
    p_message := 'submission_not_found';
    RETURN;
  END IF;

  IF v_status = 'graded' THEN
    p_message := 'already_graded';
    RETURN;
  END IF;

  IF p_score < 0 OR p_score > v_max THEN
    p_message := 'invalid_score';
    RETURN;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM courses c
    WHERE c.course_id = v_course AND c.instructor_id = p_instructor_id
  ) THEN
    p_message := 'not_course_instructor';
    RETURN;
  END IF;

  UPDATE submissions SET
    score = p_score,
    feedback = p_feedback,
    status = 'graded',
    graded_at = CURRENT_TIMESTAMP,
    graded_by = p_instructor_id
  WHERE submission_id = p_submission_id;

  p_message := 'ok';
  PERFORM sp_refresh_student_progress(v_student, v_course);
  PERFORM sp_refresh_course_analytics(v_course);
END;
$$;

-- Creates a forum (board) and an initial thread for a course.
CREATE OR REPLACE FUNCTION sp_create_forum(
  p_course_id INTEGER,
  p_user_id INTEGER,
  p_forum_title VARCHAR(255),
  p_description TEXT,
  OUT p_forum_id INTEGER,
  OUT p_thread_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM courses WHERE course_id = p_course_id) THEN
    p_message := 'course_not_found';
    p_forum_id := NULL;
    p_thread_id := NULL;
    RETURN;
  END IF;

  -- Anyone (student enrolled/completed, instructor, admin) can create
  IF NOT EXISTS (
    SELECT 1 FROM enrollments e
    INNER JOIN students s ON s.student_id = e.student_id
    WHERE e.course_id = p_course_id
      AND e.status IN ('enrolled', 'completed')
      AND s.user_id = p_user_id
  ) AND NOT EXISTS (
    SELECT 1 FROM courses c
    INNER JOIN instructors i ON i.instructor_id = c.instructor_id
    WHERE c.course_id = p_course_id AND i.user_id = p_user_id
  ) AND NOT EXISTS (
    SELECT 1 FROM users u WHERE u.user_id = p_user_id AND u.role IN ('admin', 'instructor')
  ) THEN
    p_message := 'forbidden';
    p_forum_id := NULL;
    p_thread_id := NULL;
    RETURN;
  END IF;

  BEGIN
    INSERT INTO forums (course_id, forum_title, description, created_at)
    VALUES (p_course_id, p_forum_title, p_description, CURRENT_TIMESTAMP)
    RETURNING forum_id INTO p_forum_id;

    INSERT INTO threads (forum_id, created_by, title, created_at)
    VALUES (p_forum_id, p_user_id, p_forum_title, CURRENT_TIMESTAMP)
    RETURNING thread_id INTO p_thread_id;

    p_message := 'ok';
  EXCEPTION
    WHEN unique_violation THEN
      p_message := 'duplicate_forum_title';
      p_forum_id := NULL;
      p_thread_id := NULL;
      RETURN;
  END;
END;
$$;

CREATE OR REPLACE FUNCTION sp_add_forum_post(
  p_thread_id INTEGER,
  p_user_id INTEGER,
  p_content TEXT,
  OUT p_post_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
  v_course INTEGER;
BEGIN
  SELECT f.course_id INTO v_course 
  FROM threads t 
  INNER JOIN forums f ON f.forum_id = t.forum_id 
  WHERE t.thread_id = p_thread_id;

  IF v_course IS NULL THEN
    p_message := 'thread_not_found';
    p_post_id := NULL;
    RETURN;
  END IF;

  -- Allowed: enrolled/completed student, course instructor, admin, or any instructor (prototype).
  IF NOT EXISTS (
    SELECT 1 FROM enrollments e
    INNER JOIN students s ON s.student_id = e.student_id
    WHERE e.course_id = v_course
      AND e.status IN ('enrolled', 'completed')
      AND s.user_id = p_user_id
  ) AND NOT EXISTS (
    SELECT 1 FROM courses c
    INNER JOIN instructors i ON i.instructor_id = c.instructor_id
    WHERE c.course_id = v_course AND i.user_id = p_user_id
  ) AND NOT EXISTS (
    SELECT 1 FROM users u WHERE u.user_id = p_user_id AND u.role IN ('admin', 'instructor')
  ) THEN
    p_message := 'forbidden';
    p_post_id := NULL;
    RETURN;
  END IF;

  IF p_content IS NULL OR TRIM(p_content) = '' THEN
    p_message := 'empty_content';
    p_post_id := NULL;
    RETURN;
  END IF;

  INSERT INTO posts (thread_id, user_id, content, created_at, updated_at)
  VALUES (p_thread_id, p_user_id, p_content, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
  RETURNING post_id INTO p_post_id;

  p_message := 'ok';
END;
$$;

CREATE OR REPLACE FUNCTION sp_send_message(
  p_sender_id INTEGER,
  p_recipient_id INTEGER,
  p_subject VARCHAR(255),
  p_content TEXT,
  OUT p_message_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
BEGIN
  IF p_sender_id = p_recipient_id THEN
    p_message := 'invalid_recipient';
    p_message_id := NULL;
    RETURN;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM users WHERE user_id = p_recipient_id) THEN
    p_message := 'recipient_not_found';
    p_message_id := NULL;
    RETURN;
  END IF;

  INSERT INTO messages (sender_id, recipient_id, subject, content, is_read, created_at)
  VALUES (p_sender_id, p_recipient_id, p_subject, p_content, FALSE, CURRENT_TIMESTAMP)
  RETURNING message_id INTO p_message_id;

  p_message := 'ok';
END;
$$;

CREATE OR REPLACE FUNCTION sp_create_course(
  p_course_code VARCHAR(50),
  p_course_name VARCHAR(255),
  p_description TEXT,
  p_credit INTEGER,
  p_instructor_id INTEGER,
  p_capacity INTEGER,
  OUT p_course_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM instructors WHERE instructor_id = p_instructor_id) THEN
    p_message := 'instructor_not_found';
    p_course_id := NULL;
    RETURN;
  END IF;

  IF EXISTS (SELECT 1 FROM courses WHERE course_code = p_course_code) THEN
    p_message := 'duplicate_course_code';
    p_course_id := NULL;
    RETURN;
  END IF;

  INSERT INTO courses (course_code, course_name, description, credit, instructor_id, capacity, created_at, updated_at)
  VALUES (p_course_code, p_course_name, p_description, p_credit, p_instructor_id, p_capacity, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
  RETURNING course_id INTO p_course_id;

  p_message := 'ok';
  PERFORM sp_refresh_course_analytics(p_course_id);
END;
$$;

CREATE OR REPLACE FUNCTION sp_create_assessment(
  p_course_id INTEGER,
  p_instructor_id INTEGER,
  p_type assessment_type,
  p_title VARCHAR(255),
  p_description TEXT,
  p_total_points INTEGER,
  p_due_date TIMESTAMP,
  OUT p_assessment_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM courses WHERE course_id = p_course_id AND instructor_id = p_instructor_id) THEN
    p_message := 'not_course_instructor';
    p_assessment_id := NULL;
    RETURN;
  END IF;

  INSERT INTO assessments (course_id, assessment_type, title, description, total_points, due_date, created_at)
  VALUES (p_course_id, p_type, p_title, p_description, p_total_points, p_due_date, CURRENT_TIMESTAMP)
  RETURNING assessment_id INTO p_assessment_id;

  p_message := 'ok';
END;
$$;

CREATE OR REPLACE FUNCTION sp_create_module(
  p_course_id INTEGER,
  p_instructor_id INTEGER,
  p_module_title VARCHAR(255),
  p_description TEXT,
  OUT p_module_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
  v_next_module_number INTEGER;
BEGIN
  IF NOT EXISTS (SELECT 1 FROM courses WHERE course_id = p_course_id AND instructor_id = p_instructor_id) THEN
    p_message := 'not_course_instructor';
    p_module_id := NULL;
    RETURN;
  END IF;

  PERFORM pg_advisory_xact_lock(p_course_id);

  SELECT COALESCE(MAX(module_number), 0) + 1 INTO v_next_module_number FROM modules WHERE course_id = p_course_id;

  INSERT INTO modules (course_id, module_number, module_title, description, created_at)
  VALUES (p_course_id, v_next_module_number, p_module_title, p_description, CURRENT_TIMESTAMP)
  RETURNING module_id INTO p_module_id;

  p_message := 'ok';
END;
$$;

CREATE OR REPLACE FUNCTION sp_create_material(
  p_module_id INTEGER,
  p_instructor_id INTEGER,
  p_material_title VARCHAR(255),
  p_description TEXT,
  p_file_name VARCHAR(255),
  p_file_path VARCHAR(512),
  p_file_type VARCHAR(64),
  OUT p_material_id INTEGER,
  OUT p_message TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
  v_course INTEGER;
BEGIN
  SELECT course_id INTO v_course FROM modules WHERE module_id = p_module_id;
  IF v_course IS NULL THEN
    p_message := 'module_not_found';
    p_material_id := NULL;
    RETURN;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM courses WHERE course_id = v_course AND instructor_id = p_instructor_id) THEN
    p_message := 'not_course_instructor';
    p_material_id := NULL;
    RETURN;
  END IF;

  INSERT INTO materials (module_id, material_title, description, file_name, file_path, file_type, created_at, upload_date)
  VALUES (p_module_id, p_material_title, p_description, p_file_name, p_file_path, p_file_type, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
  RETURNING material_id INTO p_material_id;

  p_message := 'ok';
END;
$$;
