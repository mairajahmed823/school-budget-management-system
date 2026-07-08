<?php
require_once '../controllers/RequisitionController.php';
require_once '../controllers/AuthController.php';
checkUserAuth();

$req_id = $_GET['id'] ?? 0;
// Existing Requisition Data
$res = mysqli_query($conn, "SELECT * FROM tbl_requisition WHERE id = $req_id");
$requisition = mysqli_fetch_assoc($res);

if (!$requisition) {
    die("Requisition Not Found!");
}

$dropdowns = getInitialDropdowns($conn);
updateRequisitionDetailsOnly($conn); // Controller function call

$page_title = 'Edit Item Requisition';
include '../includes/header.php';
include '../includes/navbar.php';

// Fetch Saved Items Details
$saved_items_res = mysqli_query($conn, "SELECT rd.*, i.item_name FROM tbl_requisition_details rd JOIN tbl_item i ON rd.item_id = i.id WHERE requisition_id = $req_id");
$saved_items = [];
while ($si = mysqli_fetch_assoc($saved_items_res)) {
    $saved_items[] = $si;
}
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

    .btn-save-custom {
        background: #ff9800;
        color: white;
        border: none;
        border-radius: 8px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
        padding: 16px;
        margin-top: 10px;
    }
</style>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />

<div class="form-container">
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 25px;">Edit Item Requisition</h4>
       <div class="container mt-3">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" id="error-alert">
                <strong>Limit Error!</strong> <?php echo nl2br($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

    </div>
    <div class="form-card">
        <form action="" method="POST" id="req-form">
            <input type="hidden" name="requisition_id" value="<?= $req_id ?>">

            <label class="form-label">Head Type <span class="required">*</span></label>
            <select name="head_id" id="item_type_select" class="custom-input" required aria-readonly="true">
                <?php mysqli_data_seek($dropdowns['heads'], 0);
                while ($h = mysqli_fetch_assoc($dropdowns['heads'])): ?>
                    <option value="<?= $h['h_id'] ?>" <?= $h['h_id'] == $requisition['head_id'] ? 'selected' : '' ?>>
                        <?= $h['code_no'] . " - " . $h['head_name'] ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <input type="hidden" name="school_id" value="<?= $requisition['school_id'] ?>">

            <label class="form-label">Requisition Date <span class="required">*</span></label>
            <input type="date" name="req_date" class="custom-input" value="<?= $requisition['req_date'] ?>" required>

            <label class="form-label">Expected Invoice Amount</label>
            <input type="text" name="expected_amount" id="expected_amount" class="custom-input" readonly style="background: #e9ecef; font-weight: bold; color: #000080;">
            <div id="limit-warning" style="display:none; color: #ff5252; font-weight: bold;">⚠️ Maximum limit 200,000 exceeded.</div>

            <hr>
            <div class="row mb-2" style="font-weight: bold; color: #000080;">
                <div class="col-6">Item Name</div>
                <div class="col-2">Qty</div>
                <div class="col-4">Price (Total)</div>
            </div>

            <?php for ($i = 0; $i < 15; $i++):
                $item_id = $saved_items[$i]['item_id'] ?? '';
                $qty = $saved_items[$i]['quantity'] ?? '';
            ?>
                <div class="row item-row mb-1 px-1">
                    <div class="col-6 p-1">
                        <select name="items[]" class="custom-input item-select-list mb-0" data-selected="<?= $item_id ?>">
                            <option value="">-- Choose Item --</option>
                        </select>
                    </div>
                    <div class="col-2 p-1">
                        <input type="number" name="qty[]" class="custom-input qty-input mb-0 text-center" value="<?= $qty ?>" placeholder="0">
                    </div>
                    <div class="col-4 p-1">
                        <input type="text" class="custom-input row-total-price mb-0" readonly placeholder="0.00" style="background: #fdfdfd;">
                    </div>
                    <input type="hidden" class="h-win-price" name="win_price[]">
                </div>
            <?php endfor; ?>

            <button type="submit" name="update_requisition" id="btn-save" class="btn-save-custom">UPDATE REQUISITION</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    $(document).ready(function() {
        let itemChoicesInstances = [];

        function refreshDropdowns() {
            itemChoicesInstances.forEach(i => i.destroy());
            itemChoicesInstances = [];
            $('.item-select-list').each(function() {
                let savedID = $(this).attr('data-selected');
                const inst = new Choices(this, {
                    searchEnabled: true,
                    itemSelectText: ''
                });
                if (savedID) {
                    inst.setChoiceByValue(savedID);
                }
                itemChoicesInstances.push(inst);
            });
        }

        async function processRowChange(row) {
            let headID = $('#item_type_select').val();
            let itemID = row.find('.item-select-list').val();
            if (!headID || !itemID) return;

            try {
                const res = await $.post('../controllers/RequisitionController.php', {
                    action: 'get_item_prices',
                    item_id: itemID,
                    head_id: headID
                });
                let data = (typeof res === 'string') ? JSON.parse(res) : res;
                let winPrice = parseFloat(data.winner_price) || 0;

                row.find('.h-win-price').val(winPrice);
                let qty = parseInt(row.find('.qty-input').val()) || 0;
                row.find('.row-total-price').val((winPrice * qty).toFixed(2));
                updateGrandTotal();
            } catch (e) {
                console.error(e);
            }
        }

        function updateGrandTotal() {
            let total = 0;
            $('.row-total-price').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            $('#expected_amount').val(total.toFixed(2));
            if (total > 200000) {
                $('#limit-warning').show();
                $('#btn-save').prop('disabled', true).css('opacity', '0.5');
            } else {
                $('#limit-warning').hide();
                $('#btn-save').prop('disabled', false).css('opacity', '1');
            }
        }

        async function loadItems(headID) {
            if (!headID) return;
            const response = await $.post('../controllers/RequisitionController.php', {
                action: 'fetch_items',
                head_id: headID
            });

            $('.item-select-list').html(html_options(response));
            refreshDropdowns();

            // Har row ke liye price calculate karein initial load par
            let rows = $('.item-row');
            for (let i = 0; i < rows.length; i++) {
                if ($(rows[i]).find('.item-select-list').val()) {
                    await processRowChange($(rows[i]));
                }
            }
        }

        function html_options(data) {
            // Agar response sirf HTML string hai toh wahi return karein
            return data;
        }

        $('#item_type_select').on('change', function() {
            loadItems($(this).val());
        });
        $(document).on('change', '.item-select-list', function() {
            processRowChange($(this).closest('.item-row'));
        });
        $(document).on('input', '.qty-input', function() {
            processRowChange($(this).closest('.item-row'));
        });

        // --- INITIAL LOAD ---
        // Pehle items load honge, phir unki prices fetch hongi
        loadItems($('#item_type_select').val());
    });
</script>