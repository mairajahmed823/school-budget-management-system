<?php
require_once '../config/database.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/HeadController.php';
checkUserAuth();

// Logic call
saveHead($conn);

$types = mysqli_query($conn, "SELECT * FROM tbl_head_type");
$page_title = 'Add New Head';
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
        font-weight: 600;
        color: #000080;
        margin-bottom: 10px;
        display: block;
        font-size: 15px;
    }

    .form-label .required {
        color: #ff5252;
        margin-left: 2px;
    }

    .custom-input {
        border-radius: 8px;
        padding: 14px;
        border: 1px solid #dee2e6;
        margin-bottom: 25px;
        width: 100%;
        outline: none;
        background: #fafafa;
        transition: 0.3s;
        font-size: 14px;
    }

    .custom-input:focus {
        border-color: #ff9800;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
    }

    /* ===== SELECT2 STYLING ===== */
    .select2-container {
        width: 100% !important;
        margin-bottom: 25px;
    }

    .select2-container--default .select2-selection--multiple {
        border: 1px solid #dee2e6 !important;
        border-radius: 8px !important;
        padding: 6px 8px !important;
        background: #fafafa !important;
        min-height: 50px !important;
        cursor: pointer;
    }

    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #ff9800 !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1) !important;
        outline: none !important;
    }

    /* Tags/Chips styling - like the picture */
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #000080 !important;
        border: none !important;
        color: white !important;
        padding: 4px 10px !important;
        border-radius: 4px !important;
        font-size: 13px !important;
        margin: 3px 4px 3px 0 !important;
    }

    /* X button on tag */
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: white !important;
        margin-right: 6px !important;
        font-weight: bold;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ffcdd2 !important;
        background: transparent !important;
    }

    /* Dropdown options */
    .select2-dropdown {
        border: 1px solid #dee2e6 !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #ff9800 !important;
    }

    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #fff3e0 !important;
        color: #000080 !important;
        font-weight: 600;
    }

    /* Placeholder text */
    .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
        color: #aaa !important;
        font-size: 14px;
    }

    .btn-save-custom {
        background: #ff9800;
        color: white;
        border: none;
        border-radius: 8px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 16px;
        margin-top: 10px;
        padding: 16px;
    }

    .btn-save-custom:hover {
        background: #e68a00;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 152, 0, 0.2);
    }

    .back-link {
        color: #000080;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        margin-top: 25px;
        font-size: 14px;
    }
</style>

<div class="form-container">
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 25px;">Add New Head</h4>
    <div class="form-card">
        <form action="" method="POST">

            <div class="form-group">
                <label class="form-label">Head Type (Multiple Selection) <span class="required">*</span></label>
                <select id="head-type-select" name="head_type_ids[]" multiple="multiple" required>
                    <?php
                    // Purana query types wala
                    mysqli_data_seek($types, 0); // Reset pointer if already used
                    while ($t = mysqli_fetch_assoc($types)):
                    ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo $t['head_type_name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Head Name <span class="required">*</span></label>
                <input type="text" name="head_name" class="custom-input" placeholder="e.g. Stationery, Repairing, etc." required>
            </div>

            <div class="form-group">
                <label class="form-label">Head Category <span class="required">*</span></label>
                <div style="display: flex; gap: 20px; margin-top: 5px;">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="head_category" value="physical" required> Physical Assets
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="head_category" value="service" required> Service
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="radio" name="head_category" value="tax_free" required> Tax Free
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Head Code <span class="required">*</span></label>
                <input type="text" name="head_code" class="custom-input" placeholder="e.g. A13370, A03901, etc." required>
            </div>

            <button type="submit" name="save" class="btn-save-custom">SAVE HEAD DETAILS</button>
            <div style="text-align: center;"><a href="manage_heads.php" class="back-link">← Back to Heads List</a></div>
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