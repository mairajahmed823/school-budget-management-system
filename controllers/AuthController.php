<?php

require_once __DIR__ . '/../config/database.php';

function handleAdminLogin($conn)
{

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['admin_login'])) return '';
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = trim($_POST['password']);

    $query = "SELECT * FROM tbl_users WHERE email = '$email' AND role = 'admin' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password']) || $password == $user['password']) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['role']      = $user['ROLE'];

            header('Location: manage_school.php');
            exit();
        }
    }
    return 'Invalid Admin Credentials';
}

// Helper function to avoid code repetition
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


// function handleRegister($conn) {
//     if ($_SERVER['REQUEST_METHOD'] !== 'POST') return '';

//     $user_name = mysqli_real_escape_string($conn, $_POST['user_name']);
//     $email     = mysqli_real_escape_string($conn, $_POST['email']);
//     $password  = mysqli_real_escape_string($conn, $_POST['password']);


//     $checkEmail = mysqli_query($conn, "SELECT id FROM tbl_users WHERE email = '$email'");
//     if (mysqli_num_rows($checkEmail) > 0) {
//         return "Email already exists!";
//     }


//     $sql = "INSERT INTO tbl_users (user_name, email, password, status) 
//             VALUES ('$user_name', '$email', '$password', 'Active')";

//     if (mysqli_query($conn, $sql)) {
//         header('Location: login.php?success=Registered successfully');
//         exit();
//     } else {
//         return "Error: " . mysqli_error($conn);
//     }
// }