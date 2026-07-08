<?php
require_once '../config/database.php';
require_once '../config/paths.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/SchoolController.php';
require_once '../includes/crypto.php';

checkUserAuth();

// Show errors (remove after debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch current data
$encrypted_id = $_GET['id'] ?? '';
$id = decrypt_id($encrypted_id);

if (!$id) {
    die("Invalid ID");
}
$id = mysqli_real_escape_string($conn, $id);

$query = mysqli_query($conn, "SELECT * FROM tbl_manage_school WHERE id = '$id'");
$data = mysqli_fetch_assoc($query);

include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
    .form-container {
        padding: 40px;
        max-width: 1000px;
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
        font-size: 14px;
    }

    .custom-input {
        border-radius: 8px;
        padding: 12px;
        border: 1px solid #dee2e6;
        margin-bottom: 20px;
        width: 100%;
        outline: none;
        background: #fafafa;
    }

    .btn-save-custom {
        background: #ff9800;
        color: white;
        border: none;
        padding: 15px;
        border-radius: 8px;
        width: 100%;
        font-weight: bold;
        cursor: pointer;
    }

    .row-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }

    .img-preview {
        width: 80px;
        height: 80px;
        object-fit: contain;
        border: 1px solid #ddd;
        border-radius: 5px;
        background: #fff;
        margin-bottom: 10px;
    }
</style>

<div class="form-container">
    <h4 style="color:#000080;font-weight:bold;margin-bottom:25px;">Edit School Details</h4>
    <div class="form-card">
        <form action="" method="POST" enctype="multipart/form-data">

            <div class="row-grid">
                <div>
                    <label class="form-label">District</label>
                    <input type="text" name="district" class="custom-input" value="<?php echo $data['district']; ?>" required>
                </div>
                <div>
                    <label class="form-label">SEMIS Code</label>
                    <input type="text" name="semis_code" class="custom-input" value="<?php echo $data['semis_code']; ?>" required>
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Institute Code</label>
                    <input type="text" name="school_code" class="custom-input" value="<?php echo $data['school_code']; ?>" readonly required>
                </div>
                <div>
                    <label class="form-label">Institute Name</label>
                    <input type="text" name="school_name" class="custom-input" value="<?php echo $data['school_name']; ?>" required>
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">No. of Students</label>
                    <input type="number" name="no_of_students" class="custom-input" value="<?php echo $data['no_of_students']; ?>" required>
                </div>
                <div>
                    <label class="form-label">Enrollment</label>
                    <input type="number" name="enrollment" class="custom-input" value="<?php echo $data['enrollment']; ?>" required>
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Demand No</label>
                    <input type="text" name="demand_no" class="custom-input" value="<?php echo $data['demand_no']; ?>" placeholder="Enter Demand No" required>
                </div>
                <div>
                    <label class="form-label">Institute Acronym</label>
                    <input type="text" name="acronym" class="custom-input" value="<?php echo $data['acronym']; ?>">
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Principal Signature</label>
                    <?php if (!empty($data['principal_signature'])) { ?>
                        <img src="<?php echo SIGN_URL . $data['principal_signature']; ?>" class="img-preview" style="display:block; width:80px; margin-bottom:5px;">
                    <?php } ?>
                    <input type="file" name="principal_signature" class="custom-input" accept="image/*">
                </div>
                <div>
                    <label class="form-label">Institute Stamp</label>
                    <?php if (!empty($data['school_stamp'])) { ?>
                        <img src="<?php echo STAMP_URL . $data['school_stamp']; ?>" class="img-preview" style="display:block; width:80px; margin-bottom:5px;">
                    <?php } ?>
                    <input type="file" name="school_stamp" class="custom-input" accept="image/*">
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Institute Logo</label>
                    <?php if (!empty($data['school_logo'])) { ?>
                        <img src="<?php echo LOGO_URL . $data['school_logo']; ?>" class="img-preview" style="display:block; width:80px; margin-bottom:5px;">
                    <?php } ?>
                    <input type="file" name="school_logo" class="custom-input" accept="image/*">
                </div>
                <?php if ($_SESSION['role'] === 'admin') { ?>
                <div class="mt-5">
                    <label class="form-label mt-4">Status</label>
                    <select name="status" class="custom-input">
                        <option value="Active" <?php if ($data['STATUS'] == 'Active') echo 'selected'; ?>>Active</option>
                        <option value="Inactive" <?php if ($data['STATUS'] == 'Inactive') echo 'selected'; ?>>Inactive</option>
                    </select>
                </div>
                <?php } ?>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Section</label>
                    <input type="text" name="section" class="custom-input" value="<?php echo $data['section']; ?>" placeholder="Enter Section (e.g., A, B, C)">
                </div>
                <div>
                    <!-- Empty div for spacing, or you can add another field here -->
                </div>
            </div>

            <div style="margin-top: 15px;">
                <label class="form-label">Institute Address</label>
                <textarea name="school_address" class="custom-input" rows="2"><?php echo $data['school_address']; ?></textarea>
            </div>

            <?php if ($_SESSION['role'] === 'admin') { ?>
                <div>
                    <label class="form-label">Update Password (Leave blank to keep current)</label>
                    <input type="password" name="password" class="custom-input" placeholder="Enter new password">
                </div>
            <?php } ?>


            <button type="submit" name="update" class="btn-save-custom">UPDATE SCHOOL DETAILS</button>

            <div style="text-align:center;margin-top:15px;">
                <a href="manage_school.php" style="color:#000080;text-decoration:none;font-weight:600;">← Back to List</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>