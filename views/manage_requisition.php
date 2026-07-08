<?php
require_once '../controllers/RequisitionController.php';
require_once '../controllers/AuthController.php';
checkUserAuth();

if (isset($_GET['delete_id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete_id']);

    $query = "UPDATE `tbl_requisition` SET `status` = 'InActive' WHERE `id` = '$id'";

    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Requisition Deleted Successfully!'); window.location='manage_requisition.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error: Could not delete.');</script>";
    }
}
$tenure_query = mysqli_query($conn, "SELECT * FROM tbl_tenure WHERE status = 'Active'");
$search = [
    'school_code' => $_GET['school_code'] ?? '',
    'tenure_id'    => $_GET['tenure_id'] ?? ''
];

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$result = getManageRequisitions($conn, $search, $limit, $offset);

$requisitions = $result['data'];
$total_rows = $result['total'];

$total_pages = ceil($total_rows / $limit);

$page_title = 'Manage Requisition';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    .view-items-btn {
        background-color: #ffa500;
        color: #fff;
        padding: 10px 10px;
        font-size: 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        line-height: 1.2;
        transition: 0.2s ease;
    }

    .view-items-btn:hover {
        background-color: #e69500;
    }
</style>
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="color: #000080; font-weight: bold;">Manage Requisition</h2>
        <a href="add_requisition.php" class="btn btn-primary w-25">+ Add New Requisition</a>
    </div>
    <div class="search-box shadow-sm mb-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <input type="text" name="school_code" class="form-control" placeholder="Cost Center" value="<?= $search['school_code'] ?>">
            </div>
            <!--<div class="col-md-2">-->
            <!--    <input type="text" name="quotation_id" class="form-control" placeholder="Quotation ID" value="<?= $search['quotation_id'] ?>">-->
            <!--</div>-->
            <div class="col-md-3">
                <select name="tenure_id" class="form-control">
                    <option value="">Select Tenure</option>
                    <?php while ($t = mysqli_fetch_assoc($tenure_query)): ?>
                        <option value="<?= $t['id'] ?>" <?= ($search['tenure_id'] == $t['id']) ? 'selected' : '' ?>>
                            <?= $t['tenure'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100">Search</button>
                <a href="manage_requisition.php" class="btn btn-dark w-100">Reset</a>
            </div>
        </form>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-bordered table-hover mb-0 text-center align-middle">
                <thead class="table-light">
                    <tr style="background: #000080; color: white;">
                        <th>S.No</th>
                        <th>Institute</th>
                         <th>Head</th> 
                        <th>Items Selected</th>
                        <th width="10%">Req Date</th>
                        <!-- <th>Status</th> -->
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count = 1;
                    if (mysqli_num_rows($requisitions) > 0):
                        while ($row = mysqli_fetch_assoc($requisitions)): ?>
                            <tr>
                                <td><?= $count++ ?></td>
                                <td class="text-start ps-3">
                            <strong><?= $row['school_name'] ?></strong><br>
                            <small><?= $row['school_code'] ?></small>
                        </td>
                                 <td><span><?= $row['head_name'] ?></span></td> 
                                <td>
                                    <?php if (!empty($row['all_items'])): ?>
                                        <button
                                            class="view-items-btn"
                                            data-items="<?= htmlspecialchars($row['all_items']) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#itemsModal">
                                            View Items
                                        </button>
                                    <?php else: ?>
                                        <span class="text-danger">No items</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d-M-Y', strtotime($row['req_date'])) ?></td>
                                <!-- <td>
                                    <?php
                                    $statusClass = ($row['status'] == 'InActive') ? 'bg-danger' : (($row['status'] == 'Active') ? 'bg-success' : 'bg-danger');
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $row['status'] ?></span>
                                </td> -->
                                <td>
                                    <div class="btn-group" style="gap: 5px; display: flex; align-items: center;">

                                        <a href="../controllers/QuotationController.php?action=process_quotation&requisition_id=<?= $row['id'] ?>&school_id=<?= $row['school_id'] ?>"
                                            class="btn-process"
                                            style="background: #000080; color: white; padding: 5px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.3s;">
                                            PROCESS
                                        </a>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>

                                            <a href="edit_requisition.php?id=<?= $row['id'] ?>" style="background: #000080; color: white; padding: 5px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.3s;"><i class="fa fa-edit"></i></a>
                                        <?php endif; ?>

                                        <a href="../controllers/RequisitionController.php?action=delete_requisition&id=<?= $row['id'] ?>"
                                            onclick="return confirm('Are you sure you want to delete this requisition?');"
                                            style="background: #dc3545; color: white; padding: 5px 15px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.3s;">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>


                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="7">No Requisitions Found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <!-- Items Modal -->
            <div class="modal fade" id="itemsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title">Requisition Items</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <div id="itemsContent" style="white-space: pre-wrap;"></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">

    <!-- Showing info -->
    <div>
        Showing <?= ($offset + 1) ?> to <?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?> entries
    </div>

    <!-- Pagination -->
    <nav>
    <ul class="pagination mb-0">
        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>&school_code=<?= $search['school_code'] ?>&tenure_id=<?= $search['tenure_id'] ?>">Previous</a>
        </li>

        <?php
        $adjacents = 2; // Current page ke aage peeche kitne numbers dikhane hain

        if ($total_pages <= 7) {
            // Agar total pages kam hain toh saare dikhao
            for ($i = 1; $i <= $total_pages; $i++) {
                echo renderPageLink($i, $page, $search);
            }
        } else {
            // Agar pages zyada hain toh logic apply karein
            if ($page <= 4) {
                for ($i = 1; $i <= 5; $i++) echo renderPageLink($i, $page, $search);
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                echo renderPageLink($total_pages, $page, $search);
            } elseif ($page > 4 && $page < $total_pages - 3) {
                echo renderPageLink(1, $page, $search);
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                for ($i = $page - $adjacents; $i <= $page + $adjacents; $i++) {
                    echo renderPageLink($i, $page, $search);
                }
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                echo renderPageLink($total_pages, $page, $search);
            } else {
                echo renderPageLink(1, $page, $search);
                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                for ($i = $total_pages - 4; $i <= $total_pages; $i++) {
                    echo renderPageLink($i, $page, $search);
                }
            }
        }

        // Helper function to render links (Isse apne PHP block ke bahar ya upar rakh sakte hain)
        function renderPageLink($i, $current_page, $search) {
            $active = ($i == $current_page) ? 'active' : '';
            $url = "?page=$i&school_code={$search['school_code']}&tenure_id={$search['tenure_id']}";
            return "<li class='page-item $active'><a class='page-link' href='$url'>$i</a></li>";
        }
        ?>

        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>&school_code=<?= $search['school_code'] ?>&tenure_id=<?= $search['tenure_id'] ?>">Next</a>
        </li>
    </ul>
</nav>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const buttons = document.querySelectorAll(".view-items-btn");
        const contentDiv = document.getElementById("itemsContent");

        buttons.forEach(btn => {
            btn.addEventListener("click", function() {
                let items = this.getAttribute("data-items");

                // Optional: agar comma separated hain to line break
                items = items.replace(/,/g, '\n');

                contentDiv.innerHTML = items;
            });
        });
    });
</script>
<?php include '../includes/footer.php'; ?>