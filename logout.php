<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

auth_logout();
header('Location: ' . php_asm_url('login.php'), true, 302);
exit;
