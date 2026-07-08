<?php
session_start();
require_once '../config/database.php';

function getNextDate($startDate, $gapDays = 2)
{
    $date = new DateTime($startDate);
    $added = 0;
    while ($added < $gapDays) {
        $date->modify('+1 day');
        if ($date->format('N') != 7) { // Sunday skip
            $added++;
        }
    }
    return $date->format('Y-m-d');
}

if (isset($_GET['action']) && $_GET['action'] == 'auto_generate') {

    $school_id = intval($_GET['school_id']);
    $tenure_val = mysqli_real_escape_string($conn, $_GET['tenure']);
    $user_id = $_SESSION['user_id'];

    $reference_date = (isset($_GET['start_date']) && !empty($_GET['start_date'])) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-d');

    $school_res = mysqli_query($conn, "SELECT enrollment FROM tbl_manage_school WHERE id = $school_id");
    $school_data = mysqli_fetch_assoc($school_res);
    $enrollment = $school_data ? intval($school_data['enrollment']) : 0;

    $tenure_q = mysqli_query($conn, "SELECT id FROM tbl_tenure WHERE tenure = '$tenure_val' LIMIT 1");
    $t_row = mysqli_fetch_assoc($tenure_q);
    $current_tenure_id = $t_row['id'];

    // $check_query = "SELECT id FROM tbl_requisition 
    //             WHERE school_id = '$school_id' 
    //             AND tenure_id = '$current_tenure_id' 
    //             LIMIT 1";
    // $check_res = mysqli_query($conn, $check_query);

    // if (mysqli_num_rows($check_res) > 0) {
    //     die("<script>alert('Error: Budget already processed for this tenure!'); window.history.back();</script>");
    // }

    if ($enrollment <= 0) {
        die("<script>alert('Error: School enrollment is 0 or not found!'); window.history.back();</script>");
    }

    // 2. Get Budget and Head Details (New Schema Join)
    $budget_sql = "SELECT 
                        bh.head_id, 
                        bh.amount as total_budget, 
                        h.code_no as head_code 
                   FROM tbl_budget_details bh 
                   JOIN tbl_budget b ON bh.budget_id = b.id 
                   JOIN tbl_tenure t ON b.tenure_id = t.id 
                   JOIN tbl_heads h ON bh.head_id = h.id 
                   WHERE b.school_id = $school_id 
                   AND t.tenure = '$tenure_val' 
                   AND bh.STATUS = 'Active'";

    $budget_q = mysqli_query($conn, $budget_sql);

    if (mysqli_num_rows($budget_q) == 0) {
        die("<script>alert('Budget not added for this tenure: $tenure_val'); window.history.back();</script>");
    }

    $tenure_q = mysqli_query($conn, "SELECT id FROM tbl_tenure WHERE tenure = '$tenure_val' LIMIT 1");
    $tenure_row = mysqli_fetch_assoc($tenure_q);
    $tenure_id = $tenure_row['id'];

    while ($head = mysqli_fetch_assoc($budget_q)) {
        $head_id = $head['head_id'];
        $head_code = $head['head_code'];
        $total_allocated = floatval($head['total_budget']);

        // --- NEW SPENT BUDGET CHECK START ---
        $spent_query = "SELECT SUM(qd.price * qd.quantity) AS spent 
                    FROM tbl_quotation q
                    JOIN tbl_quotation_details qd ON q.id = qd.quotation_id
                    JOIN tbl_requisition r ON q.requisition_id = r.id
                    WHERE q.school_id = '$school_id' 
                    AND r.tenure_id = '$current_tenure_id'
                    AND q.head_id = '$head_id' 
                    AND q.winner_vendor_id = qd.vendor_id 
                    AND q.status = 'Active'";

        $spent_res = mysqli_query($conn, $spent_query);
        $spent_row = mysqli_fetch_assoc($spent_res);
        $already_spent = floatval($spent_row['spent'] ?? 0);

        // Ab actual bacha hua budget calculate karein
        $remaining_for_this_head = $total_allocated - $already_spent;

        // Agar budget pehle hi khatam ho chuka hai (Manual entries ki wajah se), to is Head ko skip kar do
        if ($remaining_for_this_head <= 0) {
            continue;
        }
        // --- NEW SPENT BUDGET CHECK END ---

        $spent_so_far = 0; // Ye loop ke andar tracking ke liye hai

        // Loop jab tak pura budget khatam na ho jaye
        while ($spent_so_far < $remaining_for_this_head) {

            $max_req_limit = 200000;
            $budget_left_to_process = $remaining_for_this_head - $spent_so_far;

            $allowed_limit = min($max_req_limit, $budget_left_to_process);

            if ($allowed_limit < 10) break;

            $current_req_amount = 0;
            $items_to_add = [];

            // --- HEAD WISE ITEM LOGIC ---
            if ($head_code == 'A13370') {
                $item_res = mysqli_query($conn, "SELECT i.id FROM tbl_item i 
                            JOIN tbl_item_head_type iht ON i.id = iht.item_id 
                            WHERE iht.head_type_id = $head_id 
                            AND i.item_category = 'Primary'
                            AND i.status = 'Active' LIMIT 1");

                if ($item = mysqli_fetch_assoc($item_res)) {
                    $items_to_add[] = [
                        'id' => $item['id'],
                        'qty' => 1,
                        'min' => $allowed_limit, // Budget amount as price
                        'max' => $allowed_limit,
                        'is_fixed' => true
                    ];
                    $current_req_amount = $allowed_limit;
                }
            } elseif ($head_code == 'A03901') {
                $current_req_amount = 0;
                $items_to_add = [];

                // --- STEP 1: Unique Items (Jo is tenure mein pehle nahi liye gaye) ---
                $already_purchased_subquery = "SELECT DISTINCT rd.item_id 
                                    FROM tbl_requisition_details rd
                                    JOIN tbl_requisition r ON rd.requisition_id = r.id
                                    WHERE r.school_id = $school_id 
                                    AND r.tenure_id = $tenure_id";

                // Item Name 'Paper Rim' ko exclude karne ke liye check lagaya hai
                $unique_items_q = mysqli_query($conn, "SELECT i.id, i.min_price, i.max_price FROM tbl_item i 
                JOIN tbl_item_head_type iht ON i.id = iht.item_id 
                WHERE iht.head_type_id = $head_id 
                AND i.status = 'Active' 
                AND i.item_category = 'Primary'
                AND i.item_name NOT LIKE '%PAPER REAM%' 
                AND i.id NOT IN ($already_purchased_subquery)
                ORDER BY RAND()");

                while ($item = mysqli_fetch_assoc($unique_items_q)) {
                    $cost = $item['min_price'] * $enrollment;
                    if (($current_req_amount + $cost) <= $allowed_limit) {
                        $items_to_add[] = [
                            'id' => $item['id'],
                            'qty' => $enrollment,
                            'min' => $item['min_price'],
                            'max' => $item['max_price']
                        ];
                        $current_req_amount += $cost;
                    }
                }

                // --- STEP 2: Repeat Items (Agar budget bacha ho toh dobara wahi items uthao) ---
                $max_repeat_attempts = 15; // Limit barha di taake variety milti rahe
                $attempt = 0;

                // Rim ki price nikalne ke liye query (By Name)
                $rim_data_res = mysqli_query($conn, "SELECT id, min_price, max_price FROM tbl_item WHERE item_name LIKE '%PAPER REAM%' LIMIT 1");
                $rim = mysqli_fetch_assoc($rim_data_res);
                $rim_mid_price = ($rim['min_price'] + $rim['max_price']) / 2;

                while ($current_req_amount < ($allowed_limit - $rim_mid_price) && $attempt < $max_repeat_attempts) {

                    $current_ids = count($items_to_add) > 0 ? implode(',', array_column($items_to_add, 'id')) : '0';

                    $repeat_q = mysqli_query($conn, "SELECT i.id, i.min_price, i.max_price FROM tbl_item i 
                     JOIN tbl_item_head_type iht ON i.id = iht.item_id 
                     WHERE iht.head_type_id = $head_id 
                     AND i.status = 'Active' 
                     AND i.item_category = 'Primary'
                     AND i.item_name NOT LIKE '%PAPER REAM%' 
                     AND i.id NOT IN ($current_ids) 
                     ORDER BY RAND() LIMIT 5");

                    $added_in_round = false;
                    while ($item = mysqli_fetch_assoc($repeat_q)) {
                        $cost = $item['min_price'] * $enrollment;
                        if (($current_req_amount + $cost) <= $allowed_limit) {
                            $items_to_add[] = [
                                'id' => $item['id'],
                                'qty' => $enrollment,
                                'min' => $item['min_price'],
                                'max' => $item['max_price']
                            ];
                            $current_req_amount += $cost;
                            $added_in_round = true;
                        }
                    }
                    if (!$added_in_round) break;
                    $attempt++;
                }

                // --- STEP 3: Paper Rim as Filler (By Name) ---
                if ($rim) {
                    $remaining_gap = $allowed_limit - $current_req_amount;
                    if ($remaining_gap >= $rim['min_price']) {
                        $rim_qty = floor($remaining_gap / $rim['min_price']);
                        if ($rim_qty > 0) {
                            $items_to_add[] = [
                                'id' => $rim['id'],
                                'qty' => $rim_qty,
                                'min' => $rim['min_price'],
                                'max' => $rim['max_price']
                            ];
                            $current_req_amount += ($rim_qty * $rim['min_price']);
                        }
                    }
                }
            } else {
                $item_res = mysqli_query($conn, "SELECT i.id, i.min_price, i.max_price FROM tbl_item i 
                    JOIN tbl_item_head_type iht ON i.id = iht.item_id 
                    WHERE iht.head_type_id = $head_id AND i.status = 'Active' AND i.item_category = 'Primary' limit 1");
                if ($item = mysqli_fetch_assoc($item_res)) {
                    $qty = floor($allowed_limit / $item['min_price']);
                    if ($qty > 0) {
                        $items_to_add[] = ['id' => $item['id'], 'qty' => $qty, 'min' => $item['min_price'], 'max' => $item['max_price']];
                        $current_req_amount = $qty * $item['min_price'];
                    }
                }
            }

            if (empty($items_to_add)) break;

            // --- 1. INSERT REQUISITION ---
            $ins_req = "INSERT INTO tbl_requisition (school_id, tenure_id, head_id, req_date, description, created_by, status) 
                    VALUES ($school_id, $tenure_id, $head_id, '$reference_date', 'Auto Processed', $user_id, 'Active')";
            mysqli_query($conn, $ins_req);
            $req_id = mysqli_insert_id($conn);

            foreach ($items_to_add as $it) {
                mysqli_query($conn, "INSERT INTO tbl_requisition_details (requisition_id, item_id, quantity, created_by) 
                                     VALUES ($req_id, {$it['id']}, {$it['qty']}, $user_id)");
            }


            // --- Insertion Logic for Outward No Table ---

            // 1. Pehle check karo is School aur Tenure ke liye max number kya chal raha hai
            $sql_max = "SELECT MAX(doc_no) as last_no 
            FROM tbl_outward_no 
            WHERE school_id = '$school_id' 
            AND tenure_id = '$tenure_id'";

            $res_max = mysqli_query($conn, $sql_max);
            $row_max = mysqli_fetch_assoc($res_max);

            // Agar koi record nahi mila to 1 se shuru karo, warna +1
            $new_doc_no = ($row_max['last_no'] ?? 0) + 1;

            // 2. Ab check karo kahin is Requisition ID ki entry pehle se to nahi hai (Duplicate safety)
            $check_exists = mysqli_query($conn, "SELECT id FROM tbl_outward_no WHERE requisition_id = '$req_id'");

            if (mysqli_num_rows($check_exists) == 0) {
                // 3. Insert kar do naya record
                $ins_outward = "INSERT INTO tbl_outward_no (school_id, tenure_id, requisition_id, doc_no) 
                    VALUES ('$school_id', '$tenure_id', '$req_id', '$new_doc_no')";

                mysqli_query($conn, $ins_outward);
            }

            // --- 2. PREPARE VENDORS (Winner + 2 Others) ---
            $winner_q = mysqli_query($conn, "SELECT v.id FROM tbl_vendor v 
                JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id 
                WHERE vit.item_type_id = '$head_id' AND v.win_status = 'Winner' AND v.status = 'Active' LIMIT 1");
            $winner_data = mysqli_fetch_assoc($winner_q);
            $winner_id = $winner_data ? $winner_data['id'] : 'NULL';

            $others_q = mysqli_query($conn, "SELECT v.id FROM tbl_vendor v 
                JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id 
                WHERE vit.item_type_id = '$head_id' AND (v.win_status != 'Winner' OR v.win_status IS NULL) AND v.status = 'Active' LIMIT 2");
            $other_vendors = mysqli_fetch_all($others_q, MYSQLI_ASSOC);

            $all_vendors = [];
            if ($winner_data) $all_vendors[] = ['id' => $winner_id, 'type' => 'Winner'];
            foreach ($other_vendors as $ov) $all_vendors[] = ['id' => $ov['id'], 'type' => 'Other'];

            // --- 3. INSERT QUOTATION ---
            $q_date = getNextDate($reference_date, 2); // 2 din gap skip Sunday logic
            mysqli_query($conn, "INSERT INTO tbl_quotation (school_id, head_id, requisition_id, winner_vendor_id, quotation_date, created_by) 
                                 VALUES ('$school_id', '$head_id', '$req_id', $winner_id, '$q_date', '$user_id')");
            $q_id = mysqli_insert_id($conn);

            // --- 4. INSERT QUOTATION DETAILS (3 Vendors Logic) ---
            // ... baaki upar ka code same hai ...

            // --- 4. INSERT QUOTATION DETAILS (3 Vendors Logic) ---
            foreach ($items_to_add as $it) {
                $others_counter = 1;

                // Yahan hum price1 ko pehle se define kar letay hain taake niche error na aaye
                $price1 = round($it['min']);

                foreach ($all_vendors as $v) {
                    if (isset($it['is_fixed']) && $it['is_fixed'] == true) {
                        // --- FIXED LOGIC FOR A13370 ---
                        if ($v['type'] == 'Winner') {
                            $price = $price1;
                        } else {
                            // BUG FIXED: Pehle yahan $price1 define nahi tha aur variables $price2/$price3 use ho rahe thay
                            if ($others_counter == 1) {
                                $price = $price1 + 5000;
                                $others_counter++;
                            } else {
                                $price = $price1 + 10000;
                            }
                        }
                    } else {
                        // --- NORMAL STATIONARY LOGIC ---
                        if ($v['type'] == 'Winner') {
                            $price = $price1;
                        } else {
                            if ($others_counter == 1) {
                                $price = round(($it['min'] + $it['max']) / 2);
                                $others_counter++;
                            } else {
                                $price = round($it['max']);
                            }
                        }
                    }

                    // Final Query
                    mysqli_query($conn, "INSERT INTO tbl_quotation_details (quotation_id, vendor_id, item_id, price, quantity) 
                             VALUES ($q_id, {$v['id']}, {$it['id']}, '$price', {$it['qty']})");
                }
            }
            // ... baaki spent_so_far wala code same hai ...

            $spent_so_far += $current_req_amount;
        }
    }
    echo "<script>alert('Success: All budgets processed within limits!'); window.location='../views/manage_requisition.php';</script>";
}
