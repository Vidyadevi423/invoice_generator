<?php
require_once 'db.php';

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: admin.php');
    exit;
}

$conn = getDB();
$countResult = $conn->query("SELECT COUNT(*) AS total FROM admin_users");
if (!$countResult) {
    error_log('Admin count query failed: ' . $conn->error);
    $conn->close();
    http_response_code(500);
    exit('Unable to load the login page right now.');
}
$count = (int) $countResult->fetch_assoc()['total'];
$error = '';
$setupTokenConfigured = getenv('ADMIN_SETUP_TOKEN') !== false && getenv('ADMIN_SETUP_TOKEN') !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif ($count === 0 && ($_POST['action'] ?? '') === 'setup') {
        $setupToken = $_POST['setup_token'] ?? '';

        if (!$setupTokenConfigured || !hash_equals((string) getenv('ADMIN_SETUP_TOKEN'), (string) $setupToken)) {
            $error = 'Administrator setup is not enabled or the setup token is invalid.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $email = trim($_POST['email'] ?? '');

            if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
                $error = 'Username must be 3-100 characters and contain only letters, numbers, dot, underscore, or hyphen.';
            } elseif (strlen($password) < 12) {
                $error = 'Password must be at least 12 characters.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a valid email address.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
                if (!$stmt) {
                    error_log('Admin setup query preparation failed: ' . $conn->error);
                    $error = 'Unable to create the administrator account.';
                } else {
                    $stmt->bind_param('sss', $username, $hash, $email);
                }
                if ($stmt && $stmt->execute()) {
                    session_regenerate_id(true);
                    $_SESSION['admin_user_id'] = $conn->insert_id;
                    $_SESSION['admin_username'] = $username;
                    header('Location: admin.php');
                    exit;
                }
                $error = 'Unable to create the administrator account.';
                if ($stmt) {
                    $stmt->close();
                }
            }
        }
    } elseif ($count > 0 && ($_POST['action'] ?? '') === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $attemptStmt = $conn->prepare(
            "SELECT attempts, window_started_at, blocked_until
             FROM admin_login_attempts
             WHERE username = ?
             LIMIT 1"
        );

        $blocked = false;
        if ($attemptStmt) {
            $attemptStmt->bind_param('s', $username);
            if ($attemptStmt->execute()) {
                $attempt = $attemptStmt->get_result()->fetch_assoc();
                if ($attempt && $attempt['blocked_until'] !== null && strtotime($attempt['blocked_until']) > time()) {
                    $blocked = true;
                }
            } else {
                error_log('Login throttle query failed: ' . $attemptStmt->error);
            }
            $attemptStmt->close();
        } else {
            error_log('Login throttle query preparation failed: ' . $conn->error);
        }

        if ($blocked) {
            $error = 'Too many failed sign-in attempts. Please try again later.';
        } else {
            $user = null;
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

            if ($error === '') {
                if ($user && password_verify($password, $user['password'])) {
                    $clearStmt = $conn->prepare("DELETE FROM admin_login_attempts WHERE username = ?");
                    if ($clearStmt) {
                        $clearStmt->bind_param('s', $username);
                        $clearStmt->execute();
                        $clearStmt->close();
                    }

                    session_regenerate_id(true);
                    $_SESSION['admin_user_id'] = (int) $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    header('Location: admin.php');
                    exit;
                }

                $recordStmt = $conn->prepare(
                    "SELECT attempts, window_started_at
                     FROM admin_login_attempts
                     WHERE username = ?
                     LIMIT 1"
                );
                $attempts = 0;
                $windowStarted = null;
                $recordExists = false;

                if ($recordStmt) {
                    $recordStmt->bind_param('s', $username);
                    if ($recordStmt->execute()) {
                        $record = $recordStmt->get_result()->fetch_assoc();
                        if ($record) {
                            $recordExists = true;
                            if (strtotime($record['window_started_at']) >= strtotime('-15 minutes')) {
                                $attempts = (int) $record['attempts'];
                                $windowStarted = $record['window_started_at'];
                            }
                        }
                    }
                    $recordStmt->close();
                }

                $attempts++;
                $blockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;

                if (!$recordExists) {
                    $upsertStmt = $conn->prepare(
                        "INSERT INTO admin_login_attempts (username, attempts, window_started_at, blocked_until)
                         VALUES (?, ?, NOW(), ?)"
                    );
                    if ($upsertStmt) {
                        $upsertStmt->bind_param('sis', $username, $attempts, $blockedUntil);
                    }
                } else {
                    $upsertStmt = $conn->prepare(
                        "UPDATE admin_login_attempts
                         SET attempts = ?, blocked_until = ?
                         WHERE username = ?"
                    );
                    if ($upsertStmt) {
                        $upsertStmt->bind_param('iss', $attempts, $blockedUntil, $username);
                    }
                }

                if (isset($upsertStmt) && $upsertStmt) {
                    if (!$upsertStmt->execute()) {
                        error_log('Login throttle update failed: ' . $upsertStmt->error);
                    }
                    $upsertStmt->close();
                }

                $error = 'Invalid username or password.';
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — <?= htmlspecialchars(COMPANY_NAME) ?></title>
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

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
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
