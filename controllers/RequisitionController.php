<?php
require_once '../config/database.php';


function getManageRequisitions($conn, $search = [], $limit = 10, $offset = 0)
{
    $where = "WHERE r.status = 'Active'";

    if ($_SESSION['role'] !== 'admin') {
        $s_id = $_SESSION['school_id'];
        $where .= " AND s.id = '$s_id'";
    }

    if (!empty($search['school_code'])) {
        $school_code = mysqli_real_escape_string($conn, $search['school_code']);
        $where .= " AND s.school_code LIKE '%$school_code%'";
    }

    if (!empty($search['tenure_id'])) {
        $tenure_id = mysqli_real_escape_string($conn, $search['tenure_id']);
        $where .= " AND r.tenure_id = '$tenure_id'";
    }

    // ✅ STEP 1: TOTAL COUNT QUERY (yahan likhna hai)
    $count_query = "SELECT COUNT(DISTINCT r.id) as total
                    FROM tbl_requisition r
                    JOIN tbl_manage_school s ON r.school_id = s.id
                    $where";

    $count_result = mysqli_query($conn, $count_query);
    $total_rows = mysqli_fetch_assoc($count_result)['total'];

    // ✅ STEP 2: MAIN QUERY (LIMIT + OFFSET)
    $query = "SELECT 
                r.*, 
                s.school_name, 
                s.school_code,
                s.id as school_id,
                h.head_name,
                GROUP_CONCAT(i.item_name SEPARATOR ', ') AS all_items
              FROM tbl_requisition r
              JOIN tbl_manage_school s ON r.school_id = s.id
              JOIN tbl_heads h ON r.head_id = h.id
              JOIN tbl_requisition_details rd ON r.id = rd.requisition_id
              JOIN tbl_item i ON rd.item_id = i.id
              $where
              GROUP BY r.id 
              ORDER BY r.id DESC
              LIMIT $limit OFFSET $offset";

    $data = mysqli_query($conn, $query);

    // ✅ STEP 3: RETURN BOTH
    return [
        'data' => $data,
        'total' => $total_rows
    ];
}

function getTotalRequisitionsCount($conn, $search = [])
{
    $where = "WHERE r.status = 'Active'";
    if (!empty($search['school_code'])) {
        $school_code = mysqli_real_escape_string($conn, $search['school_code']);
        $where .= " AND s.school_code LIKE '%$school_code%'";
    }

    if (!empty($search['tenure_id'])) {
        $tenure_id = mysqli_real_escape_string($conn, $search['tenure_id']);
        $where .= " AND r.tenure_id = '$tenure_id'";
    }

    $query = "SELECT COUNT(DISTINCT r.id) as total FROM tbl_requisition r 
              JOIN tbl_manage_school s ON r.school_id = s.id 
              LEFT JOIN tbl_quotation q ON r.id = q.requisition_id $where";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'];
}

// Dropdowns ke liye initial data
function getInitialDropdowns($conn)
{
    // 1. Schools Query
    $role = $_SESSION['role'] ?? 'user';
    $school_id = $_SESSION['school_id'] ?? 0;

    // 1. Schools Query with Role Condition
    if ($role === 'admin') {
        // Admin ke liye saare schools
        $school_query = "SELECT id, school_name, school_code FROM `tbl_manage_school` WHERE STATUS = 'Active'";
    } else {
        // School User ke liye sirf apna school
        $school_query = "SELECT id, school_name, school_code FROM `tbl_manage_school` WHERE STATUS = 'Active' AND id = '$school_id'";
    }

    $data['schools'] = mysqli_query($conn, $school_query);

    // 2. Head (Item Type) Query
    $data['heads'] = mysqli_query($conn, "SELECT h.head_name, h.id AS h_id, h.code_no FROM tbl_heads AS h
        INNER JOIN tbl_head_head_types AS hht ON h.id = hht.head_id
        INNER JOIN tbl_head_type AS ht ON ht.id = hht.head_type_id
        WHERE ht.type = 's' AND ht.item_type_status = 'Active'");

    return $data;
}


if (isset($_POST['action']) && $_POST['action'] == 'fetch_items') {
    $h_id = mysqli_real_escape_string($conn, $_POST['head_id']);
    $output = '<option value="">-- Choose Item --</option>'; // Pehla option empty lazmi rakhein

    $query = "SELECT i.id, i.item_name FROM tbl_item AS i
              INNER JOIN `tbl_item_head_type` AS it ON i.`id` = it.`item_id`
              INNER JOIN tbl_heads AS h ON it.`head_type_id` = h.`id`
              WHERE i.`status` = 'Active' AND h.`id` = '$h_id'";

    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $output .= '<option value="' . $row['id'] . '">' . $row['item_name'] . '</option>';
    }
    echo $output;
    exit;
}

// RequisitionController.php ke top par

if (isset($_POST['action']) && $_POST['action'] == 'get_item_prices') {
    // Clear any previous output (headers, spaces etc)
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json');

    $item_id = intval($_POST['item_id']);
    $head_id = intval($_POST['head_id']);

    // 1. Item ki min/max price
    $res = mysqli_query($conn, "SELECT min_price, max_price FROM tbl_item WHERE id = '$item_id'");
    $item = mysqli_fetch_assoc($res);

    if (!$item) {
        echo json_encode(['error' => 'Item not found']);
        exit;
    }

    // 2. Winner aur Other Vendors
    $winner = mysqli_query($conn, "SELECT v.id FROM tbl_vendor v JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id WHERE vit.item_type_id = '$head_id' AND v.win_status = 'Winner' LIMIT 1");
    $w_data = mysqli_fetch_assoc($winner);
    $w_id = $w_data['id'] ?? 0;

    $others_res = mysqli_query($conn, "SELECT v.id FROM tbl_vendor v JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id WHERE vit.item_type_id = '$head_id' AND v.win_status != 'Winner' LIMIT 2");

    // Price Logic
    // 1. Winner Price (Ab direct min price hai)
    $win_price = round($item['min_price']);

    $other_prices = [];
    $counter = 1; // Counter 2nd aur 3rd vendor ko pehchane ke liye

    while ($v = mysqli_fetch_assoc($others_res)) {
        $min = $item['min_price'];
        $max = $item['max_price'];

        if ($counter == 1) {
            // 2. Second Vendor: Center amount (Average)
            $calculated_price = round(($min + $max) / 2);
        } else {
            // 3. Third Vendor: Max price
            $calculated_price = round($max);
        }

        $other_prices[] = [
            'vendor_id' => $v['id'],
            'price' => $calculated_price
        ];

        $counter++;
    }

    // Final JSON Response
    echo json_encode([
        'winner_id' => $w_id,
        'winner_price' => $win_price,
        'others' => $other_prices
    ]);
    exit;
}

function saveRequisition($conn)
{
    if (isset($_POST['save_requisition'])) {
        $user_id = $_SESSION['user_id'] ?? 1;
        $school_id = (int)$_POST['school_id'];
        $head_id = (int)$_POST['head_id'];
        $req_date = mysqli_real_escape_string($conn, $_POST['req_date']);
        $current_req_total = floatval($_POST['expected_amount']);

        // 1. Single Requisition Max Limit Check
        if ($current_req_total > 200000) {
            $_SESSION['error'] = "Error: Single Requisition cannot exceed 200,000.";
            header("Location: ../views/add_requisition.php");
            exit;
        }

        // 2. Identify Tenure logic... (Wahi rahega jo aapka tha)
        $ts = strtotime($req_date);
        $m = (int)date('m', $ts);
        $y = (int)date('Y', $ts);
        $tenure = ($m >= 7) ? "$y/" . ($y + 1) : ($y - 1) . "/$y";
        $fiscal_start_date = (($m >= 7) ? $y : ($y - 1)) . "-07-01";

        // Quarter Logic... (Wahi rahega)
        if ($m >= 7 && $m <= 9) {
            $active_quarters = 1;
            $q_e = "$y-09-30";
        } elseif ($m >= 10 && $m <= 12) {
            $active_quarters = 2;
            $q_e = "$y-12-31";
        } elseif ($m >= 1 && $m <= 3) {
            $active_quarters = 3;
            $q_e = "$y-03-31";
        } else {
            $active_quarters = 4;
            $q_e = "$y-06-30";
        }

        // 3. Get Budget Details (Wahi rahega)
        $budget_sql = "SELECT bd.amount FROM tbl_budget_details bd 
                       JOIN tbl_budget b ON bd.budget_id = b.id 
                       JOIN tbl_tenure t ON b.tenure_id = t.id 
                       WHERE b.school_id = $school_id AND bd.head_id = $head_id 
                       AND t.tenure = '$tenure' AND bd.STATUS = 'Active' LIMIT 1";

        $b_res = mysqli_query($conn, $budget_sql);
        $b_row = mysqli_fetch_assoc($b_res);

        if (!$b_row) {
            $_SESSION['error'] = "Error: Budget not assigned for $tenure!";
            header("Location: ../views/add_requisition.php");
            exit;
        }

        // 4. Budget Calculation & Spending check (Wahi rahega)
        $total_allowed_till_now = (floatval($b_row['amount']) / 4) * $active_quarters;
        $spent_sql = "SELECT SUM(qd.price * qd.quantity) as total_spent 
                      FROM tbl_quotation q JOIN tbl_quotation_details qd ON q.id = qd.quotation_id
                      WHERE q.school_id = $school_id AND q.head_id = $head_id 
                      AND q.winner_vendor_id = qd.vendor_id AND q.quotation_date BETWEEN '$fiscal_start_date' AND '$q_e' AND q.status = 'Active'";

        $s_res = mysqli_query($conn, $spent_sql);
        $s_row = mysqli_fetch_assoc($s_res);
        $already_spent_total = floatval($s_row['total_spent'] ?? 0);

        if (($already_spent_total + $current_req_total) > $total_allowed_till_now) {

            $available_balance = $total_allowed_till_now - $already_spent_total;
            $available_balance = $available_balance > 0 ? $available_balance : 0;

            $_SESSION['error'] = "Limit Exceeded! \n" .
                "Total Allowed till Quarter $active_quarters: " . number_format($total_allowed_till_now) . "\n" .
                "Already Spent in this Year: " . number_format($already_spent_total) . "\n" .
                "Current Available (with Carry Forward): " . number_format($available_balance);

            header("Location: ../views/add_requisition.php");
            exit;
        }

        // 5. Get Tenure ID
        $t_res = mysqli_query($conn, "SELECT id FROM tbl_tenure WHERE tenure = '$tenure' LIMIT 1");
        $tenure_id = mysqli_fetch_assoc($t_res)['id'];

        // 6. MAIN INSERTION (YAHAN FIX HAI)
        $desc = mysqli_real_escape_string($conn, $_POST['description']);
        $ins_main = "INSERT INTO tbl_requisition (school_id, head_id, tenure_id, req_date, description, created_by, status) 
                     VALUES ($school_id, $head_id, $tenure_id, '$req_date', '$desc', $user_id, 'Active')";

        if (mysqli_query($conn, $ins_main)) {
            $req_id = mysqli_insert_id($conn);

            // --- OUTWARD NO LOGIC ---
            $sql_max = "SELECT MAX(doc_no) as last_no FROM tbl_outward_no WHERE school_id = $school_id AND tenure_id = $tenure_id";
            $res_max = mysqli_query($conn, $sql_max);
            $new_doc_no = (mysqli_fetch_assoc($res_max)['last_no'] ?? 0) + 1;

            mysqli_query($conn, "INSERT INTO tbl_outward_no (school_id, tenure_id, requisition_id, doc_no) 
                                 VALUES ($school_id, $tenure_id, $req_id, $new_doc_no)");

            // --- ITEMS INSERTION ---
            foreach ($_POST['items'] as $i => $item_id) {
                if (!empty($item_id)) {
                    $qty = (int)$_POST['qty'][$i];
                    mysqli_query($conn, "INSERT INTO tbl_requisition_details (requisition_id, item_id, quantity, created_by) 
                                         VALUES ($req_id, $item_id, $qty, $user_id)");
                }
            }

            echo "<script>alert('Requisition Saved Successfully!'); window.location='manage_requisition.php';</script>";
            exit;
        }
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'get_school_enrollment') {
    $school_id = intval($_POST['school_id']);
    $res = mysqli_query($conn, "SELECT enrollment FROM tbl_manage_school WHERE id = '$school_id' LIMIT 1");
    $row = mysqli_fetch_assoc($res);
    echo json_encode(['enrollment' => $row['enrollment'] ?? 0]);
    exit;
}

//*************************************** Update Requisition **************************************//
function updateRequisitionDetailsOnly($conn)
{
    if (isset($_POST['update_requisition'])) {
        $req_id = (int)$_POST['requisition_id'];
        $req_date = mysqli_real_escape_string($conn, $_POST['req_date']);
        $school_id = (int)$_POST['school_id'];
        $head_id = (int)$_POST['head_id'];
        $user_id = $_SESSION['user_id'] ?? 1;

        // --- 1. Validation Logic (200k Limit) ---
        $current_req_total = (float)$_POST['expected_amount'];
        if ($current_req_total > 200000) {
            $_SESSION['error'] = "Error: Single Requisition cannot exceed 200,000.";
            header("Location: ../views/edit_requisition.php?id=$req_id");
            exit;
        }

        // --- 2. LOGGING: Purana data logs table mein save karein ---

        // A. Main Requisition Log (Marking as 'Updated' for history)
        $old_req = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tbl_requisition WHERE id = $req_id"));
        if ($old_req) {
            $log_ins = "INSERT INTO tbl_requisition_logs (requisition_id, school_id, tenure_id, head_id, req_date, description, created_by, status) 
                        VALUES ($req_id, '{$old_req['school_id']}', '{$old_req['tenure_id']}', '{$old_req['head_id']}', '{$old_req['req_date']}', '{$old_req['description']}', '$user_id', 'Updated')";
            mysqli_query($conn, $log_ins);
            $req_log_id = mysqli_insert_id($conn);

            // B. Requisition Details Log
            $old_details = mysqli_query($conn, "SELECT * FROM tbl_requisition_details WHERE requisition_id = $req_id");
            while ($od = mysqli_fetch_assoc($old_details)) {
                mysqli_query($conn, "INSERT INTO tbl_requisition_details_logs (requisition_log_id, item_id, quantity, created_by) 
                                     VALUES ($req_log_id, '{$od['item_id']}', '{$od['quantity']}', '$user_id')");
            }

            // C. Quotation & Details Log
            $q_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tbl_quotation WHERE requisition_id = $req_id"));
            if ($q_data) {
                $q_id = $q_data['id'];

                // Quotation main log
                $q_log_sql = "INSERT INTO tbl_quotation_logs (quotation_id, requisition_id, requisition_log_id, school_id, head_id, winner_vendor_id, quotation_date, created_by, log_status) 
                  VALUES ($q_id, $req_id, $req_log_id, '{$q_data['school_id']}', '{$q_data['head_id']}', '{$q_data['winner_vendor_id']}', '{$q_data['quotation_date']}', '$user_id', 'Updated')";

                mysqli_query($conn, $q_log_sql);
                $q_log_parent_id = mysqli_insert_id($conn);

                $old_q_details = mysqli_query($conn, "SELECT * FROM tbl_quotation_details WHERE quotation_id = $q_id");
                while ($oqd = mysqli_fetch_assoc($old_q_details)) {
                    mysqli_query($conn, "INSERT INTO tbl_quotation_details_logs (quotation_id, quotation_log_id, vendor_id, item_id, price, quantity) 
                             VALUES ($q_id, $q_log_parent_id, '{$oqd['vendor_id']}', '{$oqd['item_id']}', '{$oqd['price']}', '{$oqd['quantity']}')");
                }
            }
        }

        // --- 3. UPDATION: Main tables ko update karein ---

        // A. Update Main Requisition Date
        mysqli_query($conn, "UPDATE tbl_requisition SET req_date = '$req_date' WHERE id = $req_id");

        // B. Delete and Re-insert Requisition Details
        mysqli_query($conn, "DELETE FROM tbl_requisition_details WHERE requisition_id = $req_id");
        foreach ($_POST['items'] as $i => $item_id) {
            if (!empty($item_id)) {
                $qty = (int)$_POST['qty'][$i];
                mysqli_query($conn, "INSERT INTO tbl_requisition_details (requisition_id, item_id, quantity, created_by) 
                                     VALUES ($req_id, $item_id, $qty, $user_id)");
            }
        }

        // --- 4. QUOTATION SYNC ---
        if (isset($q_id)) {
            // Purani details delete
            mysqli_query($conn, "DELETE FROM tbl_quotation_details WHERE quotation_id = $q_id");

            // Vendors fetch (Winner + 2 Others)
            $winner_query = mysqli_query($conn, "SELECT v.id as vendor_id FROM tbl_vendor v 
                        JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id 
                        WHERE vit.item_type_id = '$head_id' AND v.win_status = 'Winner' AND v.status = 'Active' LIMIT 1");
            $winner_data = mysqli_fetch_assoc($winner_query);

            $winner_id = $winner_data ? $winner_data['vendor_id'] : 0;

            // AB YAHAN UPDATE KAREIN: Quotation Date AUR Winner Vendor dono!
            mysqli_query($conn, "UPDATE tbl_quotation SET quotation_date = '$req_date', winner_vendor_id = '$winner_id' WHERE id = $q_id");

            $other_vendors_query = mysqli_query($conn, "SELECT v.id as vendor_id, v.win_status FROM tbl_vendor v 
                                JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id 
                                WHERE vit.item_type_id = '$head_id' AND (v.win_status != 'Winner' OR v.win_status IS NULL) AND v.status = 'Active' LIMIT 2");
            $other_vendors = mysqli_fetch_all($other_vendors_query, MYSQLI_ASSOC);

            $all_vendors = [];
            if ($winner_data) $all_vendors[] = ['vendor_id' => $winner_data['vendor_id'], 'win_status' => 'Winner'];
            foreach ($other_vendors as $ov) $all_vendors[] = ['vendor_id' => $ov['vendor_id'], 'win_status' => 'Other'];

            // Naye prices ke saath insertion
            $items_res = mysqli_query($conn, "SELECT rd.item_id, rd.quantity, i.min_price, i.max_price FROM tbl_requisition_details rd JOIN tbl_item i ON rd.item_id = i.id WHERE rd.requisition_id = '$req_id'");

            while ($item = mysqli_fetch_assoc($items_res)) {
                $others_counter = 1;
                foreach ($all_vendors as $v) {
                    $min = (float)$item['min_price'];
                    $max = (float)$item['max_price'];

                    if ($v['win_status'] == 'Winner') {
                        $price = round($min);
                    } else {
                        if ($others_counter == 1) {
                            $price = round(($min + $max) / 2);
                            $others_counter++;
                        } else {
                            $price = round($max);
                        }
                    }
                    mysqli_query($conn, "INSERT INTO tbl_quotation_details (quotation_id, vendor_id, item_id, price, quantity) 
                                         VALUES ('$q_id', '{$v['vendor_id']}', '{$item['item_id']}', '$price', '{$item['quantity']}')");
                }
            }
        }

        // Final Response (Loop ke bahar)
        $_SESSION['success'] = "Requisition updated and history logged successfully!";
        echo "<script>window.location='../views/manage_requisition.php';</script>";
        exit;
    }
}

//*************************************** Delete Requisition **************************************//
if (isset($_GET['action']) && $_GET['action'] == 'delete_requisition') {

    $req_id = (int)$_GET['id'];
    $user_id = $_SESSION['user_id'] ?? 1;

    // --- 1. REQUISITION DATA FETCH ---
    $old_req = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tbl_requisition WHERE id = $req_id"));

    if ($old_req) {
        // --- A. MAIN REQUISITION LOG ---
        $req_log_sql = "INSERT INTO tbl_requisition_logs (requisition_id, school_id, tenure_id, head_id, req_date, description, created_by, status) 
                        VALUES ($req_id, '{$old_req['school_id']}', '{$old_req['tenure_id']}', '{$old_req['head_id']}', '{$old_req['req_date']}', '{$old_req['description']}', $user_id, 'Deleted')";
        mysqli_query($conn, $req_log_sql);
        $req_log_id = mysqli_insert_id($conn); // Get parent log ID

        // --- B. REQUISITION DETAILS LOG ---
        $rd_res = mysqli_query($conn, "SELECT * FROM tbl_requisition_details WHERE requisition_id = $req_id");
        while ($od = mysqli_fetch_assoc($rd_res)) {
            // Yahan requisition_log_id bhej rahe hain
            mysqli_query($conn, "INSERT INTO tbl_requisition_details_logs (requisition_log_id, item_id, quantity, created_by) 
                                 VALUES ($req_log_id, '{$od['item_id']}', '{$od['quantity']}', $user_id)");
        }

        // --- C. QUOTATION & ITS DETAILS LOG ---
        $q_res = mysqli_query($conn, "SELECT * FROM tbl_quotation WHERE requisition_id = $req_id");
        if ($q_row = mysqli_fetch_assoc($q_res)) {
            $q_id = $q_row['id'];

            // Main Quotation Log (Added: requisition_log_id)
            $q_log_sql = "INSERT INTO tbl_quotation_logs (quotation_id, requisition_id, requisition_log_id, school_id, head_id, winner_vendor_id, quotation_date, created_by, log_status) 
                          VALUES ($q_id, $req_id, $req_log_id, '{$q_row['school_id']}', '{$q_row['head_id']}', '{$q_row['winner_vendor_id']}', '{$q_row['quotation_date']}', $user_id, 'Deleted')";
            mysqli_query($conn, $q_log_sql);
            $q_log_parent_id = mysqli_insert_id($conn); // Get this specific quotation log row ID

            // Quotation Details Log (Added: quotation_log_id)
            $qdl_res = mysqli_query($conn, "SELECT * FROM tbl_quotation_details WHERE quotation_id = '$q_id'");
            while ($qdl = mysqli_fetch_assoc($qdl_res)) {
                $v_id = $qdl['vendor_id'];
                $i_id = $qdl['item_id'];
                $prc  = $qdl['price'];
                $qnt  = $qdl['quantity'];

                // Yahan quotation_log_id bhej rahe hain taake exact match ho
                mysqli_query($conn, "INSERT INTO tbl_quotation_details_logs (quotation_id, quotation_log_id, vendor_id, item_id, price, quantity) 
                                     VALUES ('$q_id', '$q_log_parent_id', '$v_id', '$i_id', '$prc', '$qnt')");
            }
        }

        // --- 3. FINAL DELETION (Main Tables se saaf kar do) ---
        if (isset($q_id)) {
            mysqli_query($conn, "DELETE FROM tbl_quotation_details WHERE quotation_id = $q_id");
            mysqli_query($conn, "DELETE FROM tbl_quotation WHERE id = $q_id");
        }
        mysqli_query($conn, "DELETE FROM tbl_requisition_details WHERE requisition_id = $req_id");
        mysqli_query($conn, "DELETE FROM tbl_requisition WHERE id = $req_id");

        $_SESSION['success'] = "Requisition deleted. All records archived with Parent Log IDs.";
    }

    header("Location: ../views/manage_requisition.php");
    exit();
}
