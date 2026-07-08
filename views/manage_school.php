<?php
require_once '../config/database.php';
require_once '../includes/crypto.php';

require_once '../controllers/AuthController.php';
require_once '../controllers/ExportSchoolsController.php';
checkUserAuth();

// --- Bulk Delete Logic Start ---
if (isset($_POST['bulk_delete']) && !empty($_POST['school_ids'])) {
    $ids_to_delete = $_POST['school_ids'];

    $escaped_ids = array_map(function ($id) use ($conn) {
        return mysqli_real_escape_string($conn, $id);
    }, $ids_to_delete);

    $id_list = "'" . implode("','", $escaped_ids) . "'";

    mysqli_query($conn, "DELETE FROM tbl_users WHERE school_id IN ($id_list)");

    $query_del = "DELETE FROM tbl_manage_school WHERE id IN ($id_list)";

    if (mysqli_query($conn, $query_del)) {
        echo "<script>alert('Selected schools deleted successfully!'); window.location.href='manage_school.php';</script>";
    }
}
// --- Bulk Delete Logic End ---

// Search Logic
$where_clauses = ["STATUS = 'Active'"];

if ($_SESSION['role'] !== 'admin') {
    $s_id = $_SESSION['school_id'];
    $where_clauses[] = "id = '$s_id'";
}

if (isset($_GET['search_semis']) && !empty($_GET['search_semis'])) {
    $search_semis = mysqli_real_escape_string($conn, $_GET['search_semis']);
    $where_clauses[] = "semis_code LIKE '%$search_semis%'"; // Column name check karlein table mein
}

if (isset($_GET['search_code']) && !empty($_GET['search_code'])) {
    $search_code = mysqli_real_escape_string($conn, $_GET['search_code']);
    $where_clauses[] = "school_code LIKE '%$search_code%'";
}

if (isset($_GET['search_district']) && !empty($_GET['search_district'])) {
    $search_dist = mysqli_real_escape_string($conn, $_GET['search_district']);
    $where_clauses[] = "district = '$search_dist'";
}

$dist_query = "SELECT DISTINCT district FROM tbl_manage_school WHERE district IS NOT NULL AND district != '' ORDER BY district ASC";
$dist_result = mysqli_query($conn, $dist_query);

$where_sql = implode(' AND ', $where_clauses);
$query = "SELECT * FROM tbl_manage_school WHERE $where_sql ORDER BY id DESC";
$result = mysqli_query($conn, $query);
if (isset($_POST['upload_csv'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (importSchoolCSV($conn, $file)) { // Seedha function call
        echo "<script>alert('Data Uploaded!'); window.location.href='manage_school.php';</script>";
    }
}

include '../includes/header.php';
include '../includes/navbar.php';

if (isset($_GET['action']) && $_GET['action'] == 'clear_data' && isset($_GET['school_id'])) {
    $school_id = mysqli_real_escape_string($conn, $_GET['school_id']);

    // 1. Delete from tbl_requisition
    $del_req = "DELETE FROM tbl_requisition WHERE school_id = '$school_id'";
    mysqli_query($conn, $del_req);

    // 2. Delete from tbl_quotation
    $del_quot = "DELETE FROM tbl_quotation WHERE school_id = '$school_id'";
    mysqli_query($conn, $del_quot);

    $del_outward_no = "DELETE FROM tbl_outward_no WHERE school_id = '$school_id'";
    mysqli_query($conn, $del_outward_no);

    echo "<script>alert('Requisition and Quotation data deleted successfully!'); window.location.href='manage_school.php';</script>";
}

// Delete School Logic
if (isset($_GET['action']) && $_GET['action'] == 'delete_school' && isset($_GET['school_id'])) {
    $school_id = mysqli_real_escape_string($conn, $_GET['school_id']);

    $del_school = "DELETE FROM tbl_manage_school WHERE id = '$school_id'";
    $del_users = "DELETE FROM tbl_users WHERE school_id = '$school_id'";
    mysqli_query($conn, $del_users);
    if (mysqli_query($conn, $del_school)) {
        echo "<script>alert('School deleted successfully!'); window.location.href='manage_school.php';</script>";
    } else {
        echo "<script>alert('Error deleting record!');</script>";
    }
}

?>

<style>
    :root {
        --primary-dark: #2c3e50;
        /* Sidebar color style */
        --dashboard-blue: #3f51b5;
        /* Header/Button blue */
        --accent-orange: #ff9800;
        /* Sidebar active highlight */
        --bg-light: #f4f7fe;
        --text-main: #2d3748;
        --text-muted: #718096;
    }

    body {
        background-color: var(--bg-light);
        font-family: 'Poppins', sans-serif;
        /* Professional dashboard font */
    }

    .content-wrapper {
        padding: 30px;
    }

    /* Heading Section */
    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .page-title {
        color: #1a202c;
        font-weight: 700;
        font-size: 24px;
        margin: 0;
    }

    /* Primary Button (Matching your image) */
    .btn-add {
        background: var(--dashboard-blue);
        color: white;
        padding: 10px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: 0.3s;
        box-shadow: 0 4px 15px rgba(63, 81, 181, 0.3);
    }

    .btn-add:hover {
        background: #303f9f;
        box-shadow: none;
    }

    /* White Card Container */
    .table-container {
        background: #ffffff;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
    }

    /* Table Headers */
    .custom-table th {
        color: var(--text-muted);
        text-transform: uppercase;
        font-size: 12px;
        font-weight: 600;
        padding: 15px;
        border-bottom: 1px solid #edf2f7;
        text-align: left;
    }

    /* Table Rows */
    .custom-table td {
        padding: 18px 15px;
        color: var(--text-main);
        font-size: 14px;
        border-bottom: 1px solid #edf2f7;
    }

    .custom-table tr:last-child td {
        border-bottom: none;
    }

    /* Status Badge (Green/Active) */
    .status-active {
        background: #e6fffa;
        color: #38b2ac;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* Action Links */
    .action-edit {
        color: var(--dashboard-blue);
        text-decoration: none;
        font-weight: 600;
        margin-right: 15px;
    }

    .action-budget {
        color: #00acc1;
        text-decoration: none;
        font-weight: 600;
    }

    .action-edit:hover,
    .action-budget:hover {
        text-decoration: underline;
    }

    .school-code {
        font-weight: 700;
        color: #1a202c;
    }

    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropbtn {
        background-color: #3498db;
        color: white;
        padding: 6px 12px;
        font-size: 14px;
        border: none;
        cursor: pointer;
        border-radius: 4px;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: #fff;
        min-width: 140px;
        box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        z-index: 1;
        border-radius: 4px;
    }

    .dropdown-content a {
        color: black;
        padding: 8px 12px;
        text-decoration: none;
        display: block;
    }

    .dropdown-content a:hover {
        background-color: #f1f1f1;
    }

    .dropdown:hover .dropdown-content {
        display: block;
    }

    .search-container {
        background: #fff;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        display: flex;
        gap: 15px;
        align-items: flex-end;
    }

    .search-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .search-input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        outline: none;
        font-size: 14px;
    }

    .btn-search {
        background: var(--dashboard-blue);
        color: white;
        border: none;
        padding: 9px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-clear {
        background: #e2e8f0;
        color: #4a5568;
        text-decoration: none;
        padding: 9px 20px;
        border-radius: 6px;
        font-size: 14px;
    }
</style>

<div class="content-wrapper">
    <?php if ($_SESSION['role'] !== 'admin' && isset($result) && mysqli_num_rows($result) > 0):
        // Pehli row se school name nikalne ke liye (sirf user ke liye)
        $temp_res = mysqli_query($conn, $query);
        $school_data = mysqli_fetch_assoc($temp_res);
    ?>
        <div class="welcome-banner" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 5px solid var(--primary-color); border-radius: 4px;">
            <h1 style="margin: 5px 0 0 0; font-size: 24px; color: var(--primary-color); line-height: 1.2; word-wrap: break-word;">
                <?php echo "Welcome, " . $school_data['school_name']; ?>
            </h1>
        </div>
    <?php endif; ?>

    <div class="header-row">
        <h2 class="page-title">Manage Institute</h2>
        <div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <form action="" method="POST" enctype="multipart/form-data" style="display:inline-block; margin-right:10px;">
                    <input type="file" name="csv_file" accept=".csv" required>
                    <button type="submit" name="upload_csv" class="btn-add">Import CSV</button>
                </form>
                <a href="add_new_school.php" class="btn-add">+ Add New Institute</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($_SESSION['role'] === 'admin'): ?>

        <form method="GET" action="" class="search-container">
            <div class="search-group">
                <label style="font-size: 12px; font-weight: 600; color: var(--text-muted);">SEMIS CODE</label>
                <input type="text" name="search_semis" class="search-input" placeholder="Search SEMIS code..." value="<?php echo isset($_GET['search_semis']) ? $_GET['search_semis'] : ''; ?>">
            </div>
            <div class="search-group">
                <label style="font-size: 12px; font-weight: 600; color: var(--text-muted);">Cost Center</label>
                <input type="text" name="search_code" class="search-input" placeholder="Enter code..." value="<?php echo isset($_GET['search_code']) ? $_GET['search_code'] : ''; ?>">
            </div>
            <div class="search-group">
                <label style="font-size: 12px; font-weight: 600; color: var(--text-muted);">District</label>
                <select name="search_district" class="search-input" style="height: 38px;">
                    <option value="">Search District</option>
                    <?php while ($dist = mysqli_fetch_assoc($dist_result)): ?>
                        <option value="<?= $dist['district'] ?>" <?= (isset($_GET['search_district']) && $_GET['search_district'] == $dist['district']) ? 'selected' : '' ?>>
                            <?= $dist['district'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <button type="submit" class="btn-search">Search</button>
            <?php if (isset($_GET['search_name']) || isset($_GET['search_code'])): ?>
                <a href="manage_school.php" class="btn-clear">Clear</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <form action="" method="POST" id="bulkForm" onsubmit="return confirm('Are you sure?')">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <button type="submit" name="bulk_delete" id="deleteBtn" style="display:none;" class="btn-add mb-2">Delete Selected Schools</button>
        <?php endif; ?>
        <div class="table-container">
            <table class="custom-table">
                <thead>
                    <tr>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <th><input type="checkbox" id="selectAll"></th>
                        <?php endif; ?>

                        <th>S.No</th>
                        <th>SEMIS Code</th>
                        <th>Cost Center</th>
                        <th>Institute Name</th>
                        <th>District Name</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sno = 1;
                    if (mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)):
                    ?>
                            <tr>
                                <?php if ($_SESSION['role'] === 'admin'): ?>

                                    <td><input type="checkbox" name="school_ids[]" value="<?= $row['id'] ?>" class="school-cb"></td>
                                <?php endif; ?>

                                <td style="color: var(--text-muted);"><?php echo $sno++; ?></td>
                                <td><strong><?php echo $row['semis_code']; ?></strong></td>
                                <td><span class="school-code"><?php echo $row['school_code']; ?></span></td>
                                <td><?php echo $row['school_name']; ?></td>
                                <td><?php echo $row['district']; ?></td>
                                <td>
                                    <span class="status-active"><?php echo $row['STATUS']; ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="dropdown">
                                        <button class="dropbtn">Actions ▼</button>
                                        <div class="dropdown-content">
                                            <a href="edit_school.php?id=<?php echo urlencode(encrypt_id($row['id'])); ?>">Edit</a>
                                            <a href="view_budget.php?id=<?php echo urlencode(encrypt_id($row['id'])); ?>">View Budget</a>
                                            <a href="add_budget.php?id=<?php echo urlencode(encrypt_id($row['id'])); ?>">Add Budget</a>
                                            <a href="#" onclick="openAutoProcess(<?= $row['id']; ?>)">Auto Process Budget</a>
                                            <a href="#" onclick="openRemainingProcess(<?= $row['id']; ?>)">Process Remaining Budget</a>
                                            <a href="#" onclick="openBulkDownload('<?= urlencode(encrypt_id($row['id'])) ?>', 'with')">Download all Docs</a>
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <a href="#" onclick="openBulkDownload('<?= urlencode(encrypt_id($row['id'])) ?>', 'without')">Download Docs WithOut LetterHead</a>
                                            <?php endif; ?>
                                            <a href="#" onclick="clearSchoolData(<?= $row['id']; ?>)" style="color: red; font-weight: bold;">Clear Records</a>

                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <a href="#" onclick="deleteSchool(<?= $row['id']; ?>)" style="color: #d32f2f;">Delete Institute</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 50px; color: var(--text-muted);">
                                No schools found in the system.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<script>
    // Select All toggle
    document.getElementById('selectAll').addEventListener('change', function() {
        let boxes = document.querySelectorAll('.school-cb');
        boxes.forEach(box => box.checked = this.checked);
        toggleBtn();
    });

    // Checkbox click par button dikhana
    document.querySelectorAll('.school-cb').forEach(box => {
        box.addEventListener('change', toggleBtn);
    });

    function toggleBtn() {
        let checkedCount = document.querySelectorAll('.school-cb:checked').length;
        document.getElementById('deleteBtn').style.display = checkedCount > 0 ? 'inline-block' : 'none';
    }

    function openAutoProcess(schoolId) {
        showTenurePicker(schoolId, 'process');
    }

    function openBulkDownload(schoolId, letterheadType) {
        // Ab hum type mein 'download_with' ya 'download_without' bhejenge
        showTenurePicker(schoolId, 'download_' + letterheadType);
    }

    function clearSchoolData(schoolId) {
        if (confirm("Are You Sure You Want To Delete?")) {
            window.location.href = "manage_school.php?action=clear_data&school_id=" + schoolId;
        }
    }

    function deleteSchool(schoolId) {
        if (confirm("Are you sure you want to permanently delete this school? This action cannot be undone.")) {
            window.location.href = "manage_school.php?action=delete_school&school_id=" + schoolId;
        }
    }

    function openRemainingProcess(schoolId) {
        showTenurePicker(schoolId, 'remaining'); // Naya type 'remaining' pass kiya
    }

    // Yeh function dropdown banaye ga screen par
    function showTenurePicker(schoolId, type) {
        let existing = document.getElementById('temp-picker');
        if (existing) existing.remove();

        // 1. HTML mein Date Input add kiya gaya
        let pickerHtml = `
         <div id="temp-picker" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:20px; border:2px solid #333; z-index:9999; box-shadow:0px 0px 15px rgba(0,0,0,0.5); border-radius:8px; min-width:300px;">
        <h4 style="margin-top:0;">Select Details</h4>
        
        <label>Tenure:</label>
        <select id="t_select" style="width:100%; padding:10px; margin-bottom:15px; font-size:16px;">
            <option value="">-- Choose Tenure --</option>
            <?php
            $t_res = mysqli_query($conn, "SELECT tenure FROM tbl_tenure ORDER BY id ASC");
            while ($t_row = mysqli_fetch_assoc($t_res)) {
                echo "<option value='{$t_row['tenure']}'>{$t_row['tenure']}</option>";
            }
            ?>
        </select>

        ${(type === 'process' || type === 'remaining') ? `
<label>Starting Date (for Requisition):</label>
<input type="date" id="start_date" value="<?= date('Y-m-d'); ?>" style="width:100%; padding:10px; margin-bottom:15px; font-size:16px;">
` : ''}

        <div style="text-align:right;">
            <button onclick="document.getElementById('temp-picker').remove()" style="padding:5px 15px; cursor:pointer;">Cancel</button>
            <button id="proceed-btn" style="padding:5px 15px; cursor:pointer; background:#007bff; color:white; border:none; margin-left:5px;">Proceed</button>
        </div>
       </div>
      `;

        document.body.insertAdjacentHTML('beforeend', pickerHtml);

        document.getElementById('proceed-btn').onclick = function() {
            let tenure = document.getElementById('t_select').value;
            if (tenure == "") {
                alert("Please select tenure!");
                return;
            }

            // Date value yahan nikaalni hai taake dono conditions mein use ho sake
            let startDate = (document.getElementById('start_date')) ? document.getElementById('start_date').value : '';

            if (type === 'process') {
                if (startDate == "") {
                    alert("Please select a starting date!");
                    return;
                }
                if (confirm("Confirm process for " + tenure + " starting from " + startDate + "?")) {
                    window.location.href = "../controllers/AutoProcessController.php?action=auto_generate&school_id=" + schoolId + "&tenure=" + tenure + "&start_date=" + startDate;
                }
                // manage_school.php ke script tag mein jayein
            } else if (type === 'remaining') {
                if (startDate == "") {
                    alert("Please select a date!");
                    return;
                }
                if (confirm("Confirm process for remaining budget for " + tenure + " starting from " + startDate + "?")) {
                    // YAHAN CHANGE KAREIN: action ko 'process_remaining' kar dein
                    window.location.href = "../controllers/RemainingBudgetController.php?action=process_remaining&school_id=" + schoolId + "&tenure=" + tenure + "&start_date=" + startDate;
                }
            } else if (type === 'download_with') {
                // Letterhead ke sath
                window.open('bulk_download.php?school_id=' + schoolId + '&tenure=' + tenure, '_blank');
            } else if (type === 'download_without') {
                // WITHOUT Letterhead (Yahan aapki new file ka naam aayega)
                window.open('download_docs_without_letterhead.php?school_id=' + schoolId + '&tenure=' + tenure, '_blank');
            }
            document.getElementById('temp-picker').remove();
        };
    }
</script>

<?php include '../includes/footer.php'; ?>