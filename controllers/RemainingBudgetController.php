<?php
session_start();
require_once '../config/database.php';

// Date calculate karne ka function (Sunday skip karta hai)
function getNextDate($startDate, $gapDays = 2)
{
    $date = new DateTime($startDate);
    $added = 0;
    while ($added < $gapDays) {
        $date->modify('+1 day');
        if ($date->format('N') != 7) {
            $added++;
        }
    }
    return $date->format('Y-m-d');
}

if (isset($_GET['action']) && $_GET['action'] == 'process_remaining') {
    $school_id = intval($_GET['school_id']);
    $tenure_val = mysqli_real_escape_string($conn, $_GET['tenure']);
    $user_id = $_SESSION['user_id'] ?? 1;
    $reference_date = date('Y-m-d');

    // 1. Tenure ID nikalna
    $t_q = mysqli_query($conn, "SELECT id FROM tbl_tenure WHERE tenure = '$tenure_val' LIMIT 1");
    $t_row = mysqli_fetch_assoc($t_q);
    $tenure_id = $t_row['id'];

    // 2. School ka enrollment nikalna
    $sch_q = mysqli_query($conn, "SELECT enrollment FROM tbl_manage_school WHERE id = $school_id");
    $sch_row = mysqli_fetch_assoc($sch_q);
    $enrollment = intval($sch_row['enrollment']);

    // 3. Har Head ka Budget nikalna
    $budget_sql = "SELECT bh.head_id, bh.amount as total_allocated 
                   FROM tbl_budget_details bh 
                   JOIN tbl_budget b ON bh.budget_id = b.id 
                   WHERE b.school_id = $school_id AND b.tenure_id = $tenure_id AND bh.STATUS = 'Active'";

    $budget_res = mysqli_query($conn, $budget_sql);

    if (mysqli_num_rows($budget_res) == 0) {
        echo "<script>alert('Error: Budget not found!'); window.location.href='../views/manage_school.php';</script>";
        exit();
    }

    while ($head = mysqli_fetch_assoc($budget_res)) {
        $head_id = $head['head_id'];
        $total_budget = floatval($head['total_allocated']);

        // 4. Spent Amount nikalna (Aap ki provide ki hui query)
        $spent_query = "SELECT SUM(qd.price * qd.quantity) AS spent 
                        FROM tbl_quotation q
                        JOIN tbl_quotation_details qd ON q.id = qd.quotation_id
                        JOIN tbl_requisition r ON q.requisition_id = r.id
                        WHERE q.school_id = '$school_id' 
                        AND r.tenure_id = '$tenure_id'
                        AND q.head_id = '$head_id' 
                        AND q.winner_vendor_id = qd.vendor_id 
                        AND q.status = 'Active'";

        $spent_res = mysqli_query($conn, $spent_query);
        $spent_row = mysqli_fetch_assoc($spent_res);
        $spent = floatval($spent_row['spent'] ?? 0);

        // Kitne paise bache hain?
        $remaining_budget = $total_budget - $spent;
        if ($remaining_budget < 1000) {
            continue;
        }

        if ($remaining_budget >= 200000) {
            continue;
        }


        // Agar 0 se zyada bache hain toh Secondary Items dhoondo
        if ($remaining_budget > 0) {

            $sec_item_q = "SELECT i.id, i.min_price, i.max_price FROM tbl_item i 
                           JOIN tbl_item_head_type iht ON i.id = iht.item_id 
                           WHERE iht.head_type_id = $head_id 
                           AND i.item_category = 'Secondary' 
                           AND i.status = 'Active' ORDER BY RAND()";
            $sec_res = mysqli_query($conn, $sec_item_q);

            $items_to_add = [];
            $temp_money = $remaining_budget;

            while ($item = mysqli_fetch_assoc($sec_res)) {
                $price = $item['min_price'];
                $full_enrollment_cost = $price * $enrollment;

                // --- SIMPLE LOGIC START ---
                if ($full_enrollment_cost <= $temp_money) {
                    // Agar paise hain toh poore bachon ke liye le lo
                    $final_qty = $enrollment;
                } else {
                    // Agar paise kam hain toh bache hue paison mein jitne aate hain le lo
                    $final_qty = floor($temp_money / $price);
                }

                if ($final_qty > 0) {
                    $items_to_add[] = [
                        'id' => $item['id'],
                        'qty' => $final_qty,
                        'min' => $item['min_price'],
                        'max' => $item['max_price']
                    ];
                    $temp_money -= ($final_qty * $price);
                }

                if ($temp_money < 10) break; // Budget khatam
            }

            if (!empty($items_to_add)) {
                // 5. Insert Requisition
                $req_date = (isset($_GET['start_date']) && !empty($_GET['start_date'])) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-d');
                mysqli_query($conn, "INSERT INTO tbl_requisition (school_id, tenure_id, head_id, req_date, description, created_by, status) 
                                    VALUES ($school_id, $tenure_id, $head_id, '$req_date', 'Budget Adjustment', $user_id, 'Active')");
                $req_id = mysqli_insert_id($conn);

                // 6. Outward Number
                $sql_max = "SELECT MAX(doc_no) as last_no FROM tbl_outward_no WHERE school_id = '$school_id' AND tenure_id = '$tenure_id'";
                $row_max = mysqli_fetch_assoc(mysqli_query($conn, $sql_max));
                $new_doc_no = ($row_max['last_no'] ?? 0) + 1;
                mysqli_query($conn, "INSERT INTO tbl_outward_no (school_id, tenure_id, requisition_id, doc_no) VALUES ('$school_id', '$tenure_id', '$req_id', '$new_doc_no')");

                // 7. Vendors nikalna
                // Winner Vendor
                $winner_q = mysqli_query($conn, "SELECT v.id FROM tbl_vendor v JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id WHERE vit.item_type_id = '$head_id' AND v.win_status = 'Winner' LIMIT 1");
                $win_row = mysqli_fetch_assoc($winner_q);
                $winner_id = $win_row['id'] ?? null;

                // Other 2 Vendors
                $others_q = mysqli_query($conn, "SELECT v.id FROM tbl_vendor v JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id WHERE vit.item_type_id = '$head_id' AND (v.win_status != 'Winner' OR v.win_status IS NULL) LIMIT 2");

                $all_vendors = [];
                // Pehle winner ko array mein dala
                if ($winner_id) {
                    $all_vendors[] = ['id' => $winner_id, 'type' => 'Winner'];
                }
                // Phir baki vendors ko dala
                while ($v_row = mysqli_fetch_assoc($others_q)) {
                    $all_vendors[] = ['id' => $v_row['id'], 'type' => 'Other'];
                }

                // Quotation Insert
                $q_date = getNextDate($req_date, 2);
                mysqli_query($conn, "INSERT INTO tbl_quotation (school_id, head_id, requisition_id, winner_vendor_id, quotation_date, created_by) 
                                     VALUES ('$school_id', '$head_id', '$req_id', $winner_id, '$q_date', '$user_id')");
                $q_id = mysqli_insert_id($conn);

                // --- Quotation Details (Ab yahan loop chalay ga teeno vendors ke liye) ---
                foreach ($items_to_add as $it) {

                    // Requisition details (sirf aik baar)
                    mysqli_query($conn, "INSERT INTO tbl_requisition_details (requisition_id, item_id, quantity, created_by) VALUES ($req_id, {$it['id']}, {$it['qty']}, $user_id)");

                    $other_count = 1; // Counter taake p2 aur p3 price set ho sakay

                    foreach ($all_vendors as $v) {
                        if ($v['type'] == 'Winner') {
                            $final_price = round($it['min']); // Winner sasta rate
                        } else {
                            if ($other_count == 1) {
                                $final_price = round(($it['min'] + $it['max']) / 2); // Medium rate
                                $other_count++;
                            } else {
                                $final_price = round($it['max']); // Expensive rate
                            }
                        }

                        $v_id = $v['id'];
                        mysqli_query($conn, "INSERT INTO tbl_quotation_details (quotation_id, vendor_id, item_id, price, quantity) 
                                             VALUES ($q_id, $v_id, {$it['id']}, '$final_price', {$it['qty']})");
                    }
                }
            }
        }
    }
    echo "<script>
        alert('Success: All budgets processed within limits!');
        window.location.href = '../views/manage_school.php'; 
    </script>";
    exit();
}
