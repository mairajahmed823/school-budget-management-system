<?php
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    // Move the user into the views folder
    header('Location: views/manage_school.php');
} else {
    header('Location: views/user_login.php');
}
exit();
?>