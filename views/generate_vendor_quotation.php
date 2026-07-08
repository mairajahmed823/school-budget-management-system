<?php
ob_start();

require_once '../config/database.php';
require_once '../config/paths.php';

// --- 1. FPDF ko global namespace mein load karo ---
require_once '../vendor/fpdf/fpdf/src/Fpdf/Fpdf.php';

// FPDI ko global FPDF class ki zaroorat hai - yeh alias banao
if (!class_exists('FPDF')) {
    class_alias(\Fpdf\Fpdf::class, 'FPDF');
}

// --- 2. Ab FPDI load karo ---
require_once '../vendor/setasign/fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

// --- 3. Custom PDF class ---
class PDF extends Fpdi {
    // Ab SetX() aur baaki sab methods kaam karenge
}

if (!isset($_GET['id']) || !isset($_GET['q_id'])) {
    die("Invalid Request.");
}

$v_id = mysqli_real_escape_string($conn, $_GET['id']);
$q_id = mysqli_real_escape_string($conn, $_GET['q_id']);

// Database Data
$v_sql = "SELECT vendor_name, vendor_letterhead FROM tbl_vendor WHERE id = '$v_id'";
$v_res = mysqli_query($conn, $v_sql);
$vendor = mysqli_fetch_assoc($v_res);

$items_sql = "SELECT qd.*, i.item_name
              FROM tbl_quotation_details qd
              JOIN tbl_item i ON qd.item_id = i.id
              WHERE qd.quotation_id = '$q_id' AND qd.vendor_id = '$v_id'";
$items_res = mysqli_query($conn, $items_sql);

// --- 4. PDF Object ---
$pdf = new PDF();

// Letterhead logic
$letterheadName = $vendor['vendor_letterhead'] ?? '';
$letterheadPath = "../uploads/vendor_letterheads/" . $letterheadName;

if (!empty($letterheadName) && file_exists($letterheadPath)) {
    try {
        $pdf->setSourceFile($letterheadPath);
        $tplIdx = $pdf->importPage(1);
        $pdf->addPage();
        $pdf->useTemplate($tplIdx, 0, 0, 210, 297);
    } catch (Exception $e) {
        $pdf->addPage();
    }
} else {
    $pdf->addPage();
}

// --- 5. DATA PRINTING ---
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 0, 128);
$pdf->SetXY(15, 65);
$pdf->Cell(180, 10, "OFFICIAL QUOTATION", 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(180, 7, strtoupper($vendor['vendor_name']), 0, 1, 'C');
$pdf->Ln(10);

// Table Header
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX(15);
$pdf->Cell(15, 9, 'S.No', 1, 0, 'C', true);
$pdf->Cell(95, 9, 'Item Description', 1, 0, 'L', true);
$pdf->Cell(20, 9, 'Qty', 1, 0, 'C', true);
$pdf->Cell(25, 9, 'Rate', 1, 0, 'R', true);
$pdf->Cell(25, 9, 'Total', 1, 1, 'R', true);

// Table Body
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

// Grand Total Row
$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(155, 9, 'GRAND TOTAL (PKR) ', 1, 0, 'R', true);
$pdf->Cell(25, 9, number_format($grandTotal), 1, 1, 'R', true);

// Buffer Clean
if (ob_get_length()) ob_end_clean();

$pdf->Output('I', 'Quotation.pdf');
exit();