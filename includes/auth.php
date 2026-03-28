<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

const AUTH_MIN_PASSWORD_LEN = 8;

function auth_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * @return array{user_id: int, user_name: string, user_email: string, role: string}|null
 */
function auth_user(): ?array
{
    auth_start_session();
    if (empty($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return null;
    }

    return [
        'user_id'    => (int) $_SESSION['user_id'],
        'user_name'  => (string) $_SESSION['user_name'],
        'user_email' => (string) $_SESSION['user_email'],
        'role'       => (string) $_SESSION['role'],
    ];
}

function auth_require_login(): void
{
    if (auth_user() === null) {
        header('Location: ' . php_asm_url('login.php'), true, 302);
        exit;
    }
}

/**
 * @param list<string> $roles Allowed user_role values
 */
function auth_require_roles(array $roles): void
{
    auth_require_login();
    $u = auth_user();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        echo 'Forbidden for your role.';
        exit;
    }
}

function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * @return true|string true on success, error message string on failure
 */
function auth_login(string $email, string $password): bool|string
{
    $email = trim($email);
    if ($email === '' || $password === '') {
        return 'Email and password are required.';
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare(
        'SELECT user_id, user_name, user_email, password_hash, role::text AS role
         FROM users WHERE LOWER(user_email) = LOWER(:email) LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();
    if ($row === false || !password_verify($password, $row['password_hash'])) {
        return 'Invalid email or password.';
    }

    auth_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $row['user_id'];
    $_SESSION['user_name']  = $row['user_name'];
    $_SESSION['user_email'] = $row['user_email'];
    $_SESSION['role']       = $row['role'];

    return true;
}

/**
 * @return list<string> empty if valid
 */
function auth_validate_register_inputs(
    string $name,
    string $email,
    string $password,
    string $passwordConfirm,
    string $role,
    string $enrollmentNumber
): array {
    $errors = [];
    if ($name === '') {
        $errors[] = 'Display name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if (strlen($password) < AUTH_MIN_PASSWORD_LEN) {
        $errors[] = 'Password must be at least ' . AUTH_MIN_PASSWORD_LEN . ' characters.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!in_array($role, ['student', 'instructor', 'admin'], true)) {
        $errors[] = 'Invalid role.';
    }
    if ($role === 'student' && trim($enrollmentNumber) === '') {
        $errors[] = 'Student enrollment number is required.';
    }

    return $errors;
}

/**
 * @return array{ok: true, user_id: int}|array{ok: false, errors: list<string>}
 */
function auth_register(
    string $name,
    string $email,
    string $password,
    string $role,
    ?string $enrollmentNumber,
    ?string $major,
    ?string $department,
    ?string $bio
): array {
    $name    = trim($name);
    $email   = trim($email);
    $major   = $major !== null && trim($major) !== '' ? trim($major) : null;
    $dept    = $department !== null && trim($department) !== '' ? trim($department) : null;
    $bio     = $bio !== null && trim($bio) !== '' ? trim($bio) : null;
    $enr     = $enrollmentNumber !== null ? trim($enrollmentNumber) : '';

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        return ['ok' => false, 'errors' => ['Could not hash password.']];
    }

    $pdo = get_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (user_name, user_email, password_hash, role)
             VALUES (:name, :email, :hash, CAST(:role AS user_role))
             RETURNING user_id'
        );
        $stmt->execute([
            'name'  => $name,
            'email' => $email,
            'hash'  => $hash,
            'role'  => $role,
        ]);
        $userId = (int) $stmt->fetchColumn();

        if ($role === 'student') {
            $st = $pdo->prepare(
                'INSERT INTO students (user_id, enrollment_number, major)
                 VALUES (:uid, :enr, :major)'
            );
            $st->execute(['uid' => $userId, 'enr' => $enr, 'major' => $major]);
        } elseif ($role === 'instructor') {
            $ins = $pdo->prepare(
                'INSERT INTO instructors (user_id, bio, department)
                 VALUES (:uid, :bio, :dept)'
            );
            $ins->execute(['uid' => $userId, 'bio' => $bio, 'dept' => $dept]);
        }
        // admin: only users row

        $pdo->commit();
        return ['ok' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (($e->errorInfo[0] ?? '') === '23505') {
            return ['ok' => false, 'errors' => ['That email or enrollment number is already registered.']];
        }
        return ['ok' => false, 'errors' => ['Registration failed: ' . $e->getMessage()]];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'errors' => ['Registration failed: ' . $e->getMessage()]];
    }
}
