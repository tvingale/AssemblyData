<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/admin_auth.php';

$baseUrl = '..';
requireAdminAuth();

$db = getDB();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $key => $value) {
        setSetting($key, trim($value));
    }
    $message = 'Settings saved successfully.';
    $messageType = 'success';
}

$allSettings = getAllSettings();

$pageTitle = 'Settings';
$baseUrl = '..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Global Settings</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Setting</th>
                        <th>Description</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allSettings as $s): ?>
                    <tr>
                        <td><code><?= h($s['setting_key']) ?></code></td>
                        <td><?= h($s['description']) ?></td>
                        <td>
                            <?php if (strpos($s['setting_key'], '_start') !== false || strpos($s['setting_key'], '_end') !== false): ?>
                                <input type="time" name="settings[<?= h($s['setting_key']) ?>]" value="<?= h($s['setting_value']) ?>">
                            <?php else: ?>
                                <input type="text" name="settings[<?= h($s['setting_key']) ?>]" value="<?= h($s['setting_value']) ?>" style="width:200px">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-group" style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
