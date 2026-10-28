<?php
require_once 'db.php';

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: admin.php');
    exit;
}

function loginPostString(string $key): ?string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : null;
}

function loginBindParams(mysqli_stmt $stmt, string $types, array $values): bool {
    $references = [];
    foreach (array_keys($values) as $key) {
        $references[$key] = &$values[$key];
    }
    return $stmt->bind_param($types, ...$references);
}

function loginIsBlocked(mysqli $conn, string $username, string $ipHash): bool {
    $checks = [
        ['admin_login_attempts', 'username = ? AND ip_hash = ?', 'ss', [$username, $ipHash]],
        ['admin_login_ip_attempts', 'ip_hash = ?', 's', [$ipHash]],
    ];

    foreach ($checks as [$table, $where, $types, $values]) {
        try {
            $stmt = $conn->prepare("SELECT blocked_until FROM {$table} WHERE {$where} LIMIT 1");
            if (!$stmt) {
                error_log("Login throttle query preparation failed for {$table}: " . $conn->error);
                return true;
            }
            loginBindParams($stmt, $types, $values);
            if (!$stmt->execute()) {
                error_log("Login throttle query failed for {$table}: " . $stmt->error);
                $stmt->close();
                return true;
            }
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && $row['blocked_until'] !== null && strtotime($row['blocked_until']) > time()) {
                return true;
            }
        } catch (Throwable $e) {
            error_log('Login throttle check failed: ' . $e->getMessage());
            return true;
        }
    }

    return false;
}

function loginRecordFailure(mysqli $conn, string $username, string $ipHash): void {
    $windowMinutes = LOGIN_RATE_WINDOW_MINUTES;
    $blockMinutes = LOGIN_BLOCK_MINUTES;
    $records = [
        [
            'table' => 'admin_login_attempts',
            'where' => 'username = ? AND ip_hash = ?',
            'select_types' => 'ss',
            'select_values' => [$username, $ipHash],
            'limit' => LOGIN_RATE_LIMIT,
            'insert_sql' => 'INSERT INTO admin_login_attempts (username, ip_hash, attempts, window_started_at, blocked_until) VALUES (?, ?, ?, ?, ?)',
            'insert_types' => 'ssiss',
            'insert_values' => [$username, $ipHash],
            'update_sql' => 'UPDATE admin_login_attempts SET attempts = ?, window_started_at = ?, blocked_until = ? WHERE username = ? AND ip_hash = ?',
            'update_types' => 'issss',
            'update_values' => [$username, $ipHash],
        ],
        [
            'table' => 'admin_login_ip_attempts',
            'where' => 'ip_hash = ?',
            'select_types' => 's',
            'select_values' => [$ipHash],
            'limit' => LOGIN_IP_RATE_LIMIT,
            'insert_sql' => 'INSERT INTO admin_login_ip_attempts (ip_hash, attempts, window_started_at, blocked_until) VALUES (?, ?, ?, ?)',
            'insert_types' => 'siss',
            'insert_values' => [$ipHash],
            'update_sql' => 'UPDATE admin_login_ip_attempts SET attempts = ?, window_started_at = ?, blocked_until = ? WHERE ip_hash = ?',
            'update_types' => 'isss',
            'update_values' => [$ipHash],
        ],
    ];

    foreach ($records as $record) {
        try {
            $stmt = $conn->prepare("SELECT attempts, window_started_at FROM {$record['table']} WHERE {$record['where']} LIMIT 1");
            if (!$stmt) {
                error_log("Login throttle read preparation failed for {$record['table']}: " . $conn->error);
                continue;
            }
            loginBindParams($stmt, $record['select_types'], $record['select_values']);
            if (!$stmt->execute()) {
                error_log("Login throttle read failed for {$record['table']}: " . $stmt->error);
                $stmt->close();
                continue;
            }
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $now = time();
            $windowStart = $now;
            $attempts = 1;
            if ($existing && strtotime($existing['window_started_at']) >= ($now - ($windowMinutes * 60))) {
                $attempts = (int) $existing['attempts'] + 1;
                $windowStart = strtotime($existing['window_started_at']);
            }
            $windowStartSql = date('Y-m-d H:i:s', $windowStart);
            $blockedUntil = $attempts >= $record['limit']
                ? date('Y-m-d H:i:s', $now + ($blockMinutes * 60))
                : null;

            if ($existing) {
                $stmt = $conn->prepare($record['update_sql']);
                if (!$stmt) {
                    error_log("Login throttle update preparation failed for {$record['table']}: " . $conn->error);
                    continue;
                }
                $values = [$attempts, $windowStartSql, $blockedUntil, ...$record['update_values']];
                loginBindParams($stmt, $record['update_types'], $values);
            } else {
                $stmt = $conn->prepare($record['insert_sql']);
                if (!$stmt) {
                    error_log("Login throttle insert preparation failed for {$record['table']}: " . $conn->error);
                    continue;
                }
                $values = [...$record['insert_values'], $attempts, $windowStartSql, $blockedUntil];
                // The IP-only table has one fewer value than the username+IP table.
                if ($record['table'] === 'admin_login_ip_attempts') {
                    $values = [$ipHash, $attempts, $windowStartSql, $blockedUntil];
                }
                loginBindParams($stmt, $record['insert_types'], $values);
            }

            if (!$stmt->execute()) {
                error_log("Login throttle write failed for {$record['table']}: " . $stmt->error);
            }
            $stmt->close();
        } catch (Throwable $e) {
            error_log('Login throttle update failed: ' . $e->getMessage());
        }
    }
}

function loginClearFailures(mysqli $conn, string $username, string $ipHash): void {
    foreach ([
        ['DELETE FROM admin_login_attempts WHERE username = ? AND ip_hash = ?', 'ss', [$username, $ipHash]],
        ['DELETE FROM admin_login_ip_attempts WHERE ip_hash = ?', 's', [$ipHash]],
    ] as [$sql, $types, $values]) {
        try {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                loginBindParams($stmt, $types, $values);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('Unable to clear login throttle record: ' . $e->getMessage());
        }
    }
}

$conn = getDB();
try {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM admin_users");
    $count = (int) $countResult->fetch_assoc()['total'];
} catch (Throwable $e) {
    error_log('Admin count query failed: ' . $e->getMessage());
    $conn->close();
    http_response_code(500);
    exit('Unable to load the login page right now.');
}

$error = '';
$tokenFromConfig = defined('ADMIN_SETUP_TOKEN') ? constant('ADMIN_SETUP_TOKEN') : '';
$setupTokenConfigured = is_string($tokenFromConfig) && trim($tokenFromConfig) !== '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrfToken = loginPostString('csrf_token');
    $action = loginPostString('action');
    $username = loginPostString('username');
    $password = loginPostString('password');

    if (!verifyCsrfToken($csrfToken ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif ($count === 0 && $action === 'setup') {
        $setupToken = loginPostString('setup_token');
        $username = $username === null ? '' : trim($username);
        $password = $password ?? '';
        $email = loginPostString('email');
        $email = $email === null ? '' : trim($email);

        if (!$setupTokenConfigured || $setupToken === null || !hash_equals((string) $tokenFromConfig, $setupToken)) {
            $error = 'Administrator setup is not enabled or the setup token is invalid.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
            $error = 'Username must be 3-100 characters and contain only letters, numbers, dot, underscore, or hyphen.';
        } elseif (strlen($password) < 12) {
            $error = 'Password must be at least 12 characters.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
                if (!$stmt) {
                    error_log('Admin setup query preparation failed: ' . $conn->error);
                    $error = 'Unable to create the administrator account.';
                } else {
                    $stmt->bind_param('sss', $username, $hash, $email);
                    if ($stmt->execute()) {
                        session_regenerate_id(true);
                        $_SESSION['admin_user_id'] = (int) $conn->insert_id;
                        $_SESSION['admin_username'] = $username;
                        header('Location: admin.php');
                        exit;
                    }
                    error_log('Admin setup query failed: ' . $stmt->error);
                    $error = 'Unable to create the administrator account.';
                    $stmt->close();
                }
            } catch (Throwable $e) {
                error_log('Admin setup failed: ' . $e->getMessage());
                $error = 'Unable to create the administrator account.';
            }
        }
    } elseif ($count > 0 && $action === 'login') {
        $username = $username === null ? '' : trim($username);
        $password = $password ?? '';
        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } else {
            $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            if (loginIsBlocked($conn, $username, $ipHash)) {
                $error = 'Too many failed sign-in attempts. Please try again later.';
            } else {
                $user = null;
                try {
                    $stmt = $conn->prepare("SELECT id, username, password FROM admin_users WHERE username = ? LIMIT 1");
                    if (!$stmt) {
                        error_log('Admin login query preparation failed: ' . $conn->error);
                        $error = 'Unable to sign in right now. Please try again.';
                    } else {
                        $stmt->bind_param('s', $username);
                        if (!$stmt->execute()) {
                            error_log('Admin login query failed: ' . $stmt->error);
                            $error = 'Unable to sign in right now. Please try again.';
                        } else {
                            $user = $stmt->get_result()->fetch_assoc();
                        }
                        $stmt->close();
                    }
                } catch (Throwable $e) {
                    error_log('Admin login query failed: ' . $e->getMessage());
                    $error = 'Unable to sign in right now. Please try again.';
                }

                if ($error === '') {
                    $dummyHash = '$2y$12$wmilnbsPv4gbNrEBbMsY8OyRFjjrd5qUo4cplNMoH/vjLpidmVsw6';
                    if ($user && password_verify($password, $user['password'])) {
                        loginClearFailures($conn, $username, $ipHash);
                        session_regenerate_id(true);
                        $_SESSION['admin_user_id'] = (int) $user['id'];
                        $_SESSION['admin_username'] = $user['username'];
                        header('Location: admin.php');
                        exit;
                    }

                    password_verify($password, $user['password'] ?? $dummyHash);
                    loginRecordFailure($conn, $username, $ipHash);
                    $error = 'Invalid username or password.';
                }
            }
        }
    } else {
        $error = 'Invalid request. Please try again.';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — <?= htmlspecialchars(COMPANY_NAME, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="admin-body">
<div class="admin-container admin-login-wrap">
    <div class="admin-login-card">
        <div class="admin-header admin-header--center">
            <div>
                <div class="admin-login-mark">🔐</div>
                <h1 class="admin-title">Admin <?= $count === 0 ? 'Setup' : 'Login' ?></h1>
                <p class="admin-subtitle">
                    <?= $count === 0 ? 'Create the first administrator account.' : 'Sign in to manage invoices.' ?>
                </p>
            </div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="<?= $count === 0 ? 'setup' : 'login' ?>">

            <?php if ($count === 0): ?>
            <div class="form-group">
                <label for="setup_token">Setup token</label>
                <div class="input-wrap">
                    <span class="input-icon">🔐</span>
                    <input id="setup_token" name="setup_token" type="password" required autocomplete="off">
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <span class="input-icon">👤</span>
                    <input id="username" name="username" type="text" required maxlength="100" autocomplete="username">
                </div>
            </div>

            <?php if ($count === 0): ?>
            <div class="form-group">
                <label for="email">Email (optional)</label>
                <div class="input-wrap">
                    <span class="input-icon">✉</span>
                    <input id="email" name="email" type="email" maxlength="200" autocomplete="email">
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="password">Password<?= $count === 0 ? ' (12+ characters)' : '' ?></label>
                <div class="input-wrap">
                    <span class="input-icon">🔑</span>
                    <input id="password" name="password" type="password" required autocomplete="<?= $count === 0 ? 'new-password' : 'current-password' ?>">
                </div>
            </div>

            <div class="form-actions">
                <a href="index.php" class="btn-secondary">← Back</a>
                <button type="submit" class="btn-primary"><?= $count === 0 ? 'Create Admin' : 'Sign In' ?></button>
            </div>
        </form>
    </div>
</div>
</body>
</html>