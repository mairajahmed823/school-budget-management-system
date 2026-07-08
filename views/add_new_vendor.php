<?php
require_once '../config/database.php';
require_once '../config/paths.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/VendorController.php';
checkUserAuth();

saveVendor($conn);

$types = mysqli_query($conn, "SELECT *, h.id AS h_id, h.code_no FROM tbl_heads AS h
INNER JOIN tbl_head_head_types AS hht ON h.id = hht.head_id
INNER JOIN tbl_head_type AS ht ON ht.id = hht.head_type_id
WHERE ht.type = 's' AND ht.item_type_status = 'Active'");

include '../includes/header.php';
include '../includes/navbar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<style>
    .form-container {
        padding: 40px 20px;
        max-width: 1100px;
        margin: auto;
    }

    .form-card {
        background: #fff;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .form-label {
        font-weight: 600;
        color: #000080;
        margin-bottom: 8px;
        display: block;
    }

    .custom-input {
        border-radius: 8px;
        padding: 12px;
        border: 1px solid #dee2e6;
        width: 100%;
        outline: none;
        margin-bottom: 20px;
        font-size: 14px;
    }

    textarea.custom-input {
        resize: vertical;
    }

    select[multiple].custom-input {
        min-height: 120px;
    }

    .btn-save-custom {
        background: #ff9800;
        color: white;
        border: none;
        padding: 15px;
        border-radius: 8px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
        font-size: 15px;
    }

    .btn-save-custom:hover {
        background: #e68a00;
    }

    .row-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
    }

    .full-width {
        grid-column: span 2;
    }

    .back-link {
        color: #000080;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        margin-top: 20px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .row-grid {
            grid-template-columns: 1fr;
        }

        .full-width {
            grid-column: span 1;
        }

        .form-card {
            padding: 25px;
        }
    }
</style>

<div class="form-container">
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 30px;">Add New Vendor</h4>

    <div class="form-card">
        <form action="" method="POST" enctype="multipart/form-data">

            <div class="row-grid">
                <div>
                    <label class="form-label">Dealing
                        <small style="color: #6c757d;">(Hold Ctrl to select multiple)</small>
                    </label>
                    <select id="vendor_item_type" name="item_type_ids[]" class="custom-input" multiple required>
                        <?php while ($t = mysqli_fetch_assoc($types)): ?>
                            <option value="<?php echo $t['h_id']; ?>">
                                <?php echo $t['code_no'] . " - " . $t['head_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Vendor Name</label>
                    <input type="text" name="vendor_name" class="custom-input" placeholder="Enter Vendor Name" required>

                    <div style="margin-top: 15px;">
                        <label class="form-label">Vendor No</label>
                        <input type="text" name="vendor_no" class="custom-input" placeholder="Enter Vendor Number" required>
                    </div>

                    <div style="margin: 10px 0;">
                        <label class="form-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="win_status" value="Winner" style="width: 18px; height: 18px;">
                            <span>Vendor Win?</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="row-grid">
                <div class="full-width">
                    <label class="form-label">Vendor Letterhead (PDF Format Only)</label>
                    <input type="file" name="vendor_letterhead" class="custom-input" accept="application/pdf">
                    <small style="color: red; position: relative; top: -15px;">Upload a blank PDF letterhead.</small>
                </div>
            </div>

            <div class="full-width">
                <label class="form-label">Vendor Logo</label>
                <input type="file" name="vendor_logo" class="custom-input" accept="image/*">
            </div>

            <div class="row-grid">
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="custom-input" placeholder="Name of person" required>
                </div>

                <div>
                    <label class="form-label">Phone No</label>
                    <input type="text" name="phone_no" class="custom-input" placeholder="e.g. 03001234567" required>
                </div>
            </div>

            <div class="full-width">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="custom-input" placeholder="Vendor Address" required>
            </div>

            <div class="full-width">
                <label class="form-label">Description</label>
                <textarea name="description" class="custom-input" rows="3" placeholder="Additional details..."></textarea>
            </div>

            <button type="submit" name="save" class="btn-save-custom">
                SAVE VENDOR
            </button>

            <div style="text-align:center;">
                <a href="manage_vendors.php" class="back-link">← Back to List</a>
            </div>

        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    const element = document.getElementById('vendor_item_type');
    const choices = new Choices(element, {
        removeItemButton: true,
        placeholder: true,
        placeholderValue: '-- Select Item Types --'
    });
</script>

<?php include '../includes/footer.php'; ?>