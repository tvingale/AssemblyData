<?php
/**
 * Simple password protection for admin pages.
 * Stores the password hash in a setting.
 * Uses PHP sessions to persist login state.
 */
session_start();

// Default admin password (hashed). Change via Settings page or directly in DB.
define('ADMIN_DEFAULT_PASSWORD', 'admin@123');

function getAdminPasswordHash(): string {
    try {
        $hash = getSetting('admin_password_hash', '');
        if ($hash === '') {
            // First-time: store default password hash
            $hash = password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_DEFAULT);
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES ('admin_password_hash', ?, 'Hashed admin password') ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$hash, $hash]);
        }
        return $hash;
    } catch (Exception $e) {
        // DB not yet set up — use default
        return password_hash(ADMIN_DEFAULT_PASSWORD, PASSWORD_DEFAULT);
    }
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

function handleAdminLogout(): void {
    if (isset($_GET['logout'])) {
        unset($_SESSION['admin_authenticated']);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}

/**
 * Call this at the top of admin pages (after includes are loaded).
 * Returns true if authenticated, otherwise shows login form and exits.
 */
function requireAdminAuth(): bool {
    handleAdminLogout();

    $loginError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password_submit'])) {
        $password = $_POST['admin_password'] ?? '';
        $hash = getAdminPasswordHash();
        if (password_verify($password, $hash)) {
            $_SESSION['admin_authenticated'] = true;
            // Redirect to same page (GET) to avoid form resubmission
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $loginError = 'Incorrect password.';
        }
    }

    if (!isAdminLoggedIn()) {
        showAdminLoginForm($loginError);
        exit;
    }

    return true;
}

function showAdminLoginForm(string $error = ''): void {
    global $baseUrl;
    $bu = $baseUrl ?? '..';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $bu ?>/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand"><a href="<?= $bu ?>/index.php"><?= APP_NAME ?></a></div>
    </nav>
    <main class="container" style="max-width:400px; margin-top:3rem;">
        <div class="card">
            <div class="card-header">Admin Login</div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="admin_password" required autofocus>
                </div>
                <div class="form-group">
                    <button type="submit" name="admin_password_submit" value="1" class="btn btn-primary" style="width:100%">Login</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
<?php
}
