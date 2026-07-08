<?php
require_once '../config/database.php';
require_once '../controllers/AuthController.php';
checkUserAuth();

// Inputs handle karna
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$item_type = isset($_GET['item_type']) ? mysqli_real_escape_string($conn, $_GET['item_type']) : '';

// Base Query (Assuming 'item_type' column name in tbl_item)
$query = "SELECT i.id AS item_id, i.item_name, i.min_price, i.max_price, i.percent_of_budget, i.status, i.item_category,
          GROUP_CONCAT(h.head_name SEPARATOR ', ') AS assigned_heads
          FROM tbl_item AS i
          INNER JOIN tbl_item_head_type AS it ON i.id = it.item_id
          INNER JOIN tbl_heads AS h ON h.id = it.head_type_id
          WHERE i.status = 'Active'";

// Search Filter
if ($search != '') {
    $query .= " AND (i.item_name LIKE '%$search%' OR h.head_name LIKE '%$search%')";
}

// Primary/Secondary Filter
if ($item_type != '') {
    $query .= " AND i.item_category = '$item_type'";
}

$query .= " GROUP BY i.id ORDER BY i.id DESC";
$result = mysqli_query($conn, $query);

$page_title = 'Manage Items';
include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    :root {
        --primary-blue: #000080;
        --bg-light: #f4f7fe;
        --text-dark: #2d3748;
        --text-muted: #718096;
        --border-color: #edf2f7;
    }

    body {
        background-color: var(--bg-light);
        font-family: 'Poppins', sans-serif;
    }

    .content-wrapper {
        padding: 30px;
    }

    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .page-title {
        color: var(--text-dark);
        font-weight: 700;
        font-size: 24px;
        margin: 0;
    }

    .btn-add {
        background: var(--primary-blue);
        color: white;
        padding: 10px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-add:hover {
        background: #000066;
        color: white;
    }

    .table-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
    }

    .custom-table th {
        color: var(--text-muted);
        text-transform: uppercase;
        font-size: 12px;
        padding: 15px;
        border-bottom: 2px solid var(--border-color);
        text-align: left;
    }

    .custom-table td {
        padding: 16px 15px;
        color: var(--text-dark);
        font-size: 14px;
        border-bottom: 1px solid var(--border-color);
    }

    /* Filters Styling */
    .filter-input {
        padding: 10px 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        outline: none;
        font-size: 14px;
    }

    .filter-input:focus {
        border-color: var(--primary-blue);
    }

    .type-badge {
        background: #e2e8f0;
        color: #475569;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }

    .edit-link {
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: 600;
    }
</style>

<div class="content-wrapper">
    <div class="header-row">
        <h2 class="page-title">Manage Items</h2>
        <a href="add_new_item.php" class="btn-add">+ Add New Item</a>
    </div>

    <!-- Filters Section -->
    <div class="search-container" style="margin-bottom: 25px;">
        <form method="GET" action="" style="display: flex; gap: 15px; align-items: center;">
            <!-- Search Input (Bara kar diya hai) -->
            <input type="text" name="search" class="filter-input"
                placeholder="Search by name or head..."
                value="<?php echo htmlspecialchars($search); ?>"
                style="width: 400px;">

            <!-- Item Type Filter -->
            <select name="item_type" class="filter-input" style="width: 200px;">
                <option value="">Item Types</option>
                <option value="Primary" <?php if ($item_type == 'Primary') echo 'selected'; ?>>Primary</option>
                <option value="Secondary" <?php if ($item_type == 'Secondary') echo 'selected'; ?>>Secondary</option>
            </select>

            <button type="submit" class="btn-add">Apply Filters</button>

            <?php if ($search != '' || $item_type != ''): ?>
                <a href="manage_items.php" class="edit-link" style="font-size: 14px;">Clear All</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-card">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 60px;">S.No</th>
                    <th>Type</th>
                    <th>Head Type</th>
                    <th>Item Name</th>
                    <th>Min Price</th>
                    <th>Max Price</th>
                    <th>% Budget</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sno = 1;
                if (mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)):
                ?>
                        <tr>
                            <td><?php echo $sno++; ?></td>
                            <td>
                                <b style="color: <?php echo ($row['item_category'] == 'Primary') ? '#2c7be5' : '#f6ad55'; ?>;">
                                    <?php echo $row['item_category']; ?>
                                </b>
                            </td>
                            <td>
                                <span class="type-badge">
                                    <?php echo $row['assigned_heads'] ? $row['assigned_heads'] : 'N/A'; ?>
                                </span>
                            </td>
                            <td><span style="font-weight: 600;"><?php echo $row['item_name']; ?></span></td>
                            <td><?php echo number_format($row['min_price'], 2); ?></td>
                            <td><?php echo number_format($row['max_price'], 2); ?></td>
                            <td><?php echo $row['percent_of_budget']; ?>%</td>
                            <td style="text-align: center;">
                                <!-- manage_items.php ke andar -->
                                <a href="edit_item.php?id=<?php echo $row['item_id']; ?>&search=<?php echo urlencode($search); ?>&item_type=<?php echo urlencode($item_type); ?>" class="edit-link">Edit</a>
                            </td>
                        </tr>
                    <?php
                    endwhile;
                else:
                    ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            No items found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>