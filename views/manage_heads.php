<?php
require_once '../config/database.php';

require_once '../controllers/AuthController.php';
checkUserAuth();

$search_name = isset($_GET['search_name']) ? mysqli_real_escape_string($conn, trim($_GET['search_name'])) : '';
$search_code = isset($_GET['search_code']) ? mysqli_real_escape_string($conn, trim($_GET['search_code'])) : '';

$query = "SELECT h.id, h.head_name, h.code_no, h.status,
          GROUP_CONCAT(ht.head_type_name SEPARATOR ', ') AS all_head_types
          FROM tbl_heads h 
          LEFT JOIN tbl_head_head_types hht ON h.id = hht.head_id 
          LEFT JOIN tbl_head_type ht ON hht.head_type_id = ht.id
          WHERE h.status = 'Active'";

// Agar dono mein se kisi bhi box mein data ho
if ($search_name != '' || $search_code != '') {
    $query .= " AND (";

    $conditions = [];
    if ($search_name != '') {
        $conditions[] = "h.head_name LIKE '%$search_name%'";
    }
    if ($search_code != '') {
        $conditions[] = "h.code_no LIKE '%$search_code%'";
    }

    // Yahan 'OR' use karne se dono mein se koi bhi match milne par result aa jayega
    $query .= implode(" OR ", $conditions);

    $query .= ")";
}

$query .= " GROUP BY h.id ORDER BY h.id DESC";

$result = mysqli_query($conn, $query);

$page_title = 'Manage Heads';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    :root {
        --primary-blue: #3f51b5;
        /* Dashboard Blue */
        --bg-light: #f4f7fe;
        --text-dark: #2d3748;
        --text-muted: #718096;
        --border-color: #edf2f7;
    }

    body {
        background-color: var(--bg-light);
        font-family: 'Poppins', sans-serif;
    }

    .content-wrapper {
        padding: 30px;
    }

    /* Header Styling */
    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .page-title {
        color: var(--text-dark);
        font-weight: 700;
        font-size: 24px;
        margin: 0;
    }

    /* Primary Button Styling */
    .btn-add {
        background: var(--primary-blue);
        color: white;
        padding: 10px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: 0.3s;
        box-shadow: 0 4px 12px rgba(63, 81, 181, 0.2);
    }

    .btn-add:hover {
        background: #303f9f;
        box-shadow: none;
        color: white;
    }

    /* Card Container */
    .table-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
    }

    .custom-table th {
        color: var(--text-muted);
        text-transform: uppercase;
        font-size: 12px;
        font-weight: 600;
        padding: 15px;
        border-bottom: 2px solid var(--border-color);
        text-align: left;
    }

    .custom-table td {
        padding: 16px 15px;
        color: var(--text-dark);
        font-size: 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    /* Head Type Badge */
    .type-badge {
        background: #f1f5f9;
        color: #475569;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
    }

    /* Status Badge */
    .status-badge {
        background: #dcfce7;
        /* Light Green */
        color: #166534;
        /* Dark Green */
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* Action Link */
    .edit-link {
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 600;
    }

    .edit-link:hover {
        text-decoration: underline;
    }

    .head-name {
        font-weight: 600;
        color: #1a202c;
    }
</style>

<div class="content-wrapper">
    <div class="header-row">
        <h2 class="page-title">Manage Heads</h2>
        <a href="add_new_head.php" class="btn-add">+ Add New Head</a>
    </div>

    <div class="filter-card" style="background: #fff; padding: 15px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
        <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">

            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px;">Head Name</label>
                <input type="text" name="search_name"
                    placeholder="Enter name..."
                    value="<?php echo htmlspecialchars($search_name); ?>"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; outline: none; font-size: 14px;">
            </div>

            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px;">Head Code</label>
                <input type="text" name="search_code"
                    placeholder="Enter code..."
                    value="<?php echo htmlspecialchars($search_code); ?>"
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; outline: none; font-size: 14px;">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-add" style="box-shadow: none; height: 42px;">Search</button>

                <?php if ($search_name != '' || $search_code != ''): ?>
                    <a href="manage_heads.php" class="btn-add" style="background: #e2e8f0; color: #475569; box-shadow: none; height: 42px; display: flex; align-items: center; justify-content: center;">Reset</a>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <div class="table-card">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 80px;">S.No</th>
                    <th>Heads Name</th>
                    <th>Heads Type</th>
                    <th>Status</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sno = 1;
                while ($row = mysqli_fetch_assoc($result)):
                ?>
                    <tr>
                        <td style="color: var(--text-muted);"><?php echo $sno++; ?></td>
                        <td><span class="head-name"><?php echo $row['code_no'] . ' - ' . $row['head_name']; ?></span></td>
                        <td>
                            <span class="type-badge">
                                <?php echo $row['all_head_types'] ? $row['all_head_types'] : 'N/A'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge"><?php echo $row['status']; ?></span>
                        </td>
                        <td style="text-align: center;">
                            <a href="edit_head.php?id=<?php echo $row['id']; ?>" class="edit-link">Edit</a>
                        </td>
                    </tr>
                <?php endwhile; ?>

                <?php if (mysqli_num_rows($result) == 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            No heads found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>