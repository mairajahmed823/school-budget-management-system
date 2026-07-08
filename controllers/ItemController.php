<?php
// controllers/ItemController.php

// Function to Save New Item
function saveItem($conn)
{
    if (isset($_POST['save_item'])) {
        $item_name   = mysqli_real_escape_string($conn, $_POST['item_name']);
        // $min_price   = mysqli_real_escape_string($conn, $_POST['min_price']);
        // $max_price   = mysqli_real_escape_string($conn, $_POST['max_price']);
        $ip_address  = $_SERVER['REMOTE_ADDR'];
        $date        = date('Y-m-d H:i:s');

        $role = $_SESSION['role'] ?? 'user';

        if ($role === 'admin') {
            $min_price = mysqli_real_escape_string($conn, $_POST['min_price']);
            $max_price = mysqli_real_escape_string($conn, $_POST['max_price']);
            $item_category = mysqli_real_escape_string($conn, $_POST['item_category']);
            $u_id        = $_SESSION['user_id'];
            $percent     = mysqli_real_escape_string($conn, $_POST['percent_of_budget']);
        } else {
            // User input price
            $input_price = floatval($_POST['user_price']);
            $min_price = $input_price;

            // Max price = Input Price + 10%
            $max_price = $input_price + ($input_price * 0.10);
            $item_category = "Secondary";
            $u_id        = $_SESSION['school_id'];
            $percent = 0;
        }

        $sql_item = "INSERT INTO tbl_item (item_name, min_price, max_price, percent_of_budget, item_category, ip_address, created_by, created_on, STATUS) 
                     VALUES ('$item_name', '$min_price', '$max_price', '$percent', '$item_category', '$ip_address', '$u_id', '$date', 'Active')";

        if (mysqli_query($conn, $sql_item)) {
            $last_item_id = mysqli_insert_id($conn);

            // Important: Handle both Single and Multi Select
            if (isset($_POST['head_ids'])) {
                // Agar single value hai toh usay array mein convert kar do
                $head_ids = is_array($_POST['head_ids']) ? $_POST['head_ids'] : [$_POST['head_ids']];

                foreach ($head_ids as $head_id) {
                    $head_id = mysqli_real_escape_string($conn, $head_id);
                    if (!empty($head_id)) {
                        $sql_pivot = "INSERT INTO tbl_item_head_type (item_id, head_type_id) 
                                      VALUES ('$last_item_id', '$head_id')";
                        mysqli_query($conn, $sql_pivot);
                    }
                }
            }
            header("Location: manage_items.php?msg=success");
            exit();
        } else {
            die("Error: " . mysqli_error($conn));
        }
    }
}

// Function to Update Existing Item
function updateItem($conn)
{
    if (isset($_POST['update_item'])) {
        $item_id   = mysqli_real_escape_string($conn, $_GET['id']);
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        // $min_price = mysqli_real_escape_string($conn, $_POST['min_price']);
        // $max_price = mysqli_real_escape_string($conn, $_POST['max_price']);
        // $percent   = mysqli_real_escape_string($conn, $_POST['percent_of_budget']);
        $status    = mysqli_real_escape_string($conn, $_POST['status']);
        // $item_category = mysqli_real_escape_string($conn, $_POST['item_category']);
        // $u_id      = $_SESSION['user_id'];
        $date      = date('Y-m-d H:i:s');

          $role = $_SESSION['role'] ?? 'user';

        if ($role === 'admin') {
            $min_price = mysqli_real_escape_string($conn, $_POST['min_price']);
            $max_price = mysqli_real_escape_string($conn, $_POST['max_price']);
            $item_category = mysqli_real_escape_string($conn, $_POST['item_category']);
            $u_id        = $_SESSION['user_id'];
            $percent     = mysqli_real_escape_string($conn, $_POST['percent_of_budget']);
        } else {
            // User input price
            $input_price = floatval($_POST['user_price']);
            $min_price = $input_price;

            // Max price = Input Price + 10%
            $max_price = $input_price + ($input_price * 0.10);
            $item_category = "Secondary";
            $u_id        = $_SESSION['school_id'];
            $percent = 0;
        }


        $update_sql = "UPDATE tbl_item SET 
                        item_name = '$item_name', 
                        min_price = '$min_price', 
                        max_price = '$max_price', 
                        percent_of_budget = '$percent',
                        item_category = '$item_category',
                        status = '$status',
                        updated_by = '$u_id',
                        updated_on = '$date'
                       WHERE id = '$item_id'";

        if (mysqli_query($conn, $update_sql)) {
            mysqli_query($conn, "DELETE FROM tbl_item_head_type WHERE item_id = '$item_id'");

            if (isset($_POST['head_ids']) && is_array($_POST['head_ids'])) {
                foreach ($_POST['head_ids'] as $h_id) {
                    $h_id = mysqli_real_escape_string($conn, $h_id);
                    mysqli_query($conn, "INSERT INTO tbl_item_head_type (item_id, head_type_id) VALUES ('$item_id', '$h_id')");
                }
            }
            echo "<script>alert('Item Updated Successfully!'); window.location.href='manage_items.php';</script>";
            exit();
        } else {
            die("Update Error: " . mysqli_error($conn));
        }
    }
}
