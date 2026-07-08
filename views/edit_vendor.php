<?php
require_once '../config/database.php';
require_once '../config/paths.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/VendorController.php';
checkUserAuth();

if (!isset($_GET['id'])) {
    header("Location: manage_vendors.php");
    exit();
}
$id = mysqli_real_escape_string($conn, $_GET['id']);

$edit_query = "SELECT * FROM tbl_vendor WHERE id = '$id'";
$edit_result = mysqli_query($conn, $edit_query);
$data = mysqli_fetch_assoc($edit_result);

if (!$data) {
    die("Vendor record not found!");
}

// Pehle sirf 2 parameters thay, ab 3 ho jayenge
updateVendor($conn, $data['vendor_letterhead'], $data['vendor_logo']);

$current_items = [];
$item_fetch = mysqli_query($conn, "SELECT item_type_id FROM tbl_vendor_item_type WHERE vendor_id = '$id'");
while ($row = mysqli_fetch_assoc($item_fetch)) {
    $current_items[] = $row['item_type_id'];
}

$types = mysqli_query($conn, "SELECT *, h.id AS h_id FROM tbl_heads AS h
INNER JOIN tbl_head_head_types AS hht ON h.id = hht.head_id
INNER JOIN tbl_head_type AS ht ON ht.id = hht.head_type_id
WHERE ht.type = 's' AND ht.item_type_status = 'Active'");

$page_title = 'Edit Vendor';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<style>
    .form-container {
        padding: 40px;
        max-width: 1000px;
        margin: auto;
    }

    .form-card {
        background: #ffffff;
        padding: 35px;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .form-label {
        font-weight: 600;
        color: #000080;
        margin-bottom: 6px;
        display: block;
        font-size: 14px;
    }

    .custom-input {
        border-radius: 8px;
        padding: 10px 12px;
        border: 1px solid #dee2e6;
        width: 100%;
        box-sizing: border-box;
        outline: none;
        font-size: 14px;
        color: #333;
    }

    .custom-input:focus {
        border-color: #000080;
    }

    textarea.custom-input {
        resize: vertical;
    }

    .field-group {
        margin-bottom: 20px;
    }

    /* Two column grid - equal columns */
    .row-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 0;
    }

    /* Full width row */
    .row-full {
        margin-bottom: 0;
    }

    /* Letterhead box */
    .logo-wrapper {
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 12px 15px;
        background: #fafafa;
        margin-bottom: 6px;
        font-size: 14px;
        min-height: 44px;
        display: flex;
        align-items: center;
    }

    .logo-wrapper a {
        color: #000080;
        text-decoration: underline;
        word-break: break-all;
    }

    /* Win checkbox row */
    .checkbox-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        margin-bottom: 20px;
    }

    .checkbox-row input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .checkbox-row span {
        font-weight: bold;
        color: #d9534f;
        font-size: 14px;
    }

    /* Submit button */
    .btn-save-custom {
        background: #ff9800;
        color: white;
        border: none;
        padding: 14px;
        border-radius: 8px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
        text-transform: uppercase;
        font-size: 15px;
        margin-top: 10px;
    }

    .btn-save-custom:hover {
        background: #e68a00;
    }

    .back-link {
        color: #000080;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        margin-top: 15px;
    }

    /* Choices.js overrides to match form height */
    .choices {
        margin-bottom: 0;
    }

    .choices__inner {
        border-radius: 8px !important;
        border: 1px solid #dee2e6 !important;
        padding: 6px 10px !important;
        min-height: 44px !important;
        font-size: 14px !important;
        background: #fff !important;
    }

    .choices__input {
        font-size: 14px !important;
    }
</style>

<div class="form-container">
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 25px;">Edit Vendor Details</h4>
    <div class="form-card">
        <form action="" method="POST" enctype="multipart/form-data">

            <div class="row-grid" style="margin-bottom: 20px;">
                <div class="field-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        Dealing <small style="color: #6c757d; font-weight: 400;">(Hold Ctrl to select/unselect)</small>
                    </label>
                    <select id="edit-multi-select" name="item_type_ids[]" class="custom-input" multiple="multiple">
                        <?php while ($t = mysqli_fetch_assoc($types)):
                            $selected = in_array($t['h_id'], $current_items) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $t['h_id']; ?>" <?php echo $selected; ?>>
                                <?php echo $t['head_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="field-group" style="margin-bottom: 0;">
                    <label class="form-label">Vendor Name</label>
                    <input type="text" name="vendor_name" class="custom-input"
                        value="<?php echo htmlspecialchars($data['vendor_name']); ?>" required>

                    <div style="margin-top: 15px;">
                        <label class="form-label">Vendor No</label>
                        <input type="text" name="vendor_no" class="custom-input"
                            value="<?php echo htmlspecialchars($data['vendor_no']); ?>" placeholder="Enter Vendor Number" required>
                    </div>
                </div>
            </div>

            <div class="row-grid" style="margin-bottom: 20px;">
                <div class="checkbox-row" style="margin-bottom: 0;">
                    <input type="checkbox" name="win_status" value="Winner"
                        <?php echo ($data['win_status'] == 'Winner') ? 'checked' : ''; ?>>
                    <span>Vendor Win?</span>
                </div>

                <div class="field-group" style="margin-bottom: 0;">
                    <label class="form-label">Status</label>
                    <select name="status" class="custom-input">
                        <option value="Active" <?php echo ($data['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($data['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="row-grid" style="margin-bottom: 20px;">
                <div>
                    <div class="field-group">
                        <label class="form-label">Current Letterhead PDF</label>
                        <div class="logo-wrapper">
                            <?php if (!empty($data['vendor_letterhead'])): ?>
                                <a href="<?php echo LETTERHEAD_URL . $data['vendor_letterhead']; ?>" target="_blank">
                                    📄 <?php echo $data['vendor_letterhead']; ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #999;">No Letterhead Uploaded</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="field-group" style="margin-bottom: 0;">
                        <label class="form-label">Update Letterhead (PDF Only)</label>
                        <input type="file" name="vendor_letterhead" class="custom-input">
                    </div>
                </div>

                <div>
                    <div class="field-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="custom-input"
                            value="<?php echo htmlspecialchars($data['contact_person']); ?>" required>
                    </div>
                    <div class="field-group" style="margin-bottom: 0;">
                        <label class="form-label">Phone No</label>
                        <input type="text" name="phone_no" class="custom-input"
                            value="<?php echo htmlspecialchars($data['phone_no']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="field-group">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="custom-input"
                    value="<?php echo htmlspecialchars($data['address']); ?>" required>
            </div>

            <div class="field-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="custom-input" rows="3"><?php echo htmlspecialchars($data['description']); ?></textarea>
            </div>

            <div class="row-grid" style="margin-bottom: 20px;">
                <!-- Letterhead Section -->
                <div>
                    <label class="form-label">Vendor Letterhead (PDF)</label>
                    <div style="margin-bottom: 10px;">
                        <?php if (!empty($data['vendor_letterhead'])): ?>
                            <a href="<?php echo LETTERHEAD_URL . $data['vendor_letterhead']; ?>" target="_blank" style="font-size: 13px; color: #007bff; text-decoration: none;">
                                📄 View Current PDF
                            </a>
                        <?php else: ?>
                            <small style="color: #999;">No Letterhead</small>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="vendor_letterhead" class="custom-input">
                </div>

                <!-- Logo Section -->
                <div>
                    <label class="form-label">Vendor Logo (Image)</label>
                    <div style="margin-bottom: 10px;">
                        <?php if (!empty($data['vendor_logo'])): ?>
                            <img src="../uploads/vendor_logos/<?php echo $data['vendor_logo']; ?>" style="height: 20px; width: auto; border-radius: 3px; vertical-align: middle;">
                            <small style="color: #28a745; margin-left: 5px;">Logo Uploaded</small>
                        <?php else: ?>
                            <small style="color: #999;">No Logo</small>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="vendor_logo" class="custom-input">
                </div>
            </div>

            <button type="submit" name="update" class="btn-save-custom">Update Vendor Information</button>
            <div style="text-align: center;">
                <a href="manage_vendors.php" class="back-link">← Back to List</a>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    const element = document.getElementById('edit-multi-select');
    const choices = new Choices(element, {
        removeItemButton: true,
        placeholder: true,
        placeholderValue: '-- Select Item Types --'
    });
</script>

<?php include '../includes/footer.php'; ?>