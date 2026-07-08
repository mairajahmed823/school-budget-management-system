<?php
require_once '../config/database.php';
require_once '../controllers/AuthController.php';
checkUserAuth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $user_id = $_SESSION['user_id'];
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['confirm_password'];

    if (empty($new_pass) || empty($conf_pass)) {
        $error = "Both fields are required!";
    } elseif ($new_pass !== $conf_pass) {
        $error = "Passwords do not match!";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        // Password ko hash karna zaroori hai safety ke liye
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

        $sql = "UPDATE tbl_users SET password = '$hashed_password' WHERE id = '$user_id'";

        if (mysqli_query($conn, $sql)) {
            $success = "Password updated successfully!";
        } else {
            $error = "Error updating password: " . mysqli_error($conn);
        }
    }
}

$page_title = 'Change Password';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="header-row">
        <h2 class="page-title">Security Settings</h2>
    </div>

    <div class="table-container" style="max-width: 500px; margin: 20px auto; padding: 30px;">
        <h3>Change Password</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
            Update your account password to stay secure.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="padding: 10px; margin-bottom: 15px; background: #fee2e2; color: #dc2626; border-radius: 5px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" style="padding: 10px; margin-bottom: 15px; background: #dcfce7; color: #16a34a; border-radius: 5px;">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>

            <button type="submit" name="update_password" class="btn-add" style="width: 100%; padding: 12px; border: none; cursor: pointer;">
                Update Password
            </button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>