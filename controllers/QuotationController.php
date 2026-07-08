<?php
session_start();
require_once '../config/database.php';
require_once '../includes/crypto.php';

// Helper Function: Add 2 working days (Skipping Sunday)
// Helper Function: 2 Working Days ka poora GAP (Sunday skip karke)
function getQuotationDate($startDate)
{
    $date = new DateTime($startDate);
    $gapDaysCount = 0;

    // Humein 2 din ka poora gap chahiye
    while ($gapDaysCount < 2) {
        $date->modify('+1 day');
        // Agar Sunday (7) nahi hai, toh isay gap day count karo
        if ($date->format('N') != 7) {
            $gapDaysCount++;
        }
    }

    // 2 gap days poore hone ke baad agla din quotation date hogi
    $date->modify('+1 day');

    // Agar agla din bhi Sunday nikal aaye (rare case but safe side), toh ek aur din agay
    if ($date->format('N') == 7) {
        $date->modify('+1 day');
    }

    return $date->format('Y-m-d');
}

if (isset($_GET['action']) && $_GET['action'] == 'process_quotation') {

    $school_id = mysqli_real_escape_string($conn, $_GET['school_id']);
    $requisition_id = isset($_GET['requisition_id']) ? intval($_GET['requisition_id']) : 0;
    $user_id = $_SESSION['user_id'] ?? 1;


    // 1. Requisition check (req_date bhi select kar li)
    $req = mysqli_query($conn, "SELECT id, head_id, req_date FROM tbl_requisition WHERE school_id = '$school_id' AND id = '$requisition_id' AND status = 'Active' ORDER BY id DESC LIMIT 1");
    $req_data = mysqli_fetch_assoc($req);

    if (!$req_data) {
        die("<script>alert('No active requisitions!'); window.history.back();</script>");
    }

    $r_id = $req_data['id'];
    $h_id = $req_data['head_id'];
    $r_date = $req_data['req_date'];

    // --- Naya Step: Quotation Date Calculate karein (2 days margin, skip Sunday) ---
    $q_date = getQuotationDate($r_date);

    // 2. Check if already exists
    $already = mysqli_query($conn, "SELECT id FROM tbl_quotation WHERE requisition_id = '$r_id'");
    if ($old = mysqli_fetch_assoc($already)) {
        header("Location: ../views/comparative_statement.php?id=" . urlencode(encrypt_id($old['id'])));
        exit();
    }

    // 3. Winner Vendor dhoondein
    $winner_query = mysqli_query($conn, "SELECT v.id as vendor_id FROM tbl_vendor v 
                        JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id 
                        WHERE vit.item_type_id = '$h_id' AND v.win_status = 'Winner' AND v.status = 'Active' LIMIT 1");
    $winner_data = mysqli_fetch_assoc($winner_query);
    $winner_id = $winner_data ? $winner_data['vendor_id'] : 'NULL';

    // 4. Insert Main Quotation (With Date and Winner ID)
    $q_insert = "INSERT INTO tbl_quotation (school_id, head_id, requisition_id, winner_vendor_id, quotation_date, created_by) 
                 VALUES ('$school_id', '$h_id', '$r_id', $winner_id, '$q_date', '$user_id')";
    mysqli_query($conn, $q_insert);
    $q_id = mysqli_insert_id($conn);

    // 5. Baaki process (Vendors and Items Loop) wahi rahega...
    $other_vendors_query = mysqli_query($conn, "SELECT v.id as vendor_id, v.win_status FROM tbl_vendor v 
                                                JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id 
                                                WHERE vit.item_type_id = '$h_id' AND (v.win_status != 'Winner' OR v.win_status IS NULL) AND v.status = 'Active' LIMIT 2");
    $other_vendors = mysqli_fetch_all($other_vendors_query, MYSQLI_ASSOC);

    $all_vendors = [];
    if ($winner_data) $all_vendors[] = ['vendor_id' => $winner_id, 'win_status' => 'Winner'];
    foreach ($other_vendors as $ov) $all_vendors[] = $ov;

    $items = mysqli_query($conn, "SELECT rd.item_id, rd.quantity, i.min_price, i.max_price FROM tbl_requisition_details rd JOIN tbl_item i ON rd.item_id = i.id WHERE rd.requisition_id = '$r_id'");

    while ($item = mysqli_fetch_assoc($items)) {
        // Har item ke liye counter reset hoga taake non-winners ko logic sahi milay
        $others_counter = 1;

        foreach ($all_vendors as $v) {
            $min = $item['min_price'];
            $max = $item['max_price'];

            if ($v['win_status'] == 'Winner') {
                // 1. Winner ko direct min price
                $price = round($min);
            } else {
                // Non-winners ke liye check ke kon sa number hai
                if ($others_counter == 1) {
                    // 2. Second Vendor: Center amount
                    $price = round(($min + $max) / 2);
                    $others_counter++;
                } else {
                    // 3. Third Vendor (ya baaqi): Max price
                    $price = round($max);
                }
            }

            mysqli_query($conn, "INSERT INTO tbl_quotation_details (quotation_id, vendor_id, item_id, price, quantity) 
                             VALUES ('$q_id', '{$v['vendor_id']}', '{$item['item_id']}', '$price', '{$item['quantity']}')");
        }
    }

    header("Location: ../views/comparative_statement.php?id=" . urlencode(encrypt_id($q_id)));
    exit();
}
