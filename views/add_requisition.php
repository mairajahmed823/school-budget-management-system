<?php
require_once '../controllers/RequisitionController.php';
require_once '../controllers/AuthController.php';
checkUserAuth();

$dropdowns = getInitialDropdowns($conn);
saveRequisition($conn);
$page_title = 'Add Item Requisition';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    .form-container {
        padding: 40px;
        max-width: 800px;
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
        margin-bottom: 8px;
        display: block;
        font-size: 15px;
    }

    .required {
        color: #ff5252;
        margin-left: 2px;
    }

    .custom-input {
        border-radius: 8px;
        padding: 12px;
        border: 1px solid #dee2e6;
        margin-bottom: 20px;
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
    }

    .back-link {
        color: #000080;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        margin-top: 25px;
        font-size: 14px;
    }

    .choices__inner {
        background-color: #fff !important;
        border-radius: 8px !important;
        border: 1px solid #dee2e6 !important;
        min-height: 48px !important;
    }
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<div class="form-container">
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 25px;">Add Item Requisition</h4>
    <div class="container mt-3">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="error-alert">
                <strong>Limit Error!</strong> <?php echo nl2br($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" id="success-alert">
                <strong>Success!</strong> <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </div>
    <div class="form-card">
        <form action="" method="POST" id="req-form">
            <label class="form-label">Cost Center <span class="required">*</span></label>

            <?php if ($_SESSION['role'] === 'admin'): ?>
                <select name="school_id" id="school-select" class="custom-input" required>
                    <option value="">-- Select Cost Center --</option>
                    <?php
                    mysqli_data_seek($dropdowns['schools'], 0);
                    while ($s = mysqli_fetch_assoc($dropdowns['schools'])):
                    ?>
                        <option value="<?= $s['id'] ?>"><?= $s['school_code'] ?></option>
                    <?php endwhile; ?>
                </select>
            <?php else: ?>
                <?php
                mysqli_data_seek($dropdowns['schools'], 0);
                $s = mysqli_fetch_assoc($dropdowns['schools']);
                ?>
                <input type="text" class="custom-input" value="<?= $s['school_code'] ?>" readonly style="background: #e9ecef; cursor: not-allowed;">

                <input type="hidden" name="school_id" id="school-select" value="<?= $s['id'] ?>">
            <?php endif; ?>

            <label class="form-label">Head Type <span class="required">*</span></label>
            <select name="head_id" id="item_type_select" class="custom-input" required>
                <option value="">-- Select Type --</option>
                <?php mysqli_data_seek($dropdowns['heads'], 0); ?>
                <?php while ($h = mysqli_fetch_assoc($dropdowns['heads'])): ?>
                    <option value="<?= $h['h_id'] ?>"><?= $h['code_no'] . " - " . $h['head_name'] ?></option>
                <?php endwhile; ?>
            </select>

            <label class="form-label">Requisition Date <span class="required">*</span></label>
            <input type="date" name="req_date" class="custom-input" required>

            <label class="form-label">Description</label>
            <textarea name="description" class="custom-input" rows="2"></textarea>

            <label class="form-label">Expected Invoice Amount</label>
            <input type="text" name="expected_amount" id="expected_amount" class="custom-input" readonly style="background: #e9ecef; font-weight: bold; color: #000080;">
            <div id="limit-warning" style="display:none; color: #ff5252; font-weight: bold; margin-bottom: 15px; font-size: 13px;">
                ⚠️ Maximum limit for requisition is 200,000. </div>

            <hr>
            <div class="row mb-2">
                <div class="col-6"><label class="form-label">Item Name</label></div>
                <div class="col-2"><label class="form-label">Qty</label></div>
                <div class="col-4"><label class="form-label">Price (Total)</label></div>
            </div>

            <?php for ($i = 0; $i < 12; $i++): ?>
                <div class="row item-row mb-1 px-1">
                    <div class="col-6 p-1">
                        <select name="items[]" class="custom-input item-select-list mb-0">
                            <option value="">-- Choose Item --</option>
                        </select>
                    </div>
                    <div class="col-2 p-1">
                        <input type="number" name="qty[]" class="custom-input qty-input mb-0 text-center" placeholder="0" style="padding: 10px 5px;">
                    </div>
                    <div class="col-4 p-1">
                        <input type="text" class="custom-input row-total-price mb-0" readonly placeholder="0.00" style="background: #fdfdfd; padding: 10px;">
                    </div>

                    <input type="hidden" class="h-win-price" name="win_price[]">
                    <input type="hidden" class="h-other1-v" name="other1_v[]">
                    <input type="hidden" class="h-other1-p" name="other1_p[]">
                    <input type="hidden" class="h-other2-v" name="other2_v[]">
                    <input type="hidden" class="h-other2-p" name="other2_p[]">
                </div>
            <?php endfor; ?>

            <button type="button" id="btn-calculate" class="btn-save-custom" style="background: #000080;">CALCULATE TOTAL</button>
            <button type="submit" name="save_requisition" id="btn-save" class="btn-save-custom">SAVE REQUISITION</button>

            <div style="text-align: center;">
                <a href="manage_requisition.php" class="back-link">← Back to List</a>
            </div>
        </form>
        <!-- Error Modal -->
        <div id="error-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
           background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:12px; padding:35px; max-width:420px; width:90%;
                box-shadow:0 10px 40px rgba(0,0,0,0.2); text-align:center;">
                <div style="font-size:48px; margin-bottom:15px;">⚠️</div>
                <h5 style="color:#000080; font-weight:bold; margin-bottom:15px;">Error</h5>
                <p id="modal-message" style="color:#444; white-space:pre-line; margin-bottom:25px; font-size:14px;"></p>
                <button onclick="closeErrorModal()"
                    style="background:#ff9800; color:white; border:none; border-radius:8px;
                       padding:12px 30px; font-weight:bold; cursor:pointer; font-size:14px;">
                    OK, Go Back
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    $(document).ready(function() {

        // Alerts Auto-hide
        setTimeout(() => {
            $('#error-alert, #success-alert').fadeOut('slow');
        }, 5000);

        let itemChoicesInstances = [];
        let currentHeadCode = '';

        // School Select Initialization (Admin vs School User)
        const schoolElem = document.getElementById('school-select');
        if (schoolElem && schoolElem.tagName === 'SELECT') {
            new Choices('#school-select', {
                searchEnabled: true,
                itemSelectText: ''
            });
        }
        new Choices('#item_type_select', {
            searchEnabled: true,
            itemSelectText: ''
        });

        // --- Core Helper: Get School ID ---
        function getActiveSchoolID() {
            return $('[name="school_id"]').val();
        }

        // --- Formula Logic: Get Qty based on Head ---
        async function getFormulaQty(itemID, headID, winPrice) {
            if (currentHeadCode === 'A03901') {
                let sID = getActiveSchoolID();
                const res = await $.post('../controllers/RequisitionController.php', {
                    action: 'get_school_enrollment',
                    school_id: sID
                });
                let data = JSON.parse(res);
                return parseInt(data.enrollment) || 0;
            } else {
                return Math.floor(200000 / winPrice);
            }
            return 0; // Default or manual
        }

        // --- Main Function: Refresh Row & Total ---
        async function processRowChange(row) {
            let headID = $('#item_type_select').val();
            let itemID = row.find('.item-select-list').val();

            if (!headID || !itemID) {
                row.find('.qty-input, .row-total-price, .h-win-price').val('');
                updateGrandTotal();
                return;
            }

            try {
                // 1. Fetch Price
                const response = await $.ajax({
                    type: 'POST',
                    url: '../controllers/RequisitionController.php',
                    data: {
                        action: 'get_item_prices',
                        item_id: itemID,
                        head_id: headID
                    }
                });
                let data = (typeof response === 'string') ? JSON.parse(response) : response;
                let winPrice = parseFloat(data.winner_price) || 0;

                // 2. Get Auto Qty based on logic
                let autoQty = await getFormulaQty(itemID, headID, winPrice);

                // 3. Update Row Fields
                row.find('.h-win-price').val(winPrice);
                if (autoQty > 0) row.find('.qty-input').val(autoQty);

                let currentQty = parseInt(row.find('.qty-input').val()) || 0;
                row.find('.row-total-price').val((winPrice * currentQty).toFixed(2));

                updateGrandTotal();

            } catch (e) {
                console.error("Error processing row:", e);
            }
        }

        // --- Grand Total & Warning ---
        function updateGrandTotal() {
            let total = 0;
            $('.row-total-price').each(function() {
                total += parseFloat($(this).val()) || 0;
            });

            $('#expected_amount').val(total.toFixed(2));

            if (total > 200000) {
                $('#limit-warning').fadeIn();
                $('#btn-save').prop('disabled', true).css('opacity', '0.5');
            } else {
                $('#limit-warning').fadeOut();
                $('#btn-save').prop('disabled', false).css('opacity', '1');
            }
        }

        // --- Dropdown Management ---
        function refreshItemDropdowns() {
            itemChoicesInstances.forEach(i => i.destroy());
            itemChoicesInstances = [];
            $('.item-select-list').each(function() {
                const inst = new Choices(this, {
                    searchEnabled: true,
                    itemSelectText: '',
                    placeholder: true,
                    // placeholderValue: '-- Choose Item --'
                });
                itemChoicesInstances.push(inst);
            });
        }

        // Event: Head Change
        $('#item_type_select').on('change', function() {
            let headID = $(this).val();
            currentHeadCode = $(this).find('option:selected').text().split(' - ')[0].trim();

            if (!headID) return;

            $.post('../controllers/RequisitionController.php', {
                action: 'fetch_items',
                head_id: headID
            }, function(html) {
                $('.item-select-list').html(html);
                refreshItemDropdowns();
                $('.qty-input, .row-total-price').val('');
                $('#expected_amount').val('0.00');
            });
        });

        // Event: Item Select Change (The Trigger)
        $(document).on('change', '.item-select-list', function() {
            processRowChange($(this).closest('.item-row'));
        });

        // Event: Manual Qty Change (If user edits qty manually)
        $(document).on('input', '.qty-input', function() {
            let row = $(this).closest('.item-row');
            let winPrice = parseFloat(row.find('.h-win-price').val()) || 0;
            let qty = parseInt($(this).val()) || 0;
            row.find('.row-total-price').val((winPrice * qty).toFixed(2));
            updateGrandTotal();
        });

        $('#btn-calculate').hide(); // Hide redundant button
    });
</script>

<?php include '../includes/footer.php'; ?>