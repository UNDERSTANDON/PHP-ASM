# PHP-ASM (Learning Management System)

A web-based Learning Management System (LMS) built with PHP and PostgreSQL for managing academic activities. The platform provides a robust set of tools for instructors and students to engage in online learning.

## Core Features

- **Course Management**: Instructors can create and organize courses, upload structured materials (modules, documents), and manage student enrollments.
- **Assessment Tools**: Instructors can initiate quizzes, assignments, and exams. The system tracks student submissions, allowing for comprehensive tracking and numerical grading.
- **Student Interaction**: Built-in forums and internal messaging systems facilitate seamless communication between students and instructors.
- **Analytics Dashboard**: Instructors have access to insights surrounding student performance, progress, tracking metrics such as average scores and completion rates.
- **Role-Based Access Control**: Secure interfaces tailored for **Administrators**, **Instructors**, and **Students**. Strict capabilities and permissions are enforced at the database level to ensure data integrity.

## Technology Stack

- **Backend**: Vanilla PHP
- **Database**: PostgreSQL
  - _Note_: Relies heavily on database-side Stored Procedures (`lms_sp_*`) and Functions (`lms_fn_*`) for enforcing business rules and efficient data manipulation.
- **Environment**: XAMPP stack

## Project Structure

- `index.php`: Core application entry point. Serves dynamic dashboards tailored to the user's role (Student, Instructor, Admin).
- `course_analytics.php`: Dedicated detailed metrics and analytics dashboards for instructors.
- `messages.php`: Standalone direct messaging interface.
- `includes/`: Core business and data logic (`lms_functions.php`, `course_data.php`) along with shared layouts (`layout.php`).
- `config/`: Configuration files (including database configuration details).
- `PHP-ASM.sql` / `Data.sql`: Initial database schema, core stored procedures/functions, and seed data.
- `planning.txt` / `Role.txt`: Documentation regarding the original database ERD, overall requirements, and strict role permissions matrix.

## Getting Started

1. Enable the following extensions in your `php.ini` file to ensure database connectivity: `extension=pdo_pgsql`, `extension=pdo_sqlite`, and `extension=pgsql`.
2. Import `PHP-ASM.sql` into your local PostgreSQL instance to set up the required schema, tables, and complex stored procedures.
3. (Optional) Import `Data.sql` to populate the application with initial seed data.
4. Configure your database connection inside the `config/` directory.
5. Host the repository in your `htdocs` directory and navigate to the project root in your browser (utilizing a standard XAMPP environment).
