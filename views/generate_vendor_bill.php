<?php
ob_start();
require_once '../config/database.php';

// --- FPDF & FPDI Loading ---
require_once '../vendor/fpdf/fpdf/src/Fpdf/Fpdf.php';
if (!class_exists('FPDF')) {
    class_alias(\Fpdf\Fpdf::class, 'FPDF');
}
require_once '../vendor/setasign/fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

class PDF extends Fpdi {}

if (!isset($_GET['id']) || !isset($_GET['q_id'])) {
    die("Invalid Request.");
}

$v_id = mysqli_real_escape_string($conn, $_GET['id']);
$q_id = mysqli_real_escape_string($conn, $_GET['q_id']);

// 1. Fetch Data including Quotation Date
$v_sql = "SELECT v.vendor_name, v.vendor_letterhead, q.quotation_date, s.school_name 
          FROM tbl_vendor v 
          JOIN tbl_quotation q ON q.id = '$q_id'
          join tbl_manage_school s on s.id = q.school_id
          WHERE v.id = '$v_id'";
$v_res = mysqli_query($conn, $v_sql);
$data = mysqli_fetch_assoc($v_res);

// 2. Bill Date Logic (Quotation Date + 2 Days Gap, Skip Sunday)
function getBillDate($startDate)
{
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
$bill_date = getBillDate($data['quotation_date']);

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
} else {
    $pdf->addPage();
}

// Header
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetXY(15, 65);
$pdf->Cell(180, 10, "OFFICIAL BILL / INVOICE", 0, 1, 'C');

// Date ke baad aur Table se pehle school name center mein display karne ke liye
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX(15);
// School name center mein print karne ke liye
$pdf->Cell(180, 10, strtoupper($data['school_name']), 0, 1, 'C');

$pdf->Ln(5); // Thora sa mazeed gap

// Date & Info
$pdf->SetFont('Arial', '', 10);
$pdf->SetXY(15, 75);
$pdf->Cell(180, 7, "Bill Date: " . $bill_date, 0, 1, 'R');
$pdf->SetX(15);
// $pdf->Cell(180, 7, "To: Head Master / DDO", 0, 1, 'L');
$pdf->Ln(5);

// Table Header
$pdf->SetFillColor(40, 40, 40);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX(15);
$pdf->Cell(15, 9, 'S.No', 1, 0, 'C', true);
$pdf->Cell(95, 9, 'Item Description', 1, 0, 'L', true);
$pdf->Cell(20, 9, 'Qty', 1, 0, 'C', true);
$pdf->Cell(25, 9, 'Rate', 1, 0, 'R', true);
$pdf->Cell(25, 9, 'Total', 1, 1, 'R', true);

// Table Body
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 10);
$sno = 1;
$grandTotal = 0;

while ($item = mysqli_fetch_assoc($items_res)) {
    $total = (float)$item['price'] * (int)$item['quantity'];
    $grandTotal += $total;
    $pdf->SetX(15);
    $pdf->Cell(15, 8, $sno++, 1, 0, 'C');
    $pdf->Cell(95, 8, $item['item_name'], 1, 0, 'L');
    $pdf->Cell(20, 8, $item['quantity'], 1, 0, 'C');
    $pdf->Cell(25, 8, number_format($item['price']), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($total), 1, 1, 'R');
}

// Grand Total
$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(155, 9, 'GRAND TOTAL (PKR) ', 1, 0, 'R');
$pdf->Cell(25, 9, number_format($grandTotal), 1, 1, 'R');

if (ob_get_length()) ob_end_clean();
$pdf->Output('I', 'Vendor_Bill.pdf');
exit();
