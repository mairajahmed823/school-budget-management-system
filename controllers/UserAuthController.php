<?php
require_once __DIR__ . '/../config/database.php';

function handleUserLogin($conn)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_login'])) return '';

    $school_code = mysqli_real_escape_string($conn, trim($_POST['school_code']));
    $password = trim($_POST['password']);

    $query = "SELECT * FROM tbl_users WHERE email = '$school_code' AND role = 'user' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password']) || $password == $user['password']) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['school_id'] = $user['school_id'];
            $_SESSION['email']     = $user['email'];

            header('Location: manage_school.php');
            exit();
        }
    }
    return 'Invalid School Code or Password';
}

function startUserSession($user)
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['user_name'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['school_id'] = $user['school_id'];
}

function checkUserAuth()
{

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}
