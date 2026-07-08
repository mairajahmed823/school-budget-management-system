<?php
require_once '../config/database.php';
require_once '../vendor/fpdf/fpdf/src/Fpdf/Fpdf.php';
if (!class_exists('FPDF')) {
    class_alias(\Fpdf\Fpdf::class, 'FPDF');
}
require_once '../vendor/setasign/fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

$q_id = $_GET['q_id'];
$v_id = $_GET['v_id'];

if (!$q_id || !$v_id) {
    die("Invalid Request: IDs are missing.");
}

// --- 1. Main Data Query ---
$sql = "SELECT q.*, s.school_name, s.school_logo, s.semis_code, s.school_code, s.acronym, h.code_no, v.vendor_name, v.vendor_letterhead, h.head_name 
        FROM tbl_quotation q
        JOIN tbl_manage_school s ON q.school_id = s.id
        JOIN tbl_vendor v ON v.id = '$v_id'
        JOIN tbl_heads h ON q.head_id = h.id
        WHERE q.id = '$q_id'";

$res = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($res);

// --- 2. Vendors List ---
$vendors_query = mysqli_query($conn, "SELECT DISTINCT v.id, v.vendor_name FROM tbl_quotation_details qd 
    JOIN tbl_vendor v ON qd.vendor_id = v.id WHERE qd.quotation_id = '$q_id'");
$v_list = mysqli_fetch_all($vendors_query, MYSQLI_ASSOC);

// --- 3. Items List ---
$items = mysqli_query($conn, "SELECT DISTINCT i.id, i.item_name FROM tbl_quotation_details qd 
    JOIN tbl_item i ON qd.item_id = i.id WHERE qd.quotation_id = '$q_id'");

// --- PDF Generation Start ---
$pdf = new FPDF();
$pdf->AddPage('L'); // Landscape

// 1. School Name
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(280, 7, $data['school_name'], 0, 1, 'C');

$pdf->Ln(10); // School name ke nichay gap

// 2. Comparative Statement Title
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(280, 10, "Comparative Statement", 0, 1, 'C');
$pdf->Ln(2);

// 3. Sub-titles (No gap between these two)
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(280, 6, "of quotations received for", 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(280, 6, $data['code_no'] . ' ' . $data['head_name'], 0, 1, 'C');

$pdf->Ln(5);

// --- Table Header ---
$pdf->SetX(10);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(10, 10, 'S#', 1, 0, 'C');
$pdf->Cell(70, 10, 'Item Name', 1, 0, 'C');

foreach ($v_list as $v) {
    // Width 45 per vendor (adjust if many vendors)
    $pdf->Cell(45, 10, $v['vendor_name'], 1, 0, 'C');
}
$pdf->Ln();

// --- Table Body ---
$pdf->SetFont('Arial', '', 9);
$sno = 1;
while ($it = mysqli_fetch_assoc($items)) {
    $pdf->SetX(10);
    $pdf->Cell(10, 8, $sno++, 1, 0, 'C');
    $pdf->Cell(70, 8, $it['item_name'], 1, 0, 'L');

    foreach ($v_list as $v) {
        $price_sql = "SELECT price FROM tbl_quotation_details 
                      WHERE quotation_id='$q_id' AND vendor_id='{$v['id']}' AND item_id='{$it['id']}'";
        $p_res = mysqli_query($conn, $price_sql);
        $p = mysqli_fetch_assoc($p_res);

        $pdf->Cell(45, 8, number_format($p['price'] ?? 0), 1, 0, 'R');
    }
    $pdf->Ln();
}

// --- Footer Text ---
$pdf->Ln(15);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(280, 10, "The lowest rate offered by " . $data['vendor_name'] . " which has been approved", 0, 1, 'L');

// Output
$pdf->Output('I', 'Comparative_Statement.pdf'); // 'I' se browser mein open hoga, 'D' se download
