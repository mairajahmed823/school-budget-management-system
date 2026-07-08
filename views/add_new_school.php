<?php
require_once '../config/database.php';
require_once '../config/paths.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/SchoolController.php';

checkUserAuth();

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

    .custom-input:focus {
        border-color: #ff9800;
        background: #fff;
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
        text-transform: uppercase;
    }

    .row-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }
</style>

<div class="form-container">
    <h4 style="color: #000080; font-weight: bold; margin-bottom: 25px;">Add New Institute</h4>
    <div class="form-card">
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="row-grid">
                <div>
                    <label class="form-label">District</label>
                    <input type="text" name="district" class="custom-input" placeholder="Enter District" required>
                </div>
                <div>
                    <label class="form-label">SEMIS Code</label>
                    <input type="text" name="semis_code" class="custom-input" placeholder="Enter SEMIS Code" required>
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Cost Center</label>
                    <input type="text" name="school_code" class="custom-input" placeholder="Cost Center" required>
                </div>
                <div>
                    <label class="form-label">Institute Name</label>
                    <input type="text" name="school_name" class="custom-input" placeholder="Enter School Name" required>
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">No. of Students</label>
                    <input type="number" name="no_of_students" class="custom-input" placeholder="Enter No Of Students" required>
                </div>
                <div>
                    <label class="form-label">Enrollment</label>
                    <input type="number" name="enrollment" class="custom-input" placeholder="Enter Enrollment" required>
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Principal Signature</label>
                    <input type="file" name="principal_signature" class="custom-input" accept="image/*">
                </div>
                <div>
                    <label class="form-label">Institute Stamp</label>
                    <input type="file" name="school_stamp" class="custom-input" accept="image/*">
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Institute Logo</label>
                    <input type="file" name="school_logo" class="custom-input" accept="image/*">
                </div>
                <div>
                    <label class="form-label">Institute Acronym</label>
                    <input type="text" name="acronym" class="custom-input" placeholder="School Short Name">
                </div>
            </div>

            <div class="row-grid">
                <div>
                    <label class="form-label">Demand No</label>
                    <input type="text" name="demand_no" class="custom-input" placeholder="Enter Demand Number" required>
                </div>
                <div>
                    <label class="form-label">Section</label>
                    <input type="text" name="section" class="custom-input" placeholder="Enter Section (e.g., A, B, C)">
                </div>
            </div>

            <label class="form-label">Institute Address</label>
            <textarea name="school_address" class="custom-input" rows="3" placeholder="Enter Description" required></textarea>

            <button type="submit" name="save" class="btn-save-custom">REGISTER SCHOOL</button>

            <div style="text-align: center; margin-top: 15px;">
                <a href="manage_school.php" style="color: #000080; text-decoration: none; font-weight: 600;">← Back to List</a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>