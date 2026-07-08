<?php
require_once '../config/database.php';
require_once '../controllers/AuthController.php';
require_once '../includes/crypto.php';
checkUserAuth();

if (!isset($_GET['id'])) {
    die("Invalid Request.");
}

$encrypted_id = $_GET['id'] ?? '';
$q_id = decrypt_id($encrypted_id);

if (!$q_id) {
    die("Invalid ID");
}

$q_id = mysqli_real_escape_string($conn, $q_id);

$items_query = "SELECT DISTINCT qd.item_id, i.item_name 
                FROM tbl_quotation_details qd 
                JOIN tbl_item i ON qd.item_id = i.id 
                WHERE qd.quotation_id = '$q_id'";
$items_res = mysqli_query($conn, $items_query);
$items = mysqli_fetch_all($items_res, MYSQLI_ASSOC);

// 2. Wo 3 Vendors nikal lo
$vendor_query = "SELECT DISTINCT v.id, v.vendor_name 
                 FROM tbl_quotation_details qd 
                 JOIN tbl_vendor v ON qd.vendor_id = v.id 
                 WHERE qd.quotation_id = '$q_id' 
                 ORDER BY v.id ASC LIMIT 3";
$vendors_res = mysqli_query($conn, $vendor_query);
$vendors = mysqli_fetch_all($vendors_res, MYSQLI_ASSOC);

// 3. LOGIC: Lowest Overall Vendor nikalna (For Supply Order)
$lowest_v_sql = "SELECT vendor_id, SUM(price * quantity) as total_amount 
                 FROM tbl_quotation_details 
                 WHERE quotation_id = '$q_id' 
                 GROUP BY vendor_id 
                 ORDER BY total_amount ASC LIMIT 1";
$lowest_v_res = mysqli_query($conn, $lowest_v_sql);
$lowest_vendor_data = mysqli_fetch_assoc($lowest_v_res);

$lowest_vendor_id = $lowest_vendor_data['vendor_id'] ?? 0;

// 4. Lowest Vendor ke items fetch karna breakout table ke liye
$supply_items = [];
if ($lowest_vendor_id > 0) {
    $si_sql = "SELECT qd.*, i.item_name, v.vendor_name 
               FROM tbl_quotation_details qd
               JOIN tbl_item i ON qd.item_id = i.id
               join tbl_vendor v on qd.vendor_id = v.id
               WHERE qd.quotation_id = '$q_id' AND qd.vendor_id = '$lowest_vendor_id'";
    $si_res = mysqli_query($conn, $si_sql);
    $supply_items = mysqli_fetch_all($si_res, MYSQLI_ASSOC);
}

if (empty($items)) {
    die("No data found for this Quotation ID.");
}

$page_title = 'Comparative Statement';
include '../includes/header.php';
include '../includes/navbar.php';
?>
<style>
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
        margin-top: 20px;
        padding: 16px;
    }

    .btn-save-custom:hover {
        background: #e68a00;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 152, 0, 0.2);
    }
</style>
<div class="container mt-4">
    <div class="mb-3 text-end">
         <a href="download_all_docs.php?v_id=<?= urlencode(encrypt_id($lowest_vendor_id)) ?>&q_id=<?= urlencode(encrypt_id($q_id)) ?>" target="_blank"
            class="btn btn-success shadow-sm">
            <i class="fas fa-file-pdf"></i> Download All Documents (Single PDF)
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="mb-0 fw-bold" style="color: #000080;">Comparative Statement</h4>
            <!--<a href="download_comparative_statement.php?q_id=<?= $q_id ?>&v_id=<?= $lowest_vendor_id ?>"-->
            <!--    target="_blank"-->
            <!--    class="btn btn-primary btn-sm w-25">-->
            <!--    <i class="fas fa-download"></i> Download Statement-->
            <!--</a>-->
        </div>
        <div class="card-body">
            <table class="table table-bordered text-center align-middle">
                <thead class="bg-light">
                    <tr>
                        <th style="color: red;">S.no</th>
                        <th style="color: red;">Item</th>
                        <?php foreach ($vendors as $v): ?>
                            <th style="color: red;"><?= $v['vendor_name'] ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td class="text-start"><?= $item['item_name'] ?></td>
                            <?php foreach ($vendors as $v): ?>
                                <td>
                                    <?php
                                    $v_id = $v['id'];
                                    $i_id = $item['item_id'];
                                    $p_sql = "SELECT price FROM tbl_quotation_details 
                                                  WHERE quotation_id = '$q_id' AND vendor_id = '$v_id' AND item_id = '$i_id'";
                                    $p_res = mysqli_query($conn, $p_sql);
                                    $p_row = mysqli_fetch_assoc($p_res);
                                    echo ($p_row) ? number_format($p_row['price'], 0) : '0';
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <!--<tfoot class="table-secondary">-->
                <!--    <tr>-->
                <!--        <td></td>-->
                <!--        <td></td>-->
                <!--        <?php foreach ($vendors as $v): ?>-->
                <!--            <td>-->
                <!--                <a href="generate_vendor_quotation.php?id=<?= $v['id'] ?>&q_id=<?= $q_id ?>"-->
                <!--                    target="_blank" class="text-primary fw-bold text-decoration-none" style="font-size: 12px;">-->
                <!--                    Download Quotation ðŸ“„-->
                <!--                </a>-->
                <!--            </td>-->
                <!--        <?php endforeach; ?>-->
                <!--    </tr>-->
                <!--</tfoot>-->
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-2 mb-md-0">
                <h4 class="mb-0 fw-bold" style="color: #000080;">Supply Order</h4>
                <h6 class="mb-0 text-muted mt-2"><?= htmlspecialchars($supply_items[0]['vendor_name']); ?></h6>
            </div>
            <!--<a href="../templates/supply_order_template.php?v_id=<?= $lowest_vendor_id ?>&q_id=<?= $q_id ?>"-->
            <!--    target="_blank"-->
            <!--    class="btn btn-primary btn-sm w-25"-->
            <!--    onclick="triggerPrint(this); return false;">-->
            <!--    Download Supply Order-->
            <!--</a>-->

            <script>
                function triggerPrint(link) {
                    var win = window.open(link.href, '_blank');
                    win.onload = function() {
                        setTimeout(function() {
                            win.print();
                        }, 800);
                    };
                }
            </script>
        </div>
        <div class="card-body">
            <table class="table table-bordered text-center">
                <thead class="bg-light">
                    <tr>
                        <th>S.no</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_bill = 0;
                    foreach ($supply_items as $s_index => $si):
                        $amt = $si['price'] * $si['quantity'];
                        $total_bill += $amt;
                    ?>
                        <tr>
                            <td><?= $s_index + 1 ?></td>
                            <td class="text-start"><?= $si['item_name'] ?></td>
                            <td><?= $si['quantity'] ?></td>
                            <td><?= number_format($si['price'], 0) ?></td>
                            <td><?= number_format($amt, 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="fw-bold bg-light">
                    <tr>
                        <td colspan="4" class="text-end">Total</td>
                        <td><?= number_format($total_bill, 0) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ****************************************** Bill code ************************** -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-2 mb-md-0">
                <h4 class="mb-0 fw-bold" style="color: #000080;">Vendor Bill</h4>
                <h6 class="mb-0 text-muted mt-2"><?= htmlspecialchars($supply_items[0]['vendor_name']); ?></h6>
            </div>
            <!--<a href="generate_vendor_bill.php?id=<?= $lowest_vendor_id ?>&q_id=<?= $q_id ?>"-->
            <!--    target="_blank"-->
            <!--    class="btn btn-primary btn-sm w-25">-->
            <!--    Download Bill ðŸ“„-->
            <!--</a>-->
        </div>
        <div class="card-body">
            <table class="table table-bordered text-center">
                <thead class="table-light">
                    <tr>
                        <th>S.no</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_bill = 0;
                    foreach ($supply_items as $s_index => $si):
                        $amt = $si['price'] * $si['quantity'];
                        $total_bill += $amt;
                    ?>
                        <tr>
                            <td><?= $s_index + 1 ?></td>
                            <td class="text-start"><?= $si['item_name'] ?></td>
                            <td><?= $si['quantity'] ?></td>
                            <td><?= number_format($si['price'], 0) ?></td>
                            <td><?= number_format($amt, 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="fw-bold bg-light">
                    <tr>
                        <td colspan="4" class="text-end">Total Bill Amount</td>
                        <td><?= number_format($total_bill, 0) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <!-- ****************************************** challan code ************************** -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="mb-2 mb-md-0">
                <h4 class="mb-0 fw-bold" style="color: #000080;">Delivery Challan</h4>
                <h6 class="mb-0 text-muted mt-2"><?= htmlspecialchars($supply_items[0]['vendor_name']); ?></h6>
            </div>
            <!--<a href="generate_delivery_challan.php?id=<?= $lowest_vendor_id ?>&q_id=<?= $q_id ?>"-->
            <!--    target="_blank"-->
            <!--    class="btn btn-primary btn-sm w-25">-->
            <!--    Download Challan ðŸšš-->
            <!--</a>-->
        </div>
        <div class="card-body">
            <table class="table table-bordered text-center">
                <thead class="table-light">
                    <tr>
                        <th style="width: 10%;">S.no</th>
                        <th style="width: 70%;">Item Description</th>
                        <th style="width: 20%;">Quantity Delivered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($supply_items as $c_index => $ci): ?>
                        <tr>
                            <td><?= $c_index + 1 ?></td>
                            <td class="text-start"><?= $ci['item_name'] ?></td>
                            <td><?= $ci['quantity'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!--<div class="card shadow-sm border-0 mb-5">-->
    <!--    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">-->
    <!--        <div class="mb-2 mb-md-0">-->
    <!--            <h4 class="mb-0 fw-bold" style="color: #000080;">Sanction Order</h4>-->
    <!--        </div>-->
            <!--<a href="../templates/generate_sanction_order.php?q_id=<?= $q_id ?>&v_id=<?= $lowest_vendor_id ?>"-->
            <!--    target="_blank" class="btn btn-primary btn-sm w-25" onclick="TriggerPrint(this); return false;">-->
            <!--    Download Sanction Order-->
            <!--</a>-->
    <!--        <script>-->
    <!--            function TriggerPrint(link) {-->
    <!--                var win = window.open(link.href, '_blank');-->
    <!--                win.onload = function() {-->
    <!--                    setTimeout(function() {-->
    <!--                        win.print();-->
    <!--                    }, 800);-->
    <!--                };-->
    <!--            }-->
    <!--        </script>-->
    <!--    </div>-->
    <!--</div>-->

    <!--<div class="card shadow-sm border-0 mb-5">-->
    <!--    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-center">-->
    <!--        <div class="mb-2 mb-md-0">-->
    <!--            <h4 class="mb-0 fw-bold" style="color: #000080;">Form T.R. 30</h4>-->
    <!--        </div>-->

            <!--<a href="../templates/generate_form_30.php?q_id=<?= $q_id ?>&v_id=<?= $lowest_vendor_id ?>"-->
            <!--    target="_blank"-->
            <!--    class="btn btn-primary btn-sm w-25"-->
            <!--    onclick="TriggerPrint(this); return false;">-->
            <!--    <i class="fa fa-file-pdf-o"></i> Download Form 30-->
            <!--</a>-->
    <!--    </div>-->

    <!--</div>-->

    <script>
        function TriggerPrint(link) {
            var win = window.open(link.href, '_blank');
            win.onload = function() {
                setTimeout(function() {
                    win.focus(); // Window ko focus karein
                    win.print();
                    // win.close(); // Agar aap chahte hain ke print ke baad window khud band ho jaey
                }, 1000);
            };
        }
    </script>

</div>
<script>
    function printComparativeStatement() {
        // Sirf Comparative Statement wala hissa nikalne ke liye
        var printContents = document.querySelector('.card.shadow-sm.border-0.mb-5').innerHTML;
        var originalContents = document.body.innerHTML;

        // Page ko temporary sirf table se replace karna
        document.body.innerHTML = "<html><head><title>Comparative Statement</title>" +
            "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'>" +
            "<style>body{padding:20px;} .btn, .text-primary{display:none !important;}</style>" +
            "</head><body>" + printContents + "</body></html>";

        window.print();

        // Wapis purana page restore karna
        document.body.innerHTML = originalContents;
        window.location.reload();
    }
</script>
<?php include '../includes/footer.php'; ?>