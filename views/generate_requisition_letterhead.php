<?php
ob_start();
require_once '../config/database.php';
require_once '../config/paths.php';

// --- FPDF & FPDI Loading ---
require_once '../vendor/fpdf/fpdf/src/Fpdf/Fpdf.php';
if (!class_exists('FPDF')) {
    class_alias(\Fpdf\Fpdf::class, 'FPDF');
}
require_once '../vendor/setasign/fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

class PDF extends Fpdi {}

if (!isset($_GET['q_id'])) {
    die("Invalid Request. Quotation ID missing.");
}

$q_id = mysqli_real_escape_string($conn, $_GET['q_id']);

// 1. Fetch Requisition ID and School Info
$q_sql = "SELECT q.requisition_id, q.school_id, s.school_name, s.letter_head 
          FROM tbl_quotation q 
          JOIN tbl_manage_school s ON q.school_id = s.id 
          WHERE q.id = '$q_id'";
$q_res = mysqli_query($conn, $q_sql);
$q_data = mysqli_fetch_assoc($q_res);

if (!$q_data) die("Record not found!");

$req_id = $q_data['requisition_id'];
$school_letterhead = $q_data['letter_head'];

// 2. Fetch Requisition Items
$sql = "SELECT r.created_on, h.head_name, i.item_name, rd.quantity
        FROM tbl_requisition r
        JOIN tbl_heads h ON r.head_id = h.id
        JOIN tbl_requisition_details rd ON r.id = rd.requisition_id
        JOIN tbl_item i ON rd.item_id = i.id
        WHERE r.id = '$req_id'";
$res = mysqli_query($conn, $sql);

$pdf = new PDF();
// Path for School Letterhead
$letterheadPath = "../uploads/school_letterhead/" . $school_letterhead;

// Import Letterhead if exists
if (!empty($school_letterhead) && file_exists($letterheadPath)) {
    $pdf->setSourceFile($letterheadPath);
    $tplIdx = $pdf->importPage(1);
    $pdf->addPage();
    $pdf->useTemplate($tplIdx, 0, 0, 210, 297);
} else {
    $pdf->addPage();
}

// --- Overlaying Content ---

// Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetXY(15, 75); // Positioning after letterhead header
$pdf->Cell(180, 10, "REQUISITION FORM", 0, 1, 'C');

// Requisition Info (Head & Date)
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetXY(15, 88);
$first_row = mysqli_fetch_assoc($res); // Pehli row se date aur head name lein
mysqli_data_seek($res, 0); // Pointer wapis start par le jayein table ke liye

$pdf->Cell(90, 7, "Object Head: " . ($first_row['head_name'] ?? 'N/A'), 0, 0, 'L');
$pdf->SetFont('Arial', '', 11);
$date_val = isset($first_row['created_on']) ? date('d-M-Y', strtotime($first_row['created_on'])) : date('d-M-Y');
$pdf->Cell(90, 7, "Date: " . $date_val, 0, 1, 'R');

$pdf->Ln(5);

// Table Header
$pdf->SetFillColor(240, 240, 240); // Light gray for requisition header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX(15);
$pdf->Cell(20, 10, 'S.No', 1, 0, 'C', true);
$pdf->Cell(120, 10, 'Item Description', 1, 0, 'L', true);
$pdf->Cell(40, 10, 'Required Qty', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Arial', '', 10);
$sno = 1;

while ($item = mysqli_fetch_assoc($res)) {
    $pdf->SetX(15);
    // MultiCell use kar sakte hain agar item name bara ho, filhal simple Cell:
    $pdf->Cell(20, 8, $sno++, 1, 0, 'C');
    $pdf->Cell(120, 8, $item['item_name'], 1, 0, 'L');
    $pdf->Cell(40, 8, $item['quantity'], 1, 1, 'C');
}

// Footer Signature Area
$pdf->Ln(30);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetX(120);
$pdf->Cell(75, 5, "_______________________", 0, 1, 'C');
$pdf->SetX(120);
$pdf->Cell(75, 5, "Head Master / D.D.O", 0, 1, 'C');
$pdf->SetX(120);
$pdf->Cell(75, 5, strtoupper($q_data['school_name']), 0, 1, 'C');

if (ob_get_length()) ob_end_clean();
$pdf->Output('I', 'Requisition_Form.pdf');
exit();
