<?php
require_once '../controllers/BudgetController.php';
require_once '../controllers/AuthController.php';
require_once '../includes/crypto.php';

checkUserAuth();

// --- Action: Save Budget ---

if (isset($_POST['save_budget'])) {
    $result = saveBudget($conn, $_POST);
    
    if ($result === "exists") {
        echo "<script>alert('Error: Budget already exists against this tenure for this school!');</script>";
    } elseif ($result === true) {
        $school_id = decrypt_id($_POST['school_id']);
        echo "<script>alert('Budget successfully allocated!'); window.location.href='add_budget.php?id=" . urlencode(encrypt_id($school_id)) . "';</script>";
    } else {
        echo "<script>alert('Something went wrong. Please try again.');</script>";
    }
}

// --- Action: Delete Budget Detail ---
if (isset($_GET['delete_id'])) {
    $encrypted_delete_id = $_GET['delete_id'] ?? '';
    $encrypted_id = $_GET['id'] ?? '';

    $delete_id = decrypt_id($encrypted_delete_id);
    $school_id = decrypt_id($encrypted_id);

    if (!$delete_id || !$school_id) {
        die("Invalid ID");
    }

    $delete_status = deleteBudgetDetail($conn, $delete_id);

    if ($delete_status === "requisition_exists") {
        // Requisition wala error message
        echo "<script>
            alert('It appears that you have already created requisition against this budget. To delete this budget, it is recommended to delete requisition first.');
            window.location.href='add_budget.php?id=" . urlencode(encrypt_id($school_id)) . "';
        </script>";
        exit();
    } elseif ($delete_status === true) {
        header("Location: add_budget.php?id=" . urlencode(encrypt_id($school_id)) . "&msg=deleted");
        exit();
    } else {
        echo "<script>alert('Error: Could not delete budget.');</script>";
    }
}

$data = getBudgetData($conn);
$school = mysqli_fetch_assoc($data['school_info']);
if (!$school) {
    die("Invalid School ID!");
}

$history_rows = [];
$unique_heads = [];
mysqli_data_seek($data['history'], 0); // Reset pointer
$history_rows = [];
$unique_heads = [];

mysqli_data_seek($data['history'], 0); // Reset pointer
while ($row = mysqli_fetch_assoc($data['history'])) {
    $tenure = $row['tenure'];

    // FIX: Unique key banane ke liye Name aur Code ko jora gaya hai
    // Taake agar Name 'Others' ho lekin Code alag ho, to alag column bane
    $head_with_code = $row['head_name'] . " - " . $row['code_no'];

    $history_rows[$tenure]['budget_id'] = $row['budget_id'];

    // Ab yahan 'Others' ki jagah 'Others - 101' jaisi unique key banegi
    $history_rows[$tenure]['data'][$head_with_code] = [
        'amount' => $row['amount'],
        'detail_id' => $row['detail_id']
    ];

    $history_rows[$tenure]['date'] = date('d-M-Y', strtotime($row['created_on']));

    // Ab ye true unique heads collect karega
    $unique_heads[$head_with_code] = true;
}
// var_dump($unique_heads); die();
$page_title = 'Add Budget';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    .form-container {
        padding: 30px;
        max-width: 1100px;
        margin: auto;
    }

    .form-card {
        background: #fff;
        padding: 35px;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        margin-bottom: 40px;
    }

    .form-label {
        font-weight: 600;
        color: #000080;
        margin-bottom: 8px;
        display: block;
    }

    .static-display {
        padding: 12px;
        background: #f4f7fe;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        margin-bottom: 20px;
        font-weight: 700;
    }

    .custom-select {
        border-radius: 8px;
        padding: 12px;
        border: 1px solid #cbd5e0;
        width: 100%;
    }

    .head-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #edf2f7;
    }

    .budget-field {
        width: 220px;
        border-radius: 6px;
        border: 1px solid #cbd5e0;
        padding: 8px 12px;
        text-align: right;
    }

    .btn-submit {
        background: #000080;
        color: white;
        border: none;
        padding: 16px;
        border-radius: 8px;
        font-weight: 700;
        width: 100%;
        margin-top: 30px;
        cursor: pointer;
    }

    /* History Table Styles */
    .history-section {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
    }

    .table-custom {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 14px;
    }

    .table-custom th {
        background: #000080;
        color: #fff;
        padding: 12px;
        text-align: center;
        border: 1px solid #ddd;
    }

    .table-custom td {
        padding: 12px;
        text-align: center;
        border: 1px solid #ddd;
        vertical-align: middle;
    }

    .btn-delete {
        color: #dc3545;
        cursor: pointer;
        text-decoration: none;
        font-size: 18px;
    }

    .total-col {
        font-weight: bold;
        background: #f8f9fa;
        color: #000;
    }
</style>

<div class="form-container">
    <h2 style="color: #000080; font-weight: 800;" class="mb-4">Budget Management</h2>

    <div class="form-card">
        <form action="" method="POST">
            <input type="hidden" name="school_id" value="<?= encrypt_id($school['id']) ?>">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">School Code</label>
                    <div class="static-display"><?= $school['school_code'] ?></div>
                    <label class="form-label">Enrollment</label>
                    <div class="static-display"><?= $school['enrollment'] ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Select Financial Tenure *</label>
                    <select name="tenure_id" class="custom-select" required>
                        <option value="">-- Choose Tenure --</option>
                        <?php mysqli_data_seek($data['tenures'], 0);
                        while ($t = mysqli_fetch_assoc($data['tenures'])): ?>
                            <option value="<?= $t['id'] ?>"><?= $t['tenure'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Allocation Details</h5>
                <?php mysqli_data_seek($data['heads'], 0);
                while ($h = mysqli_fetch_assoc($data['heads'])): ?>
                    <div class="head-item">
                        <div class="fw-bold text-dark"><?= $h['head_name'] ?> - <?= $h['code_no'] ?></div>
                        <input type="hidden" name="head_ids[]" value="<?= $h['id'] ?>">
                        <input type="number" name="budgets[]" class="budget-field" placeholder="0.00" step="0.01">
                    </div>
                <?php endwhile; ?>
            </div>
            <button type="submit" name="save_budget" class="btn-submit">CONFIRM & SAVE BUDGET</button>
        </form>
    </div>

    <div class="history-section">
        <h3 style="color: #000080; font-weight: 700;">Budget History</h3>
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Tenure</th>
                        <?php foreach (array_keys($unique_heads) as $headName): ?>
                            <th><?= $headName ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                        <th>Added On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history_rows)): ?>
                        <tr>
                            <td colspan="<?= count($unique_heads) + 4 ?>">No record found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history_rows as $tenureLabel => $info):
                            $rowTotal = 0; ?>
                            <tr>
                                <td class="fw-bold"><?= $tenureLabel ?></td>

                                <?php foreach (array_keys($unique_heads) as $headName): ?>
                                    <td>
                                        <?php if (isset($info['data'][$headName])):
                                            $amt = $info['data'][$headName]['amount'];
                                            $rowTotal += $amt;
                                            echo number_format($amt, 2);
                                        else:
                                            echo "--";
                                        endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <td class="total-col"><?= number_format($rowTotal, 2) ?></td>
                                <td><?= $info['date'] ?></td>
                                <td>
                                    <a href="?id=<?= urlencode(encrypt_id($school['id'])) ?>&delete_id=<?= urlencode(encrypt_id($info['budget_id'])) ?>"
                                        class="btn-delete"
                                        onclick="return confirm('Are you sure you want to delete this tenure budget?')">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>