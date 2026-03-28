<?php

declare(strict_types=1);

/**
 * PDO (PostgreSQL) for database php_asm.
 *
 * Env: PHP_ASM_DB_HOST, PHP_ASM_DB_PORT (default 5432), PHP_ASM_DB_NAME,
 *      PHP_ASM_DB_USER, PHP_ASM_DB_PASS
 */
function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('PHP_ASM_DB_HOST') ?: '127.0.0.1';
    $name = getenv('PHP_ASM_DB_NAME') ?: 'PHP-ASM';
    $user = getenv('PHP_ASM_DB_USER') ?: 'postgres';
    $pass = getenv('PHP_ASM_DB_PASS') ?: '1234';
    $port = getenv('PHP_ASM_DB_PORT') ?: '5432';

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;client_encoding=UTF8',
        $host,
        $port,
        $name
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

/** Web path prefix for links/redirects (e.g. /PHP-ASM). Override with env PHP_ASM_BASE. */
if (!defined('PHP_ASM_BASE')) {
    define('PHP_ASM_BASE', rtrim((string) (getenv('PHP_ASM_BASE') ?: '/PHP-ASM'), '/'));
}

function php_asm_url(string $path): string
{
    return PHP_ASM_BASE . '/' . ltrim($path, '/');
}
