<?php
ob_start();
require_once '../config/database.php';
require_once '../vendor/fpdf/fpdf/src/Fpdf/Fpdf.php';
if (!class_exists('FPDF')) { class_alias(\Fpdf\Fpdf::class, 'FPDF'); }
require_once '../vendor/setasign/fpdi/src/autoload.php';
use setasign\Fpdi\Fpdi;

class PDF extends Fpdi {}

$v_id = mysqli_real_escape_string($conn, $_GET['id']);
$q_id = mysqli_real_escape_string($conn, $_GET['q_id']);

// 1. Fetch Data with School Name
$v_sql = "SELECT v.vendor_name, v.vendor_letterhead, q.quotation_date, s.school_name 
          FROM tbl_vendor v 
          JOIN tbl_quotation q ON q.id = '$q_id'
          JOIN tbl_manage_school s ON s.id = q.school_id
          WHERE v.id = '$v_id'";
$v_res = mysqli_query($conn, $v_sql);
$data = mysqli_fetch_assoc($v_res);

// 2. Date Logic (Same as Bill Date)
function getChallanDate($startDate) {
    $date = new DateTime($startDate);
    $gap = 0;

    // Jab tak 2 valid days (excl. Sunday) guzar na jayen
    while ($gap <= 2) {
        // Pehle check karein ke current din Sunday toh nahi
        // Agar Sunday nahi hai, toh gap count karein
        if ($date->format('N') != 7) { 
            $gap++; 
        }

        // Agar gap abhi 2 poore nahi hue, toh agle din par jayen
        if ($gap <= 3) {
            $date->modify('+1 day');
        }
    }

    return $date->format('d-M-Y');
}

// Example:
// Input: 10-Mar-2026 (Tuesday)
// Logic: 10 (Day 1), 11 (Day 2), 12 (Day 3) -> Output: 13-Mar-2026
$challan_date = getChallanDate($data['quotation_date']);
$items_sql = "SELECT qd.*, i.item_name FROM tbl_quotation_details qd
              JOIN tbl_item i ON qd.item_id = i.id
              WHERE qd.quotation_id = '$q_id' AND qd.vendor_id = '$v_id'";
$items_res = mysqli_query($conn, $items_sql);

$pdf = new PDF();
$letterheadPath = "../uploads/vendor_letterheads/" . $data['vendor_letterhead'];

if (!empty($data['vendor_letterhead']) && file_exists($letterheadPath)) {
    $pdf->setSourceFile($letterheadPath);
    $tplIdx = $pdf->importPage(1);
    $pdf->addPage();
    $pdf->useTemplate($tplIdx, 0, 0, 210, 297);
} else { $pdf->addPage(); }

// Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetXY(15, 65);
$pdf->Cell(180, 10, "DELIVERY CHALLAN", 0, 1, 'C');

// Date & School Name
$pdf->SetFont('Arial', '', 10);
$pdf->SetXY(15, 75);
$pdf->Cell(180, 7, "Challan Date: " . $challan_date, 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(180, 10, strtoupper($data['school_name']), 0, 1, 'C');

// $pdf->SetFont('Arial', '', 10);
// $pdf->Cell(180, 7, "To: Head Master / DDO", 0, 1, 'L');
// $pdf->Ln(5);

// Table Header (Prices Hataye gaye hain)
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX(30);
$pdf->Cell(20, 9, 'S.No', 1, 0, 'C', true);
$pdf->Cell(100, 9, 'Item Description', 1, 0, 'L', true);
$pdf->Cell(30, 9, 'Quantity', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Arial', '', 10);
$sno = 1;
while ($item = mysqli_fetch_assoc($items_res)) {
    $pdf->SetX(30);
    $pdf->Cell(20, 8, $sno++, 1, 0, 'C');
    $pdf->Cell(100, 8, $item['item_name'], 1, 0, 'L');
    $pdf->Cell(30, 8, $item['quantity'], 1, 1, 'C');
}

// $pdf->Ln(20);
// $pdf->SetX(15);
// $pdf->Cell(90, 7, "Received By: ________________", 0, 0, 'L');
// $pdf->Cell(90, 7, "Authorized Signature: ________________", 0, 1, 'R');

if (ob_get_length()) ob_end_clean();
$pdf->Output('I', 'Delivery_Challan.pdf');
exit();