<?php
require_once '../config/database.php';
require_once '../includes/crypto.php';


function getBudgetData($conn)
{
    $encrypted_id = $_GET['id'] ?? '';
    $school_id = decrypt_id($encrypted_id);

    if (!$school_id) {
        die("Invalid ID");
    }

    $school_id = mysqli_real_escape_string($conn, $school_id);

    $res['school_info'] = mysqli_query($conn, "SELECT id, school_code, enrollment FROM tbl_manage_school WHERE id = '$school_id'");

    $res['tenures'] = mysqli_query($conn, "SELECT * FROM tbl_tenure WHERE status = 'Active' ORDER BY id");

    $res['heads'] = mysqli_query($conn, "SELECT h.id, h.head_name, h.code_no FROM tbl_heads AS h
                        INNER JOIN tbl_head_head_types AS htt ON h.id = htt.head_id
                        INNER JOIN tbl_head_type AS ht ON ht.id = htt.head_type_id
                        WHERE ht.type = 's' AND h.status = 'Active'");

    $res['history'] = mysqli_query($conn, "SELECT b.id as budget_id, t.tenure, h.head_name, h.code_no, bd.amount, bd.created_on, bd.id as detail_id
                        FROM tbl_budget AS b
                        INNER JOIN tbl_budget_details AS bd ON b.id = bd.budget_id
                        INNER JOIN tbl_tenure AS t ON t.id = b.tenure_id
                        INNER JOIN tbl_heads AS h ON bd.head_id = h.id
                        WHERE b.STATUS = 'Active' AND b.school_id = '$school_id'
                        ORDER BY b.created_on DESC");

    return $res;
}

function saveBudget($conn, $post_data)
{
    $school_id  = decrypt_id($post_data['school_id']);

    if (!$school_id) {
        return false;
    }

    $school_id  = mysqli_real_escape_string($conn, $school_id);
    $tenure_id  = mysqli_real_escape_string($conn, $post_data['tenure_id']);
    $head_ids   = $post_data['head_ids']; // Array
    $budgets    = $post_data['budgets'];  // Array
    $u_id       = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $date       = date('Y-m-d H:i:s');

    $check_query = "SELECT id FROM tbl_budget WHERE school_id = '$school_id' AND tenure_id = '$tenure_id' AND STATUS = 'Active' LIMIT 1";
    $check_res = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_res) > 0) {
        return "exists";
    }

    // STEP 1: Insert into tbl_budget (Master)
    $sql_master = "INSERT INTO tbl_budget (school_id, tenure_id, ip_address, created_by, created_on, STATUS) 
                   VALUES ('$school_id', '$tenure_id', '$ip_address', '$u_id', '$date', 'Active')";


    if (mysqli_query($conn, $sql_master)) {
        $last_id = mysqli_insert_id($conn);

        // STEP 4: Insert into tbl_budget_logs (Main Event Log)
        mysqli_query($conn, "INSERT INTO tbl_budget_logs (budget_id, school_id, tenure_id, ip_address, created_by, created_on) 
                             VALUES ('$last_id', '$school_id', '$tenure_id', '$ip_address', '$u_id', '$date')");
        $budget_log_id = mysqli_insert_id($conn);
        // STEP 2 & 3: Loop for Details and Details Logs
        foreach ($head_ids as $index => $h_id) {
            $amt  = mysqli_real_escape_string($conn, $budgets[$index]);
            $head = mysqli_real_escape_string($conn, $h_id);

            // Sirf wahi save karo jiski amount user ne daali ho
            if ($amt > 0) {
                // Details Table
                mysqli_query($conn, "INSERT INTO tbl_budget_details (budget_id, head_id, amount, ip_address, created_on, STATUS) 
                                     VALUES ('$last_id', '$head', '$amt', '$ip_address', '$date', 'Active')");

                // Details Logs Table
                mysqli_query($conn, "INSERT INTO tbl_budget_details_logs (budget_id, head_id, amount, ip_address, created_on) 
                                     VALUES ('$budget_log_id', '$head', '$amt', '$ip_address', '$date')");
            }
        }

        return true;
    }
    return false;
}

function deleteBudgetDetail($conn, $budget_id)
{
    // 1. Pehle budget ki details nikaalein (Tenure aur School ID)
    $check_budget = mysqli_query($conn, "SELECT school_id, tenure_id FROM tbl_budget WHERE id = '$budget_id'");
    $budget_data = mysqli_fetch_assoc($check_budget);

    if ($budget_data) {
        $s_id = $budget_data['school_id'];
        $t_id = $budget_data['tenure_id'];

        // 2. Check karein ke kya is tenure aur school ki koi requisition bani hui hai?
        $check_req = mysqli_query($conn, "SELECT id FROM tbl_requisition WHERE school_id = '$s_id' AND tenure_id = '$t_id' LIMIT 1");

        if (mysqli_num_rows($check_req) > 0) {
            // Agar requisition maujood hai
            return "requisition_exists";
        }

        // 3. Agar requisition nahi hai, to delete karein
        // Pehle details delete karein (Foreign key constraint ki wajah se)
        mysqli_query($conn, "DELETE FROM tbl_budget_details WHERE budget_id = '$budget_id'");
        // Phir main budget delete karein
        if (mysqli_query($conn, "DELETE FROM tbl_budget WHERE id = '$budget_id'")) {
            return true;
        }
    }
    return false;
}

function getLatestQuarterlyBudget($conn)
{
    // URL se school id lena
    $encrypted_id = $_GET['id'] ?? '';
    $school_id = decrypt_id($encrypted_id);

    if (!$school_id) {
        die("Invalid ID");
    }

    $school_id = mysqli_real_escape_string($conn, $school_id);

    $sql = "SELECT b.id AS budget_id, 
                   s.id AS school_id,
                   s.`school_name`, 
                   t.tenure, 
                   h.id as head_id,
                   h.head_name, 
                   bd.amount, 
                   bd.created_on, 
                   bd.id AS detail_id
            FROM tbl_budget AS b
            INNER JOIN tbl_budget_details AS bd ON b.id = bd.budget_id
            INNER JOIN tbl_tenure AS t ON t.id = b.tenure_id
            INNER JOIN tbl_heads AS h ON bd.head_id = h.id
            INNER JOIN tbl_manage_school AS s ON s.id = b.`school_id`
            WHERE b.status = 'Active' 
            AND b.school_id = '$school_id'
            AND b.id = (SELECT MAX(id) FROM tbl_budget WHERE school_id = '$school_id' AND STATUS = 'Active')
            ORDER BY h.head_name ASC";

    return mysqli_query($conn, $sql);
}

function getConsumptionByQuarter($conn, $school_id, $head_id, $quarter_number, $tenure)
{

    $y = explode('/', $tenure)[0];

    if ($quarter_number == 1) {
        $s = "$y-07-01";
        $e = "$y-09-30";
    } elseif ($quarter_number == 2) {
        $s = "$y-10-01";
        $e = "$y-12-31";
    } elseif ($quarter_number == 3) {
        $nextY = $y + 1;
        $s = "$nextY-01-01";
        $e = "$nextY-03-31";
    } else {
        $nextY = $y + 1;
        $s = "$nextY-04-01";
        $e = "$nextY-06-30";
    }

    // Aapki tbl_quotation aur tbl_quotation_details tables use ho rahi hain
    $sql = "SELECT SUM(qd.price * qd.quantity) as spent 
            FROM tbl_quotation q
            JOIN tbl_quotation_details qd ON q.id = qd.quotation_id
            WHERE q.school_id = '$school_id' 
            AND q.head_id = '$head_id' 
            AND q.winner_vendor_id = qd.vendor_id 
            AND q.quotation_date BETWEEN '$s' AND '$e' 
            AND q.status = 'Active'";

    $res = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($res);

    return (float)($row['spent'] ?? 0);
}

// BudgetController.php mein ye function add karein

function getSchoolsBudgetExportData($conn)
{
    // 1. Unique heads for columns
    $heads_query = "SELECT DISTINCT h.id, h.head_name, h.code_no 
                FROM tbl_heads h
                JOIN tbl_budget_details bd ON h.id = bd.head_id
                WHERE bd.budget_id IN (
                    SELECT MAX(id) 
                    FROM tbl_budget 
                    GROUP BY school_id
                )
                ORDER BY h.code_no ASC";
    $heads_res = mysqli_query($conn, $heads_query);

    $unique_heads = [];
    while ($h = mysqli_fetch_assoc($heads_res)) {
        $unique_heads[$h['id']] = $h['code_no'] . " - " . $h['head_name'];
    }

    // 2. Schools with latest budget and tenure
    $query = "SELECT 
                s.id as school_id, s.school_name, s.school_code, s.semis_code, s.district, s.no_of_students as enrolment, s.demand_no,
                b.id as budget_id, t.tenure
              FROM tbl_manage_school s
              LEFT JOIN tbl_budget b ON b.school_id = s.id AND b.id = (
                  SELECT MAX(id) FROM tbl_budget WHERE school_id = s.id
              )
              LEFT JOIN tbl_tenure t ON b.tenure_id = t.id
              WHERE s.STATUS = 'Active'
              ORDER BY s.id DESC";

    $schools_res = mysqli_query($conn, $query);

    $final_rows = [];
    while ($s = mysqli_fetch_assoc($schools_res)) {
        $budget_id = $s['budget_id'];
        $row_data = [
            'info' => $s,
            'amounts' => [],
            'total_allocation' => 0 // Sum store karne ke liye
        ];

        if ($budget_id) {
            $details_query = "SELECT head_id, amount FROM tbl_budget_details WHERE budget_id = '$budget_id'";
            $details_res = mysqli_query($conn, $details_query);
            while ($d = mysqli_fetch_assoc($details_res)) {
                $row_data['amounts'][$d['head_id']] = $d['amount'];
                $row_data['total_allocation'] += $d['amount']; // Yahan sum ho raha hai
            }
        }
        $final_rows[] = $row_data;
    }

    return [
        'unique_heads' => $unique_heads,
        'rows' => $final_rows
    ];
}
