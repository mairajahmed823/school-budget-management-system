<?php
require_once '../config/database.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/ItemController.php';
checkUserAuth();

// Logic ab Controller ke is function mein hai
updateItem($conn);

// 1. URL se Item ID uthao (Sirf display data fetch karne ke liye)
if (!isset($_GET['id'])) {
    header("Location: manage_items.php");
    exit();
}
$item_id = mysqli_real_escape_string($conn, $_GET['id']);

// 2. Item ka basic data fetch karo (Form fields fill karne ke liye)
$item_query = mysqli_query($conn, "SELECT * FROM tbl_item WHERE id = '$item_id'");
$item_data = mysqli_fetch_assoc($item_query);

if (!$item_data) {
    die("Item not found!");
}

// 3. Current selected heads (item types) uthao pivot table se
$current_heads = [];
$pivot_query = mysqli_query($conn, "SELECT head_type_id FROM tbl_item_head_type WHERE item_id = '$item_id'");
while ($p_row = mysqli_fetch_assoc($pivot_query)) {
    $current_heads[] = $p_row['head_type_id'];
}

// Dropdown Query
$types = mysqli_query($conn, "SELECT h.head_name, h.id AS h_id FROM tbl_heads AS h
    INNER JOIN tbl_head_head_types AS hht ON h.id = hht.head_id
    INNER JOIN tbl_head_type AS ht ON ht.id = hht.head_type_id
    WHERE ht.type = 's' AND ht.item_type_status = 'Active'
    GROUP BY h.id");

$page_title = 'Edit Item';
include '../includes/header.php';
include '../includes/navbar.php';
// Search aur Type parameters capture karein
$s_param = isset($_GET['search']) ? $_GET['search'] : '';
$t_param = isset($_GET['item_type']) ? $_GET['item_type'] : '';

// Wapsi ka mukammal URL banayein
$back_url = "manage_items.php?search=" . urlencode($s_param) . "&item_type=" . urlencode($t_param);
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<style>
    .form-container {
        padding: 40px;
        max-width: 750px;
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

    .custom-input {
        border-radius: 8px;
        padding: 12px;
        border: 1px solid #dee2e6;
        margin-bottom: 20px;
        width: 100%;
        background: #fafafa;
        outline: none;
        transition: 0.3s;
    }

    .custom-input:focus {
        border-color: #ff9800;
        background: #fff;
    }

    .btn-update-custom {
        background: #ff9800;
        color: white;
        border: none;
        padding: 16px;
        border-radius: 8px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
        text-transform: uppercase;
        font-size: 16px;
    }

    .btn-update-custom:hover {
        background: #e68a00;
    }

    .back-link {
        color: #000080;
        text-decoration: none;
        font-weight: 600;
        display: block;
        text-align: center;
        margin-top: 25px;
    }
</style>

<div class="form-container">
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 25px;">Edit Item Details</h4>
    <div class="form-card">
        <form action="" method="POST">

            <?php if ($_SESSION['role'] === 'admin'): ?>

                <div class="form-group">
                    <label class="form-label">Item Type</label>
                    <select id="item-type-select" name="head_ids[]" class="custom-input" multiple="multiple" required>
                        <?php while ($t = mysqli_fetch_assoc($types)):
                            $selected = in_array($t['h_id'], $current_heads) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $t['h_id']; ?>" <?php echo $selected; ?>>
                                <?php echo $t['head_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            <?php endif ?>

            <div class="form-group">
                <label class="form-label">Item Name</label>
                <input type="text" name="item_name" class="custom-input" value="<?php echo $item_data['item_name']; ?>" required>
            </div>

            <div class="row">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="col-md-4">
                        <label class="form-label">Min Price</label>
                        <input type="number" step="0.01" name="min_price" class="custom-input" value="<?php echo $item_data['min_price']; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Max Price</label>
                        <input type="number" step="0.01" name="max_price" class="custom-input" value="<?php echo $item_data['max_price']; ?>" required>
                    </div>
                <?php else: ?>
                    <div class="col-md-12">
                        <label class="form-label">Min Price</label>
                        <input type="number" step="0.01" name="user_price" class="custom-input" value="<?php echo $item_data['min_price']; ?>" required>
                    </div>
                <?php endif; ?>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="col-md-4">
                        <label class="form-label">% of Budget</label>
                        <input type="number" step="0.01" name="percent_of_budget" class="custom-input" value="<?php echo $item_data['percent_of_budget']; ?>" required>
                    </div>
            </div>

            <div class="form-group">
                <label class="form-label">Item Category <span style="color:red">*</span></label>
                <select name="item_category" class="custom-input" required>
                    <option value="">Select Category</option>
                    <option value="Primary" <?php echo ($item_data['item_category'] == 'Primary') ? 'selected' : ''; ?>>
                        Primary (For Auto-Gen)
                    </option>
                    <option value="Secondary" <?php echo ($item_data['item_category'] == 'Secondary') ? 'selected' : ''; ?>>
                        Secondary (For Manual/Small Budget)
                    </option>
                </select>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="custom-input">
                <option value="Active" <?php echo ($item_data['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo ($item_data['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <button type="submit" name="update_item" class="btn-update-custom">UPDATE ITEM DETAILS</button>
        <a href="<?php echo $back_url; ?>" class="back-link">← Back to Items List</a>
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
    const element = document.getElementById('item-type-select');
    const choices = new Choices(element, {
        removeItemButton: true,
        placeholder: true,
        placeholderValue: '-- Select Item Types --'
    });
</script>

<?php include '../includes/footer.php'; ?>