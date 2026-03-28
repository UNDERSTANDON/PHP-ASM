# PHP-ASM unit test plan

This document defines **40** unit tests for the LMS codebase. Tests are grouped by target module. Pure functions (`lms_pg_bool`, `php_asm_url`, `auth_validate_register_inputs`) are ideal for fast PHPUnit unit tests. Functions that call `get_pdo()` or manipulate `$_SESSION` / `$_POST` should use doubles, process isolation, or be classified as **integration** tests when run against a real database.

**Suggested stack:** PHPUnit 10+, PHP 8.1+ (matches `declare(strict_types=1)` usage).

**Conventions:** Each test has a stable ID (`UT-xxx`) for traceability to tickets and CI reports.

---

## 1. URL and configuration (`config/database.php`)

| ID | Test name | Description | Expected outcome |
|----|-------------|-------------|------------------|
| UT-001 | `php_asm_url_appends_path` | Call `php_asm_url('login.php')` with default `PHP_ASM_BASE` | Returns `PHP_ASM_BASE` + `/` + `login.php` (no duplicate slashes) |
| UT-002 | `php_asm_url_strips_leading_slashes` | Call `php_asm_url('/dashboard.php')` | Path is normalized; result equals base + `dashboard.php` |
| UT-003 | `php_asm_url_empty_path` | Call `php_asm_url('')` or `php_asm_url('/')` | Resolves to base URL with a single trailing slash behavior as implemented (document actual vs desired) |

---

## 2. PostgreSQL boolean coercion (`includes/lms_functions.php` — `lms_pg_bool`)

| ID | Test name | Input | Expected |
|----|-------------|-------|----------|
| UT-004 | `lms_pg_bool_true_literal` | `true` | `true` |
| UT-005 | `lms_pg_bool_false_literal` | `false` | `false` |
| UT-006 | `lms_pg_bool_int_one` | `1` | `true` |
| UT-007 | `lms_pg_bool_int_zero` | `0` | `false` |
| UT-008 | `lms_pg_bool_null` | `null` | `false` |
| UT-009 | `lms_pg_bool_empty_string` | `''` | `false` |
| UT-010 | `lms_pg_bool_string_t_uppercase` | `'T'` | `true` |
| UT-011 | `lms_pg_bool_string_t_lowercase` | `'t'` | `true` |
| UT-012 | `lms_pg_bool_string_one` | `'1'` | `true` |
| UT-013 | `lms_pg_bool_string_f` | `'f'` | `false` |
| UT-014 | `lms_pg_bool_other_nonempty_string` | e.g. `'yes'` | Per implementation: `(bool)` cast of non-`t`/`1` strings — assert actual behavior to lock regressions |
| UT-015 | `lms_pg_bool_unexpected_type` | e.g. `[]` or `2.5` | Assert stable coercion (document whether this case should be tightened) |

---

## 3. POST helpers (`index.php` — `post_int`, `post_str`, `post_opt_str`)

*Run with `$_POST` populated in test setup (or extract helpers to a small `includes/request.php` for cleaner testing).*

| ID | Test name | Setup | Expected |
|----|-------------|-------|----------|
| UT-016 | `post_int_missing_key_uses_default` | `$_POST = []`, `post_int('x', 7)` | `7` |
| UT-017 | `post_int_empty_string_uses_default` | `$_POST['k'] = ''`, `post_int('k', 3)` | `3` |
| UT-018 | `post_int_numeric_string` | `$_POST['id'] = '42'` | `42` |
| UT-019 | `post_int_non_numeric_string` | `$_POST['id'] = 'abc'` | `(int)` coercion result (`0` in PHP) |

| ID | Test name | Setup | Expected |
|----|-------------|-------|----------|
| UT-020 | `post_str_missing_key` | unset key | `''` |
| UT-021 | `post_str_trims` | `$_POST['s'] = '  hi  '` | `'hi'` |

| ID | Test name | Setup | Expected |
|----|-------------|-------|----------|
| UT-022 | `post_opt_str_empty_becomes_null` | `''` or whitespace-only after trim | `null` |
| UT-023 | `post_opt_str_non_empty` | `'value'` | `'value'` |
| UT-024 | `post_opt_str_missing_key` | no key | `null` (via `post_str` returning `''`) |

---

## 4. Registration validation (`includes/auth.php` — `auth_validate_register_inputs`)

| ID | Test name | Inputs (abbrev.) | Expected |
|----|-------------|------------------|----------|
| UT-025 | `validate_empty_name` | `name = ''` | Error containing display name requirement |
| UT-026 | `validate_empty_email` | `email = ''` | Error for valid email |
| UT-027 | `validate_invalid_email` | malformed string | Error for valid email |
| UT-028 | `validate_short_password` | password length `< AUTH_MIN_PASSWORD_LEN` | Error for password length |
| UT-029 | `validate_password_mismatch` | confirm ≠ password | Passwords do not match |
| UT-030 | `validate_invalid_role` | role not in `student`/`instructor`/`admin` | Invalid role |
| UT-031 | `validate_student_requires_enrollment` | role `student`, empty enrollment | Enrollment number required |
| UT-032 | `validate_student_whitespace_enrollment` | role `student`, enrollment only spaces | Same as empty (trim) |
| UT-033 | `validate_student_ok` | valid student fields | `[]` |
| UT-034 | `validate_instructor_ok` | valid instructor fields, enrollment can be empty | `[]` |
| UT-035 | `validate_admin_ok` | valid admin fields | `[]` |
| UT-036 | `validate_multiple_errors` | several invalid fields | Array with multiple messages, order documented |

---

## 5. Session-backed auth (`includes/auth.php` — `auth_user`)

*Prefer a test harness that resets `$_SESSION` or uses PHPUnit session extension; alternatively stub `session_status()` behavior.*

| ID | Test name | Session state | Expected |
|----|-------------|---------------|----------|
| UT-037 | `auth_user_empty_session` | No `user_id` / `role` | `null` |
| UT-038 | `auth_user_missing_role` | `user_id` set, `role` missing | `null` |
| UT-039 | `auth_user_complete` | All keys set (`user_id`, `user_name`, `user_email`, `role`) | Associative array with correct types (`int` id, `string` fields) |
| UT-040 | `auth_user_numeric_string_ids` | `user_id` as string `"5"` in session | Cast to `int` `5` in returned array |

---

## Coverage summary

| Area | Test IDs | Count |
|------|-----------|-------|
| `php_asm_url` | UT-001–UT-003 | 3 |
| `lms_pg_bool` | UT-004–UT-015 | 12 |
| POST helpers | UT-016–UT-024 | 9 |
| `auth_validate_register_inputs` | UT-025–UT-036 | 12 |
| `auth_user` | UT-037–UT-040 | 4 |
| **Total** | | **40** |

---

## Out of scope for this unit plan (recommended follow-ups)

These are valuable but depend on **PDO mocks**, **database fixtures**, or **HTTP/runtime** integration:

- `get_pdo()` connectivity and DSN construction (smoke / integration).
- `auth_login`, `auth_register`, `auth_logout`, `auth_require_login`, `auth_require_roles` (redirects, HTTP codes, DB side effects).
- All `lms_fn_*` and `lms_sp_*` wrappers (assert SQL binding and response mapping with a test double or staging DB).
- `layout_*` output (snapshot or DOM assertions in integration tests).

---

## Implementation checklist

1. Add `composer.json` with `phpunit/phpunit` (dev) and a `phpunit.xml` pointing at `test/`.
2. For UT-016–UT-024, either require `index.php` functions in a bootstrap that defines only helpers (avoid executing page logic) or move POST helpers to a dedicated include.
3. Run UT-001–UT-003 with `putenv` / `define` for `PHP_ASM_BASE` in a separate process or test-specific bootstrap so constants do not clash.
4. Tag DB-dependent tests `@group integration` when you add them later.