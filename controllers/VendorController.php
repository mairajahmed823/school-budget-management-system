<?php
// controllers/VendorController.php

// Function to Save New Vendor
function saveVendor($conn)
{
    if (isset($_POST['save'])) {
        $vendor_name    = mysqli_real_escape_string($conn, $_POST['vendor_name']);
        $vendor_no      = mysqli_real_escape_string($conn, $_POST['vendor_no']); // Added this
        $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
        $phone_no       = mysqli_real_escape_string($conn, $_POST['phone_no']);
        $description    = mysqli_real_escape_string($conn, $_POST['description']);
        $address        = mysqli_real_escape_string($conn, $_POST['address']);
        $u_id           = $_SESSION['user_id'];
        $date           = date('Y-m-d H:i:s');

        $win_status = isset($_POST['win_status']) ? "'" . mysqli_real_escape_string($conn, $_POST['win_status']) . "'" : "NULL";

        // Letterhead PDF Upload Logic
        $vendor_letterhead = "";
        if (!empty($_FILES['vendor_letterhead']['name'])) {
            $target_dir = "../uploads/vendor_letterheads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = strtolower(pathinfo($_FILES["vendor_letterhead"]["name"], PATHINFO_EXTENSION));

            if ($file_ext == "pdf") {
                $vendor_letterhead = "LH_" . time() . "_" . basename($_FILES["vendor_letterhead"]["name"]);
                move_uploaded_file($_FILES["vendor_letterhead"]["tmp_name"], $target_dir . $vendor_letterhead);
            }else {
                die("Only PDF allowed for Vendor Letterhead");
            }
        }

        $vendor_logo = "";
        if (!empty($_FILES['vendor_logo']['name'])) {
            $target_dir_logo = "../uploads/vendor_logos/";
            if (!is_dir($target_dir_logo)) {
                mkdir($target_dir_logo, 0777, true);
            }

            $logo_ext = strtolower(pathinfo($_FILES["vendor_logo"]["name"], PATHINFO_EXTENSION));
            $allowed_exts = array("jpg", "jpeg", "png");

            if (in_array($logo_ext, $allowed_exts)) {
                $vendor_logo = "LOGO_" . time() . "_" . basename($_FILES["vendor_logo"]["name"]);
                move_uploaded_file($_FILES["vendor_logo"]["tmp_name"], $target_dir_logo . $vendor_logo);
            }else {
                die("Only JPG, JPEG, PNG allowed for Vendor Logo");
            }
        }
        // Updated SQL Query (Added vendor_no)
        $sql = "INSERT INTO tbl_vendor (vendor_name, vendor_no, vendor_letterhead, vendor_logo, contact_person, phone_no, description, address, status, created_on, created_by, win_status) 
                VALUES ('$vendor_name', '$vendor_no', '$vendor_letterhead', '$vendor_logo', '$contact_person', '$phone_no', '$description', '$address', 'Active', '$date', '$u_id', $win_status)";

        if (mysqli_query($conn, $sql)) {
            $vendor_id = mysqli_insert_id($conn);

            if (isset($_POST['item_type_ids']) && is_array($_POST['item_type_ids'])) {
                foreach ($_POST['item_type_ids'] as $item_id) {
                    $item_id = mysqli_real_escape_string($conn, $item_id);
                    mysqli_query($conn, "INSERT INTO tbl_vendor_item_type (vendor_id, item_type_id) VALUES ('$vendor_id', '$item_id')");
                }
            }
            header("Location: manage_vendors.php?msg=success");
            exit();
        } else {
            die("Error: " . mysqli_error($conn));
        }
    }
}

// Function to Update Existing Vendor
function updateVendor($conn, $current_letterhead, $current_logo)
{
    if (isset($_POST['update'])) {
        $id             = mysqli_real_escape_string($conn, $_GET['id']);
        $vendor_name    = mysqli_real_escape_string($conn, $_POST['vendor_name']);
        $vendor_no      = mysqli_real_escape_string($conn, $_POST['vendor_no']); // Added this
        $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
        $phone_no       = mysqli_real_escape_string($conn, $_POST['phone_no']);
        $description    = mysqli_real_escape_string($conn, $_POST['description']);
        $address        = mysqli_real_escape_string($conn, $_POST['address']);
        $status         = mysqli_real_escape_string($conn, $_POST['status']);
        $u_id           = $_SESSION['user_id'];
        $now            = date('Y-m-d H:i:s');

        // PDF Letterhead Update Logic
        $vendor_letterhead = $current_letterhead;
        if (!empty($_FILES['vendor_letterhead']['name'])) {
            if (!is_dir(LETTERHEAD_URL)) {
                mkdir(LETTERHEAD_URL, 0777, true);
            }
            // extension check
            $ext = strtolower(pathinfo($_FILES["vendor_letterhead"]["name"], PATHINFO_EXTENSION));

            if ($ext != "pdf") {
                die("Only PDF allowed for Vendor Letterhead");
            }
            $vendor_letterhead = "LH_" . time() . "_" . basename($_FILES["vendor_letterhead"]["name"]);
            move_uploaded_file($_FILES["vendor_letterhead"]["tmp_name"], LETTERHEAD_URL . $vendor_letterhead);
        }

        $vendor_logo = $current_logo;
        if (!empty($_FILES['vendor_logo']['name'])) {
            $target_dir_logo = "../uploads/vendor_logos/";
            if (!is_dir($target_dir_logo)) {
                mkdir($target_dir_logo, 0777, true);
            }

            $logo_ext = strtolower(pathinfo($_FILES["vendor_logo"]["name"], PATHINFO_EXTENSION));
            if (in_array($logo_ext, ['jpg', 'jpeg', 'png'])) {
                $vendor_logo = "LOGO_" . time() . "_" . basename($_FILES["vendor_logo"]["name"]);
                move_uploaded_file($_FILES["vendor_logo"]["tmp_name"], $target_dir_logo . $vendor_logo);
            }else {
                die("Only JPG, JPEG, PNG allowed for Vendor Logo");
            }
        }

        $win_status = isset($_POST['win_status']) ? "'" . mysqli_real_escape_string($conn, $_POST['win_status']) . "'" : "NULL";

        // Updated SQL Query with vendor_no
        $update_sql = "UPDATE tbl_vendor SET 
                       vendor_name = '$vendor_name', 
                       vendor_no = '$vendor_no',
                       vendor_letterhead = '$vendor_letterhead',
                       vendor_logo = '$vendor_logo',
                       contact_person = '$contact_person', 
                       phone_no = '$phone_no', 
                       description = '$description', 
                       address = '$address', 
                       status = '$status', 
                       updated_on = '$now', 
                       updated_by = '$u_id',
                       win_status = $win_status 
                       WHERE id = '$id'";

        if (mysqli_query($conn, $update_sql)) {
            // Update item types (pivot table)
            mysqli_query($conn, "DELETE FROM tbl_vendor_item_type WHERE vendor_id = '$id'");
            if (isset($_POST['item_type_ids']) && is_array($_POST['item_type_ids'])) {
                foreach ($_POST['item_type_ids'] as $item_id) {
                    $item_id = mysqli_real_escape_string($conn, $item_id);
                    mysqli_query($conn, "INSERT INTO tbl_vendor_item_type (vendor_id, item_type_id) VALUES ('$id', '$item_id')");
                }
            }
            echo "<script>alert('Vendor Updated Successfully!'); window.location.href='manage_vendors.php';</script>";
            exit();
        }
    }
}
