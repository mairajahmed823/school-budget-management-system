<?php
require_once '../config/database.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/ItemController.php';
checkUserAuth();

// Function call yahan hogi
saveItem($conn);

// Dropdown Query
$types = mysqli_query($conn, "SELECT h.head_name, h.id AS h_id, h.code_no FROM tbl_heads AS h
    INNER JOIN tbl_head_head_types AS hht ON h.id = hht.head_id
    INNER JOIN tbl_head_type AS ht ON ht.id = hht.head_type_id
    WHERE ht.type = 's' AND ht.item_type_status = 'Active'
    GROUP BY h.id");

$page_title = 'Add New Item';
include '../includes/header.php';
include '../includes/navbar.php';
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
    }

    .custom-input:focus {
        border-color: #ff9800;
        box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
    }

    .btn-save-custom {
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
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 25px;">Add New Item</h4>
    <div class="form-card">
        <form action="add_new_item.php" method="POST">

            <div class="form-group">
                <label class="form-label">
                    Item Type <?php echo ($_SESSION['role'] === 'admin') ? '(Multiple Selection)' : '(Single Selection)'; ?>
                    <span style="color:red">*</span>
                </label>

                <select id="item-type-select"
                    name="head_ids<?php echo ($_SESSION['role'] === 'admin') ? '[]' : ''; ?>"
                    class="custom-input"
                    <?php echo ($_SESSION['role'] === 'admin') ? 'multiple="multiple"' : ''; ?>
                    required>

                    <option value="" disabled <?php echo ($_SESSION['role'] !== 'admin') ? 'selected' : ''; ?>>Select Item Type</option>

                    <?php
                    // Resetting pointer agar loop pehle kahi use hua ho
                    mysqli_data_seek($types, 0);
                    while ($t = mysqli_fetch_assoc($types)):
                    ?>
                        <option value="<?php echo $t['h_id']; ?>">
                            <?php echo $t['code_no'] . " - " . $t['head_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class=" form-group">
                <label class="form-label">Item Name <span style="color:red">*</span></label>
                <input type="text" name="item_name" class="custom-input" placeholder="Enter Item Name" required>
            </div>

            <div class="row">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="col-md-4">
                        <label class="form-label">Min Price</label>
                        <input type="number" step="0.01" name="min_price" class="custom-input" placeholder="0.00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Max Price</label>
                        <input type="number" step="0.01" name="max_price" class="custom-input" placeholder="0.00" required>
                    </div>
                <?php else: ?>
                    <div class="col-md-8">
                        <label class="form-label">Item Price <span style="color:red">*</span></label>
                        <input type="number" step="0.01" name="user_price" class="custom-input" placeholder="Enter Price" required>
                    </div>
                <?php endif; ?>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="col-md-4">
                        <label class="form-label">% of Budget</label>
                        <input type="number" step="0.01" name="percent_of_budget" class="custom-input" placeholder="%" required>
                    </div>
            </div>


            <div class="form-group col-md-12">
                <label class="form-label">Item Category <span style="color:red">*</span></label>
                <select name="item_category" class="custom-input" required>
                    <option value="">Select Category</option>
                    <option value="Primary">Primary (For Auto-Gen)</option>
                    <option value="Secondary">Secondary (For Manual/Small Budget)</option>
                </select>
            </div>
        <?php endif; ?>

        <button type="submit" name="save_item" class="btn-save-custom">SAVE ITEM DETAILS</button>
        <a href="manage_items.php" class="back-link">← Back to Items List</a>
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