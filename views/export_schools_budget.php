<?php
require_once '../config/database.php';
require_once '../controllers/BudgetController.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Budget_Allocation_Export.xls");

$export = getSchoolsBudgetExportData($conn);
$unique_heads = $export['unique_heads'];
$rows = $export['rows'];
?>

<table border="1">
    <thead>
        <tr style="background-color: #FFC107; font-weight: bold; text-align: center;">
            <th>Sr</th>
            <th>Demand No</th>
            <th>Cost Center</th>
            <th>Semis Code</th>
            <th>Name of School</th>
            <th>District</th>
            <th>Allocation<br>
                <?php 
                // Pehli row se tenure utha kar heading mein dikhanay ke liye
                echo isset($rows[0]['info']['tenure']) ? $rows[0]['info']['tenure'] : 'N/A'; 
                ?>
            </th>
            <th>Enrolment</th>
            <?php foreach ($unique_heads as $headTitle): ?>
                <th><?= $headTitle ?></th>
            <?php endforeach; ?>
            <th>Grand Total</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $sr = 1;
        foreach ($rows as $r): 
            $info = $r['info'];
            // rowTotal humne controller mein 'total_allocation' mein calculate kar lia hai
        ?>
            <tr>
                <td align="center"><?= $sr++ ?></td>
                <td><?= $info['demand_no'] ?: '--' ?></td>
                <td><?= $info['school_code'] ?></td>
                <td><?= $info['semis_code'] ?></td>
                <td><?= $info['school_name'] ?></td>
                <td><?= $info['district'] ?></td>
                
                <td align="right" style="background-color: #f9f9f9; font-weight: bold;">
                    <?= number_format($r['total_allocation'], 2) ?>
                </td>

                <td align="center"><?= $info['enrolment'] ?></td>

                <?php foreach (array_keys($unique_heads) as $headId): ?>
                    <td align="right">
                        <?php 
                        if (isset($r['amounts'][$headId])): 
                            echo number_format($r['amounts'][$headId], 2);
                        else:
                            echo "--";
                        endif; 
                        ?>
                    </td>
                <?php endforeach; ?>

                <td style="font-weight: bold; background-color: #e8f5e9;" align="right">
                    <?= number_format($r['total_allocation'], 2) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>