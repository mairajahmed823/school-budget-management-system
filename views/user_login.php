<?php
require_once '../controllers/UserAuthController.php';
$error = handleUserLogin($conn);

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

        <form method="POST" action="user_login.php">
            <div class="form-group">
                <label><i class="fas fa-university"></i> Username</label>
                <input type="text" name="school_code" class="form-control" placeholder="Enter Username" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="user_login" class="btn btn-success">Institute Login</button>
        </form>

        <div class="login-footer" style="text-align: center; margin-top: 20px;">
            <p style="color: var(--text-light); font-size: 0.9rem;">
               <a href="https://api.whatsapp.com/send?phone=0923162638673&text=Hello%20I%20need%20help"
   target="_blank"
   rel="noopener noreferrer"
   style="text-decoration: none; font-size: 16px;">
    
   <i class="fa-brands fa-whatsapp fa-2x" style="color: green; margin-right: 5px;"></i>
   Contact Us on WhatsApp
</a>
            </p>
        </div>

        <!-- <div class="login-footer" style="text-align: center; margin-top: 20px;">
            <p style="color: var(--text-light); font-size: 0.9rem;">
                Don't have an account? 
                <a href="register.php" style="color: var(--secondary-color); font-weight: 600; text-decoration: none;">Register Now</a>
            </p>
        </div> -->
    </div>
</div>

<?php // include 'includes/footer.php'; 
?>