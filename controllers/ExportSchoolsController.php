<?php
require_once '../config/database.php';

function importSchoolCSV($conn, $filePath)
{
    $fileType = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);

    if (strtolower($fileType) != 'csv') {
        die("Only CSV allowed");
    }
    
    $handle = fopen($filePath, "r");
    if (!$handle) return false;

    $ip_address = $_SERVER['REMOTE_ADDR'];
    $u_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
    $date = date('Y-m-d H:i:s');

    // STEP 1: Headers Read Karein
    $headers = fgetcsv($handle);

    // --- DYNAMIC TENURE EXTRACTION ---
    // Allocation column ab index 8 par hai (shifted due to 2 new columns)
    $allocationHeader = $headers[8];
    preg_match('/\d{4}-\d{2,4}/', $allocationHeader, $matches);
    $year_from_excel = isset($matches[0]) ? $matches[0] : '';

    if ($year_from_excel == '2025-26') {
        $year_from_excel = '2025/2026';
    } elseif ($year_from_excel == '2026-27') {
        $year_from_excel = '2026/2027';
    } elseif ($year_from_excel == '2027-28') {
        $year_from_excel = '2027/2028';
    } elseif ($year_from_excel == '2028-29') {
        $year_from_excel = '2028/2029';
    }

    $tenure_id = 0;
    if (!empty($year_from_excel)) {
        $t_res = mysqli_query($conn, "SELECT id FROM tbl_tenure WHERE tenure LIKE '%$year_from_excel%' LIMIT 1");
        $t_row = mysqli_fetch_assoc($t_res);
        if ($t_row) {
            $tenure_id = $t_row['id'];
        }
    }

    if ($tenure_id == 0) {
        die("Fatal Error: Tenure '$year_from_excel' not found in Database.");
    }

    // --- STEP 2: Heads Mapping ---
    // Ab heads index 10 se shuru ho rahe hain
    $headMapping = [];
    for ($i = 10; $i < count($headers); $i++) {
        $headerValue = trim($headers[$i]);
        if (empty($headerValue) || strtolower($headerValue) == 'total') continue;
        $parts = explode('-', $headerValue);
        $code_no = trim($parts[0]);
        $head_res = mysqli_query($conn, "SELECT id FROM tbl_heads WHERE code_no = '$code_no' LIMIT 1");
        if ($head_row = mysqli_fetch_assoc($head_res)) {
            $headMapping[$i] = $head_row['id'];
        }
    }

    // --- STEP 3: Data Insertion ---
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (empty($row[2])) continue; // CostCenter khali ho to skip

        $demand_no   = mysqli_real_escape_string($conn, $row[1]);
        $cost_center = mysqli_real_escape_string($conn, $row[2]);
        $semis       = mysqli_real_escape_string($conn, $row[3]);

        // NEW COLUMNS FROM EXCEL
        $section_val = mysqli_real_escape_string($conn, $row[4]);
        $acronym_val = mysqli_real_escape_string($conn, $row[5]);

        // SHIFTED COLUMNS
        $name        = mysqli_real_escape_string($conn, $row[6]);
        $dist        = mysqli_real_escape_string($conn, $row[7]);
        $alloc_amt   = (float)str_replace(',', '', $row[8]);
        $enrol       = (int)$row[9];

        // --- CONDITION 1: School Exist Check ---
        $check_sch = mysqli_query($conn, "SELECT id FROM tbl_manage_school WHERE school_code = '$cost_center' LIMIT 1");
        if (mysqli_num_rows($check_sch) > 0) {
            $sch_row = mysqli_fetch_assoc($check_sch);
            $school_id = $sch_row['id'];
        } else {
            // Auto acronym logic khatam kar di gayi hai, ab excel wala use hoga
            $sql_school = "INSERT INTO tbl_manage_school (demand_no, school_code, semis_code, section, acronym, school_name, district, enrollment, STATUS) 
                           VALUES ('$demand_no', '$cost_center', '$semis', '$section_val', '$acronym_val', '$name', '$dist', '$enrol', 'Active')";
            mysqli_query($conn, $sql_school);
            $school_id = mysqli_insert_id($conn);

            $username = $cost_center;
            $hashed_password = password_hash($cost_center, PASSWORD_DEFAULT);

            // Check karein kahin ye user pehle se to nahi bana hua (Safety check)
            $check_user_exists = mysqli_query($conn, "SELECT id FROM tbl_users WHERE user_name = '$username' LIMIT 1");

            if (mysqli_num_rows($check_user_exists) == 0) {
                $sql_user = "INSERT INTO tbl_users (email, password, role, school_id, status) 
                     VALUES ('$username', '$hashed_password', 'user', '$school_id', 'Active')";
                mysqli_query($conn, $sql_user);
            }
        }

        // --- CONDITION 2: Duplicate Budget for same Tenure Check ---
        $check_budget = mysqli_query($conn, "SELECT id FROM tbl_budget WHERE school_id = '$school_id' AND tenure_id = '$tenure_id' AND STATUS = 'Active' LIMIT 1");
        if (mysqli_num_rows($check_budget) > 0) {
            continue;
        }

        // 2. tbl_allocation
        mysqli_query($conn, "INSERT INTO tbl_allocation (school_id, tenure_id, amount) VALUES ('$school_id', '$tenure_id', '$alloc_amt')");

        // 3. tbl_budget (Master)
        $sql_budget = "INSERT INTO tbl_budget (school_id, tenure_id, ip_address, created_by, created_on, STATUS) 
                       VALUES ('$school_id', '$tenure_id', '$ip_address', '$u_id', '$date', 'Active')";
        mysqli_query($conn, $sql_budget);
        $budget_id = mysqli_insert_id($conn);
        $sql_budget_log = "INSERT INTO tbl_budget_logs (school_id, budget_id, tenure_id, ip_address, created_by, created_on) 
                       VALUES ('$school_id', '$budget_id', '$tenure_id', '$ip_address', '$u_id', '$date')";
        mysqli_query($conn, $sql_budget_log);
        $budget_id2 = mysqli_insert_id($conn);

        // 4. Details...
        foreach ($headMapping as $index => $head_id) {
            $amt = isset($row[$index]) ? (float)str_replace(',', '', $row[$index]) : 0;
            if ($amt > 0) {
                mysqli_query($conn, "INSERT INTO tbl_budget_details (budget_id, head_id, amount, ip_address, created_on, STATUS) 
                                     VALUES ('$budget_id', '$head_id', '$amt', '$ip_address', '$date', 'Active')");
                // --- LOG ENTRY FOR BUDGET DETAILS ---
                $sql_detail_log = "INSERT INTO tbl_budget_details_logs (budget_id, head_id, amount, ip_address, created_on) 
                                     VALUES ('$budget_id2', '$head_id', '$amt', '$ip_address', '$date')";
                mysqli_query($conn, $sql_detail_log);
            }
        }
    }
    fclose($handle);
    return true;
}
