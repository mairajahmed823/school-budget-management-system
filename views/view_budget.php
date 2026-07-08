<?php
require_once '../controllers/BudgetController.php';
require_once '../controllers/AuthController.php';
require_once '../includes/crypto.php';

checkUserAuth();

$budget_data = getLatestQuarterlyBudget($conn);
$rows = [];
$tenure_name = "";

while ($row = mysqli_fetch_assoc($budget_data)) {
    $rows[] = $row;
    $tenure_name = $row['tenure'];
}

$page_title = 'Quarterly Budget';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    .budget-wrapper {
        padding: 30px;
    }

    .budget-title {
        color: #000080;
        font-weight: 800;
        font-size: 26px;
    }

    .btn-back {
        background: #6c757d;
        color: white;
        border: none;
        padding: 10px 35px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 15px;
        text-decoration: none;
        transition: 0.3s;
    }

    .btn-back:hover {
        background: #5a6268;
        color: white;
    }

    .quarter-card {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 25px;
    }

    .quarter-header {
        background: #fff;
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #f0f0f0;
    }

    .quarter-title {
        color: #000080;
        font-weight: 800;
        font-size: 16px;
        margin: 0;
    }

    .btn-process {
        background: #ff9800;
        color: white;
        border: none;
        padding: 8px 28px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-process:hover {
        background: #e68a00;
        box-shadow: 0 3px 10px rgba(255, 152, 0, 0.3);
    }

    .budget-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }

    .budget-table thead tr {
        background: #f8f9fa;
    }

    .budget-table th {
        padding: 10px 14px;
        color: #333;
        font-weight: 700;
        border: 1px solid #dee2e6;
        text-align: center;
    }

    .budget-table td {
        padding: 9px 14px;
        border: 1px solid #dee2e6;
        text-align: center;
        color: #444;
    }

    .budget-table td.text-start {
        text-align: left;
    }

    .budget-table td.fw-bold {
        font-weight: 700;
        color: #000080;
    }

    .budget-table tbody tr:hover {
        background: #fffbf2;
    }
</style>

<div class="budget-wrapper">


    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="budget-title">
            <?= !empty($rows) ? $rows[0]['school_name'] : 'School Budget' ?>
        </h2>
        <a href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'manage_school.php' ?>" class="btn-back">Back</a>
    </div>

    <div class="row">
        <?php
        // Aaj ki date ke hisaab se current fiscal quarter nikalna
        $current_month = (int)date('m');
        if ($current_month >= 7 && $current_month <= 9) $active_q_now = 1;
        elseif ($current_month >= 10 && $current_month <= 12) $active_q_now = 2;
        elseif ($current_month >= 1 && $current_month <= 3) $active_q_now = 3;
        else $active_q_now = 4;

        $quarters = ["1st Quarter", "2nd Quarter", "3rd Quarter", "4th Quarter"];
        $carry_forward = [];

        foreach ($quarters as $index => $q):
            $q_num = $index + 1;

            $is_future = ($q_num > $active_q_now);
        ?>
            <div class="col-md-12">
                <div class="quarter-card mb-4">
                    <div class="quarter-header">
                        <h5 class="quarter-title"><?= $q ?> <?= ($q_num == $active_q_now) ? '<span class="badge bg-primary">Current</span>' : '' ?></h5>
                    </div>

                    <table class="budget-table">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Head Name</th>
                                <th>Quarter Budget</th>
                                <th>Carry Forward</th>
                                <th>Total Available</th>
                                <th>Consumption</th>
                                <th>Remaining Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rows)) : ?>
                                <?php
                                $count = 1;
                                foreach ($rows as $item):
                                    $h_id = $item['head_id'];
                                    $s_id = $item['school_id'];
                                    $q_budget = (float)$item['amount'] / 4;

                                    // Pichlay quarter se kitna aaya
                                    $brought_fwd = isset($carry_forward[$h_id]) ? $carry_forward[$h_id] : 0;

                                    // Total jo is quarter mein available hai
                                    $total_available = $q_budget + $brought_fwd;

                                    // Kharcha (Future quarter hai toh consumption 0 hogi automatically)
                                    $consumption = getConsumptionByQuarter($conn, $s_id, $h_id, $q_num, $tenure_name);

                                    // Bacha hua paisa
                                    $remaining = $total_available - $consumption;

                                    // Carry forward sirf tab update hoga agar ye quarter "Future" nahi hai
                                    if (!$is_future) {
                                        $carry_forward[$h_id] = $remaining;
                                    } else {
                                        // Future quarters ke liye carry forward track nahi karenge view mein
                                        $remaining = 0;
                                        $total_available = $q_budget; // Future mein sirf apna 25% dikhaye
                                        $brought_fwd = 0;
                                    }
                                ?>
                                    <tr>
                                        <td><?= $count++ ?></td>
                                        <td class="text-start"><?= $item['head_name'] ?></td>
                                        <td><?= number_format($q_budget, 2) ?></td>
                                        <td class="text-info"><?= ($is_future) ? '-' : number_format($brought_fwd, 2) ?></td>
                                        <td class="fw-bold"><?= ($is_future) ? number_format($q_budget, 2) : number_format($total_available, 2) ?></td>
                                        <td class="text-danger fw-bold"><?= ($is_future) ? '-' : number_format($consumption, 2) ?></td>
                                        <td class="<?= ($remaining >= 0) ? 'text-success' : 'text-danger' ?> fw-bold">
                                            <?= ($is_future) ? '-' : number_format($remaining, 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="7">No data found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>