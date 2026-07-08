<?php
// Ek level piche ja kar controller aur database ko dhundo
require_once '../controllers/AuthController.php'; 
// Note: AuthController ke andar database.php ka path bhi check karein (woh bhi ../ hona chahiye)

$error = '';
$success = '';

$error = '';
if (isset($_POST['admin_login'])) {
    $error = handleAdminLogin($conn);
}

if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

$page_title = 'Login - School Management System';

// Yahan paths ko ../ ke saath theek kiya gaya hai
include '../includes/header.php'; 
?>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <i class="fas fa-graduation-cap"></i>
            <h2>Welcome Back</h2>
            <p>Login to your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                    placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" class="form-control"
                    placeholder="Enter your password" required>
            </div>

            <button type="submit" name="admin_login" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        <!-- <div class="login-footer" style="text-align: center; margin-top: 20px;">
            <p style="color: var(--text-light); font-size: 0.9rem;">
                Don't have an account? 
                <a href="register.php" style="color: var(--secondary-color); font-weight: 600; text-decoration: none;">Register Now</a>
            </p>
        </div> -->
    </div>
</div>

<?php // include 'includes/footer.php'; ?>