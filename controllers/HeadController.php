<?php
// controllers/HeadController.php

// Function to Save New Head
function saveHead($conn)
{
    if (isset($_POST['save'])) {
        // $main_head_id = mysqli_real_escape_string($conn, $_POST['main_head_id']);
        $h_name = mysqli_real_escape_string($conn, $_POST['head_name']);
        $h_code = mysqli_real_escape_string($conn, $_POST['head_code']);
        $head_category = mysqli_real_escape_string($conn, $_POST['head_category']);
        $u_id = $_SESSION['user_id'];
        $date = date('Y-m-d H:i:s');

        // Insert into tbl_heads (Added head_category)
        $sql = "INSERT INTO tbl_heads (head_name, code_no, head_category, status, created_on, created_by) 
                VALUES ('$h_name', '$h_code', '$head_category', 'Active', '$date', '$u_id')";

        if (mysqli_query($conn, $sql)) {
            $head_id = mysqli_insert_id($conn);

            if (isset($_POST['head_type_ids']) && is_array($_POST['head_type_ids'])) {
                foreach ($_POST['head_type_ids'] as $type_id) {
                    $type_id = mysqli_real_escape_string($conn, $type_id);
                    mysqli_query($conn, "INSERT INTO tbl_head_head_types (head_id, head_type_id) VALUES ('$head_id', '$type_id')");
                }
            }
            header("Location: manage_heads.php?msg=success");
            exit();
        } else {
            die("Error: " . mysqli_error($conn));
        }
    }
}
// Function to Update Head
function updateHead($conn)
{
    if (isset($_POST['update'])) {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        $h_name = mysqli_real_escape_string($conn, $_POST['head_name']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $head_category = mysqli_real_escape_string($conn, $_POST['head_category']); // ← NAYA FIELD

        // Update main table with head_category
        $update_sql = "UPDATE tbl_heads SET 
                       head_name = '$h_name', 
                       status = '$status',
                       head_category = '$head_category' 
                       WHERE id = '$id'";

        if (mysqli_query($conn, $update_sql)) {
            mysqli_query($conn, "DELETE FROM tbl_head_head_types WHERE head_id = '$id'");

            if (isset($_POST['head_type_ids']) && is_array($_POST['head_type_ids'])) {
                foreach ($_POST['head_type_ids'] as $type_id) {
                    $type_id = mysqli_real_escape_string($conn, $type_id);
                    mysqli_query($conn, "INSERT INTO tbl_head_head_types (head_id, head_type_id) VALUES ('$id', '$type_id')");
                }
            }
            echo "<script>alert('Record Updated Successfully!'); window.location.href='manage_heads.php';</script>";
            exit();
        } else {
            die("Error: " . mysqli_error($conn));
        }
    }
}
