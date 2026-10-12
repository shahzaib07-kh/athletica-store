<?php
/**
 * ATHLETICA — Admin Login
 *
 * Authenticates an admin against the `admins` table using
 * PDO prepared statements and password_verify().
 *
 * Security measures:
 *   - Prepared statements prevent SQL injection
 *   - password_verify() checks bcrypt/argon2 hashes
 *   - session_regenerate_id() prevents session fixation
 *   - CSRF token prevents cross-site request forgery
 *   - Generic error messages prevent user enumeration
 *   - Deactivated accounts are rejected
 */

// ============================================================
// SESSION START (same secure config as auth.php)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

// ============================================================
// ALREADY LOGGED IN? REDIRECT TO DASHBOARD
// ============================================================
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

// ============================================================
// CSRF TOKEN (generate once per session)
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// PROCESS LOGIN FORM
// ============================================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CSRF Verification ---
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $error = 'Invalid request. Please reload the page and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // --- Input Validation ---
        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } else {
            // --- Load Database Connection ---
            require __DIR__ . '/../config/database.php';

            // --- Query Admin by Email (prepared statement) ---
            $stmt = $pdo->prepare(
                'SELECT id, email, password_hash, full_name, is_active
                 FROM admins
                 WHERE email = :email
                 LIMIT 1'
            );
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            // --- Verify Password ---
            if ($admin && password_verify($password, $admin['password_hash'])) {

                // Check if the account is active
                if (!$admin['is_active']) {
                    $error = 'This account has been deactivated.';
                } else {
                    // ✅ Authentication successful

                    // Regenerate session ID to prevent session fixation attacks
                    session_regenerate_id(true);

                    // Store minimal admin identity in session (never the password)
                    $_SESSION['admin_id']    = (int) $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name']  = $admin['full_name'];

                    // Update last_login_at timestamp
                    $update = $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
                    $update->execute([':id' => $admin['id']]);

                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                // Generic error — do NOT reveal whether the email exists
                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — ATHLETICA</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f0f;
            color: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-container {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }

        .brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 4px;
            color: #ffffff;
            margin-bottom: 6px;
        }

        .brand p {
            font-size: 10px;
            letter-spacing: 3px;
            color: #666;
            text-transform: uppercase;
        }

        .brand .divider {
            width: 40px;
            height: 2px;
            background: #ffffff;
            margin: 16px auto;
        }

        .brand .subtitle {
            font-size: 13px;
            letter-spacing: 1px;
            color: #888;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #999;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            background: #111;
            border: 1px solid #333;
            border-radius: 4px;
            color: #e0e0e0;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            border-color: #555;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: #ffffff;
            color: #000000;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-login:hover {
            background: #e0e0e0;
        }

        .error-message {
            background: #1c0a0a;
            border: 1px solid #4a1515;
            color: #e74c3c;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="brand">
            <h1>ATHLETICA</h1>
            <p>Focus &nbsp;|&nbsp; Power &nbsp;|&nbsp; Performance</p>
            <div class="divider"></div>
            <p class="subtitle">Admin Panel</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="admin@athletica.pk"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>
</body>
</html>
