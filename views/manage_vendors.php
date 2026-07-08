<?php
require_once '../config/database.php';
require_once '../config/paths.php'; // Path include lazmi hai
require_once '../controllers/AuthController.php';
checkUserAuth();

$query = "SELECT 
            v.id, 
            v.vendor_name, 
            v.vendor_letterhead,
            v.contact_person, 
            v.status,
            GROUP_CONCAT(h.head_name SEPARATOR ', ') AS dealing_items
          FROM tbl_vendor v 
          LEFT JOIN tbl_vendor_item_type vit ON v.id = vit.vendor_id
          LEFT JOIN tbl_heads h ON vit.item_type_id = h.id 
          GROUP BY v.id
          ORDER BY v.id DESC";

$result = mysqli_query($conn, $query);
$page_title = 'Manage Vendors';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    :root {
        --primary-blue: #3f51b5;
        --bg-light: #f4f7fe;
        --text-dark: #2d3748;
        --text-muted: #718096;
        --border-color: #edf2f7;
    }

    /* Body Background & Font */
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

    /* Button Styling */
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

    /* Table Container Card */
    .table-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        overflow: hidden;
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

    /* Dealing Items Badges */
    .item-badge {
        background: #eef2ff;
        color: #4f46e5;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        border: 1px solid #e0e7ff;
        display: inline-block;
        margin: 2px;
    }

    /* Status Badge */
    .status-badge {
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* Success Status */
    .status-badge.success {
        background: #dcfce7;
        color: #166534;
    }

    /* Danger Status */
    .status-badge.danger {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Action Link */
    .edit-link {
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
    }

    .edit-link:hover {
        text-decoration: underline;
    }

    .vendor-name {
        font-weight: 600;
        color: #1a202c;
    }
</style>

<div class="content-wrapper">
    <div class="header-row">
        <h2 class="page-title">Manage Vendors</h2>
        <a href="add_new_vendor.php" class="btn-add">+ Add New Vendor</a>
    </div>

    <div class="table-card">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 50px;">S.No</th>
                    <th>Vendor Name</th>
                    <th>Letterhead</th>
                    <th>Contact</th>
                    <th style="width: 30%;">Dealing Items</th>
                    <th>Status</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sno = 1;
                if (mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)):
                ?>
                        <tr>
                            <td><?php echo $sno++; ?></td>
                            <td><span class="vendor-name"><?php echo $row['vendor_name']; ?></span></td>
                            <td>
                                <?php if (!empty($row['vendor_letterhead'])): ?>
                                    <a href="<?php echo LETTERHEAD_URL . $row['vendor_letterhead']; ?>" target="_blank" style="text-decoration:none; color: #ef4444;">
                                        PDF 📄
                                    </a>
                                <?php else: ?>
                                    <small class="text-muted">No File</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $row['contact_person']; ?></td>
                            <td>
                                <?php
                                if (!empty($row['dealing_items'])) {
                                    $items = explode(',', $row['dealing_items']);
                                    foreach ($items as $item) {
                                        echo "<span class='item-badge'>" . trim($item) . "</span>";
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo ($row['status'] == 'Active') ? 'success' : 'danger'; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 10px; justify-content: center;">
                                    <a href="edit_vendor.php?id=<?php echo $row['id']; ?>" class="edit-link">Edit</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>