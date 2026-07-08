<?php
// controllers/SchoolController.php
require_once '../includes/crypto.php';
// 1. Add School Logic
if (isset($_POST['save'])) {
    $district    = mysqli_real_escape_string($conn, $_POST['district']);
    $semis_code  = mysqli_real_escape_string($conn, $_POST['semis_code']);
    $school_code = mysqli_real_escape_string($conn, $_POST['school_code']);
    $school_name = mysqli_real_escape_string($conn, $_POST['school_name']);
    $students    = mysqli_real_escape_string($conn, $_POST['no_of_students']);
    $enrollment  = mysqli_real_escape_string($conn, $_POST['enrollment']);
    $address     = mysqli_real_escape_string($conn, $_POST['school_address']);
    $acronym     = mysqli_real_escape_string($conn, $_POST['acronym']);
    $demand_no   = mysqli_real_escape_string($conn, $_POST['demand_no']);
    $section     = mysqli_real_escape_string($conn, $_POST['section']);
    $u_id        = $_SESSION['user_id'];

    // Folder check (Removed letterhead folder check)
    if (!is_dir(SIGN_URL)) mkdir(SIGN_URL, 0777, true);
    if (!is_dir(STAMP_URL)) mkdir(STAMP_URL, 0777, true);
    if (!is_dir(LOGO_URL)) mkdir(LOGO_URL, 0777, true);

    $allowed = ['jpg', 'jpeg', 'png'];

    // ================= SCHOOL LOGO =================
    if (!empty($_FILES["school_logo"]["name"])) {
        $logo_ext = strtolower(pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($logo_ext, $allowed)) {
            die("Only JPG, JPEG, PNG allowed for School Logo");
        }
    }

    // ================= PRINCIPAL SIGNATURE =================
    if (!empty($_FILES["principal_signature"]["name"])) {
        $sig_ext = strtolower(pathinfo($_FILES['principal_signature']['name'], PATHINFO_EXTENSION));
        if (!in_array($sig_ext, $allowed)) {
            die("Only JPG, JPEG, PNG allowed for Principal Signature");
        }
    }

    // ================= SCHOOL STAMP =================
    if (!empty($_FILES["school_stamp"]["name"])) {
        $stamp_ext = strtolower(pathinfo($_FILES['school_stamp']['name'], PATHINFO_EXTENSION));
        if (!in_array($stamp_ext, $allowed)) {
            die("Only JPG, JPEG, PNG allowed for School Stamp");
        }
    }

    $sig_file   = time() . "_sig_" . basename($_FILES["principal_signature"]["name"]);
    $stamp_file = time() . "_stamp_" . basename($_FILES["school_stamp"]["name"]);
    $logo_file  = time() . "_logo_" . basename($_FILES["school_logo"]["name"]);

    move_uploaded_file($_FILES["school_logo"]["tmp_name"], LOGO_URL . $logo_file);
    move_uploaded_file($_FILES["principal_signature"]["tmp_name"], SIGN_URL . $sig_file);
    move_uploaded_file($_FILES["school_stamp"]["tmp_name"], STAMP_URL . $stamp_file);

    // SQL Query Updated (Removed letter_head, Added demand_no)
    $sql = "INSERT INTO tbl_manage_school 
        (district, semis_code, school_code, school_name, acronym, section, demand_no, no_of_students, enrollment, principal_signature, school_stamp, school_logo, school_address, status, created_by) 
        VALUES 
        ('$district', '$semis_code', '$school_code', '$school_name', '$acronym', '$section', '$demand_no', '$students', '$enrollment', '$sig_file', '$stamp_file', '$logo_file', '$address', 'Active', '$u_id')";



    if (mysqli_query($conn, $sql)) {

        // Naye school ki ID uthao (agar reference ke liye chahiye ho)
        $new_school_id = mysqli_insert_id($conn);

        $plain_password = $school_code;
        $password = password_hash($plain_password, PASSWORD_DEFAULT);
        $username = $school_code;

        $sql_user = "INSERT INTO tbl_users (email, password, role, school_id, status) 
                     VALUES ('$username', '$password', 'user', '$new_school_id', 'Active')";

        if (mysqli_query($conn, $sql_user)) {
            header("Location: manage_school.php?msg=success");
            exit();
        } else {
            die("User Account Creation Error: " . mysqli_error($conn));
        }
    } else {
        die("Insert Error: " . mysqli_error($conn));
    }
}

// 2. Update School Logic
if (isset($_POST['update'])) {
    $id = decrypt_id($_GET['id']);
    $district    = mysqli_real_escape_string($conn, $_POST['district']);
    $semis_code  = mysqli_real_escape_string($conn, $_POST['semis_code']);
    $school_code = mysqli_real_escape_string($conn, $_POST['school_code']); // Added this
    $school_name = mysqli_real_escape_string($conn, $_POST['school_name']);
    $students    = mysqli_real_escape_string($conn, $_POST['no_of_students']);
    $enrollment  = mysqli_real_escape_string($conn, $_POST['enrollment']);
    $address     = mysqli_real_escape_string($conn, $_POST['school_address']);
    $status      = mysqli_real_escape_string($conn, $_POST['status']);
    $acronym     = mysqli_real_escape_string($conn, $_POST['acronym']);
    $demand_no   = mysqli_real_escape_string($conn, $_POST['demand_no']);
    $section = mysqli_real_escape_string($conn, $_POST['section']);
    $password    = $_POST['password']; // Password field uthain

    // Get old data for files
    $query = mysqli_query($conn, "SELECT * FROM tbl_manage_school WHERE id = '$id'");
    $data = mysqli_fetch_assoc($query);

    $sig_file = $data['principal_signature'];
    $stamp_file = $data['school_stamp'];
    $logo_file = $data['school_logo'];

    $allowed = ['jpg', 'jpeg', 'png'];
    // Handle File Uploads (Only if new files are selected)
    if (!empty($_FILES["school_logo"]["name"])) {
        $ext = strtolower(pathinfo($_FILES['school_logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            die("Only JPG, JPEG, PNG allowed for School Logo");
        }
        $logo_file = time() . "_logo_" . basename($_FILES["school_logo"]["name"]);
        move_uploaded_file($_FILES["school_logo"]["tmp_name"], LOGO_URL . $logo_file);
    }
    if (!empty($_FILES["principal_signature"]["name"])) {
        $ext = strtolower(pathinfo($_FILES['principal_signature']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            die("Only JPG, JPEG, PNG allowed for Principal Signature");
        }
        $sig_file = time() . "_sig_" . basename($_FILES["principal_signature"]["name"]);
        move_uploaded_file($_FILES["principal_signature"]["tmp_name"], SIGN_URL . $sig_file);
    }
    if (!empty($_FILES["school_stamp"]["name"])) {
        $ext = strtolower(pathinfo($_FILES['school_stamp']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            die("Only JPG, JPEG, PNG allowed for School Stamp");
        }
        $stamp_file = time() . "_stamp_" . basename($_FILES["school_stamp"]["name"]);
        move_uploaded_file($_FILES["school_stamp"]["tmp_name"], STAMP_URL . $stamp_file);
    }

    // Updated SQL Query with school_code
    $sql = "UPDATE tbl_manage_school SET 
        district='$district', 
        semis_code='$semis_code', 
        school_code='$school_code', 
        school_name='$school_name',
        acronym='$acronym',
        demand_no='$demand_no',
        no_of_students='$students',
        enrollment='$enrollment', 
        principal_signature='$sig_file', 
        section = '$section',
        school_stamp='$stamp_file', 
        school_logo='$logo_file',
        school_address='$address',
        status='$status'
        WHERE id='$id'";

    if (mysqli_query($conn, $sql)) {
        $checkUser = mysqli_query($conn, "SELECT * FROM tbl_users WHERE email = '$school_code'");

        if (mysqli_num_rows($checkUser) > 0) {
            // User maujood hai: Agar password field bhari hai toh update karo
            if (!empty($password)) {
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE tbl_users SET password = '$hashedPass' WHERE email = '$school_code'");
            }
        } else {
            // User maujood nahi hai: Naya user banao
            // Default: Email = school_code, Password = school_code
            $defaultPass = password_hash($school_code, PASSWORD_DEFAULT);
            $role = 'user'; // ya jo bhi aapka default role hai

            mysqli_query($conn, "INSERT INTO tbl_users (email, password, role, school_id) 
                             VALUES ('$school_code', '$defaultPass', '$role', '$id')");
        }
        header("Location: manage_school.php?msg=updated");
        exit();
    } else {
        die("Update Error: " . mysqli_error($conn));
    }
}
