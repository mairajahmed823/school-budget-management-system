<?php
// Ek level piche ja kar controller aur database ko dhundo
require_once '../controllers/AuthController.php';
// Note: AuthController ke andar database.php ka path bhi check karein (woh bhi ../ hona chahiye)

$error = '';
$success = '';

$error = handleRegister($conn);

$page_title = 'Login - School Management System';

// Yahan paths ko ../ ke saath theek kiya gaya hai
include '../includes/header.php';
?>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <i class="fas fa-graduation-cap"></i>
            <h2>Register Now</h2>
            <!-- <p>Login to your account</p> -->
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

        <form method="POST" action="register.php" class="register-form">
            <div class="form-group">
                <label><i class="fas fa-user"></i> User Name</label>
                <input type="text" name="user_name" class="form-control" placeholder="Enter Your Name" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Enter Your Email" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter Your Password" required>
            </div>

            <button type="submit" class="btn btn-primary">Register</button>
        </form>
          <div class="login-footer" style="text-align: center; margin-top: 20px;">
            <p style="color: var(--text-light); font-size: 0.9rem;">
                Already have an account? 
                <a href="login.php" style="color: var(--secondary-color); font-weight: 600; text-decoration: none;">Login Now</a>
            </p>
        </div>
    </div>
</div>

<?php // include 'includes/footer.php'; 
?>