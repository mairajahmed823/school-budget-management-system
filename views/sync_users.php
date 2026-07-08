<?php
require_once '../config/database.php';
require_once '../controllers/AuthController.php';
checkUserAuth();


// 1. Un schools ko uthao jinka record tbl_users mein nahi hay
$query = "SELECT id, school_code FROM tbl_manage_school 
          WHERE id NOT IN (SELECT school_id FROM tbl_users WHERE school_id IS NOT NULL)";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    $count = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $school_id   = $row['id'];
        $school_code = $row['school_code'];
        
        // 2. Password Hash aur Username set karein (Wahi logic jo aapne di thi)
        $username = mysqli_real_escape_string($conn, $school_code);
        $password = password_hash($school_code, PASSWORD_DEFAULT);
        
        // 3. tbl_users mein insert karein
        $insert_sql = "INSERT INTO tbl_users (email, password, role, school_id, status) 
                       VALUES ('$username', '$password', 'user', '$school_id', 'Active')";
        
        if (mysqli_query($conn, $insert_sql)) {
            $count++;
        } else {
            echo "Error for School ID $school_id: " . mysqli_error($conn) . "<br>";
        }
    }
    
    echo "<h3>Success! $count new schools' data synced to tbl_users.</h3>";
} else {
    echo "<h3>Data missing</h3>";
}
?>