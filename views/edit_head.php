<?php
require_once '../config/database.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/HeadController.php';
checkUserAuth();

// Logic call
updateHead($conn);

if (!isset($_GET['id'])) {
    header("Location: manage_heads.php");
    exit();
}
$id = mysqli_real_escape_string($conn, $_GET['id']);

$edit_query = "SELECT * FROM tbl_heads WHERE id = '$id'";
$edit_result = mysqli_query($conn, $edit_query);
$data = mysqli_fetch_assoc($edit_result);

if (!$data) {
    die("Record not found!");
}

$current_selected_types = [];
$pivot_query = mysqli_query($conn, "SELECT head_type_id FROM tbl_head_head_types WHERE head_id = '$id'");
while ($p_row = mysqli_fetch_assoc($pivot_query)) {
    $current_selected_types[] = $p_row['head_type_id'];
}

$types = mysqli_query($conn, "SELECT * FROM tbl_head_type");
include '../includes/header.php';
include '../includes/navbar.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />


<style>
    .form-container {
        padding: 40px;
        max-width: 700px;
        margin: auto;
    }

    .form-card {
        background: #fff;
        padding: 35px;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .form-label {
        font-weight: 600 !important;
        color: #000080 !important;
        margin-bottom: 12px !important;
        display: block !important;
        font-size: 15px !important;
    }

    .custom-input {
        width: 100% !important;
        display: block !important;
        border-radius: 8px !important;
        padding: 14px !important;
        border: 1px solid #dee2e6 !important;
        margin-bottom: 25px !important;
        background: #fafafa !important;
        font-size: 14px !important;
        box-sizing: border-box !important;
    }

    .btn-update-custom {
        background: #ff9800 !important;
        color: white !important;
        border: none !important;
        padding: 16px !important;
        border-radius: 8px !important;
        width: 100% !important;
        font-weight: bold !important;
        font-size: 16px !important;
        cursor: pointer !important;
        text-transform: uppercase !important;
    }

    .btn-update-custom:hover {
        background: #e68a00 !important;
    }

    .back-link {
        color: #000080 !important;
        text-decoration: none !important;
        font-weight: 600 !important;
        display: block !important;
        text-align: center;
        margin-top: 25px !important;
    }

    .btn-group,
    .multiselect-container,
    .multiselect {
        width: 100% !important;
        margin-bottom: 25px !important;
    }
</style>

<div class="form-container">
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 25px;">Edit Head Details</h4>
    <div class="form-card">
        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label">Head Types (Multiple Selection)</label>
                <select id="head-type-select" name="head_type_ids[]" multiple="multiple" required>
                    <?php while ($t = mysqli_fetch_assoc($types)):
                        $selected = in_array($t['id'], $current_selected_types) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $selected; ?>>
                            <?php echo $t['head_type_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Head Category <span class="required">*</span></label>
                <div style="display: flex; gap: 20px; margin-top: 5px;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="head_category" value="physical" <?php echo ($data['head_category'] == 'physical') ? 'checked' : ''; ?> required> Physical Assets
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="head_category" value="service" <?php echo ($data['head_category'] == 'service') ? 'checked' : ''; ?> required> Service
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="head_category" value="tax_free" <?php echo ($data['head_category'] == 'tax_free') ? 'checked' : ''; ?> required> Tax Free
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Head Name</label>
                <input type="text" name="head_name" class="custom-input" value="<?php echo $data['head_name']; ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="custom-input">
                    <option value="Active" <?php echo ($data['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($data['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" name="update" class="btn-update-custom">UPDATE CHANGES</button>
            <a href="manage_heads.php" class="back-link">← Back to Heads List</a>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    $(document).ready(function() {
        console.log('jQuery loaded:', typeof $ !== 'undefined');
        console.log('Select2 loaded:', typeof $.fn.select2 !== 'undefined');
    });
    const element = document.getElementById('head-type-select');
    const choices = new Choices(element, {
        removeItemButton: true,
        placeholder: true,
        placeholderValue: '-- Select Head Types --'
    });
</script>
<?php include '../includes/footer.php'; ?>