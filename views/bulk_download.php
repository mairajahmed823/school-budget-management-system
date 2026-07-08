<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '1024M');
set_time_limit(600);
ob_start();

require_once '../config/database.php';
require_once '../controllers/AuthController.php';
require_once '../config/paths.php';
require_once '../vendor/autoload.php';
checkUserAuth();

use Dompdf\Dompdf;
use Dompdf\Options;

// --- FPDF & FPDI Loading ---
require_once '../vendor/fpdf/fpdf/src/Fpdf/Fpdf.php';
if (!class_exists('FPDF')) {
    class_alias(\Fpdf\Fpdf::class, 'FPDF');
}
require_once '../vendor/setasign/fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

require_once '../includes/crypto.php';

$encrypted_id = $_GET['school_id'] ?? '';
$school_id = decrypt_id($encrypted_id);

if (!$school_id) {
    die("Invalid ID");
}

$school_id = mysqli_real_escape_string($conn, $school_id);

$tenure_val = mysqli_real_escape_string($conn, $_GET['tenure']);

$q_list_sql = "SELECT 
                    q.id as q_id, 
                    q.winner_vendor_id as v_id 
               FROM tbl_quotation q
               JOIN tbl_requisition r ON q.requisition_id = r.id
               WHERE r.school_id = '$school_id' 
               AND r.tenure_id = (SELECT id FROM tbl_tenure WHERE tenure = '$tenure_val' LIMIT 1)
               AND q.winner_vendor_id IS NOT NULL
               ORDER BY q.id ASC";

$q_list_res = mysqli_query($conn, $q_list_sql);

if (mysqli_num_rows($q_list_res) == 0) {
    die("<script>alert('No generated documents found for this tenure!'); window.close();</script>");
}



class MasterGenerator extends Fpdi
{
    function amountInWords($number)
    {
        $hyphen      = '-';
        $conjunction = ' '; // " and " ko space se replace kiya for hundreds
        $separator   = ', ';
        $negative    = 'negative ';
        $decimal     = ' point ';
        $dictionary  = array(
            0                   => 'zero',
            1                   => 'one',
            2                   => 'two',
            3                   => 'three',
            4                   => 'four',
            5                   => 'five',
            6                   => 'six',
            7                   => 'seven',
            8                   => 'eight',
            9                   => 'nine',
            10                  => 'ten',
            11                  => 'eleven',
            12                  => 'twelve',
            13                  => 'thirteen',
            14                  => 'fourteen',
            15                  => 'fifteen',
            16                  => 'sixteen',
            17                  => 'seventeen',
            18                  => 'eighteen',
            19                  => 'nineteen',
            20                  => 'twenty',
            30                  => 'thirty',
            40                  => 'forty', // Spelling fixed: fourty -> forty
            50                  => 'fifty',
            60                  => 'sixty',
            70                  => 'seventy',
            80                  => 'eighty',
            90                  => 'ninety',
            100                 => 'hundred',
            1000                => 'thousand',
            1000000             => 'million',
            1000000000          => 'billion',
            1000000000000       => 'trillion'
        );

        if (!is_numeric($number)) return false;

        if ($number < 0) return $negative . $this->amountInWords(abs($number));

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens   = ((int) ($number / 10)) * 10;
                $units  = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds  = (int)($number / 100);
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    // Yahan "and" hata kar simple space diya gaya hai
                    $string .= ' ' . $this->amountInWords($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;

                $string = $this->amountInWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    // Thousand ke baad "and" sirf tab aaye jab remainder 100 se kam ho
                    $string .= $remainder < 100 ? ' And ' : $separator;
                    $string .= $this->amountInWords($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction) && (int)$fraction > 0) {
            $string .= $decimal;
            $words = array();
            foreach (str_split((string) $fraction) as $num) {
                $words[] = $dictionary[$num];
            }
            $string .= implode(' ', $words);
        }

        return ucwords($string);
    }
    // ... baqi code wese hi rahega

    function StyledHeader($header, $widths)
    {
        $this->SetFillColor(240, 240, 240);
        $this->SetFont('Arial', 'B', 9);
        $this->SetX(15);
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($widths[$i], 9, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetFont('Arial', '', 9);
    }

    function SafeImage($file, $x, $y, $w)
    {
        if (!file_exists($file) || empty($file)) return;
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'temp_logo_' . uniqid() . '.jpg';
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($extension == 'png') {
            $img = @imagecreatefrompng($file);
            if ($img) {
                $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                imagejpeg($bg, $tempFile, 90);
                $this->Image($tempFile, $x, $y, $w);
                imagedestroy($img);
                imagedestroy($bg);
                @unlink($tempFile);
                return;
            }
        }
        $this->Image($file, $x, $y, $w);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Times', 'I', 8);
        $this->Cell(0, 10, $this->PageNo(), 0, 0, 'R');
    }
}

// ─── Date Calculation Function ───────────────────────────────
// --- Nayi Date Function: Sirf 1 din agay barhane ke liye (Sunday skip) ---
function getNextWorkingDay($dateString)
{
    $date = new DateTime($dateString);
    $date->modify('+1 day');

    // Agar Sunday (7) hai to ek aur din add kardo (Monday)
    if ($date->format('N') == 7) {
        $date->modify('+1 day');
    }
    return $date->format('d-M-Y');
}

function getNextWorkingDayObj($dateString)
{
    $date = new DateTime($dateString);
    $date->modify('+1 day');
    if ($date->format('N') == 7) {
        $date->modify('+1 day');
    }
    return $date;
}

$pdf = new MasterGenerator();
$pdf->SetAutoPageBreak(true, 15);

while ($q_row = mysqli_fetch_assoc($q_list_res)) {
    $q_id = $q_row['q_id']; // Har baar naye quotation ki ID
    $v_id = $q_row['v_id'];

    // ─── GET Params ───────────────────────────────────────────────
    // $v_id = mysqli_real_escape_string($conn, $_GET['v_id']);
    // $q_id = mysqli_real_escape_string($conn, $_GET['q_id']);

    // ─── Master Data ─────────────────────────────────────────────
    $sql = "SELECT q.*, s.school_name, s.school_logo, s.semis_code, s.school_code, s.acronym, s.demand_no, h.code_no,
                   v.vendor_name, v.vendor_letterhead, v.vendor_logo, h.head_name, r.req_date, s.district,
               o.doc_no, t.tenure as tenure_name 
        FROM tbl_quotation q
        JOIN tbl_manage_school s ON q.school_id = s.id
        JOIN tbl_vendor v ON v.id = '$v_id'
        JOIN tbl_heads h ON q.head_id = h.id
        JOIN tbl_requisition r ON q.requisition_id = r.id
        JOIN tbl_tenure t ON r.tenure_id = t.id
        LEFT JOIN tbl_outward_no o ON r.id = o.requisition_id  -- <--- Yeh Join Add Karein
        WHERE q.id = '$q_id'";
    $data = mysqli_fetch_assoc(mysqli_query($conn, $sql));


    $school_acronym = $data['acronym'];
    $doc_no_padded  = sprintf("%03d", $data['doc_no']);
    $tenure_name    = $data['tenure_name'];

    $full_outward_no = "$school_acronym/$doc_no_padded/$tenure_name";

    // ─── Get all vendors first for date calculation ──────────────
    $vendors_sql = "SELECT DISTINCT v.id, v.vendor_name, v.vendor_letterhead, v.vendor_logo 
                FROM tbl_quotation_details qd
                JOIN tbl_vendor v ON qd.vendor_id = v.id
                WHERE qd.quotation_id = '$q_id'
                ORDER BY v.id ASC";
    $vendors_res = mysqli_query($conn, $vendors_sql);
    $vendors_list = mysqli_fetch_all($vendors_res, MYSQLI_ASSOC);
    $vendor_count = count($vendors_list);

    // ─── Reset vendors_res pointer for later use ─────────────────
    mysqli_data_seek($vendors_res, 0);

    // ─── Date Calculation Sequence ───────────────────────────────
    $requisition_date = date('d-M-Y', strtotime($data['req_date']));

    // Start date for quotation (Requisition + 1 day)
    $current_date_obj = getNextWorkingDayObj($requisition_date);
    $all_dates = [];

    // Generate all dates in ascending order (small to big)
    for ($i = 0; $i < $vendor_count; $i++) {
        $all_dates[$i] = $current_date_obj->format('d-M-Y');
        if ($i < $vendor_count - 1) {
            $current_date_obj = getNextWorkingDayObj($current_date_obj->format('d-M-Y'));
        }
    }

    // REVERSE: Last vendor gets smallest date, first vendor gets largest date
    $quotation_dates = array_reverse($all_dates);

    // Last quotation date (for next documents) - ye wohi rahega
    $last_quotation_obj = new DateTime($all_dates[$vendor_count - 1]); // Original last date use karo

    // Comparative Statement Date (Last Quotation + 1 Day)
    $comp_obj = getNextWorkingDayObj($last_quotation_obj->format('d-M-Y'));
    $comp_statement_date = $comp_obj->format('d-M-Y');

    // Supply Order Date (Comparative + 1 Day)
    $supply_obj = getNextWorkingDayObj($comp_statement_date);
    $supply_order_date = $supply_obj->format('d-M-Y');

    // Delivery Challan Date (Supply Order + 1 Day)
    $delivery_obj = getNextWorkingDayObj($supply_order_date);
    $delivery_date = $delivery_obj->format('d-M-Y');

    // Bill / Invoice Date (Delivery + 1 Day)
    $bill_obj = getNextWorkingDayObj($delivery_date);
    $bill_date = $bill_obj->format('d-M-Y');


    $f_year_res = mysqli_query($conn, "SELECT tenure FROM tbl_budget b JOIN tbl_tenure t ON t.id = b.tenure_id 
    WHERE b.school_id = '{$data['school_id']}' AND b.status = 'Active' AND t.tenure = '$tenure_val' LIMIT 1");
    
    $f_year_row = mysqli_fetch_assoc($f_year_res);
    $f_year = $f_year_row['tenure'] ?? '2025-26';

    $quotation_date = date('d-M-Y', strtotime($data['quotation_date']));
    // $challan_date   = getChallanDate($data['quotation_date']);

    $items_res = mysqli_query($conn, "SELECT qd.*, i.item_name FROM tbl_quotation_details qd 
    JOIN tbl_item i ON qd.item_id = i.id 
    WHERE qd.quotation_id = '$q_id' AND qd.vendor_id = '$v_id'");

    // ─── Pre-calculate grand total ────────────────────────────────
    $gt_res = mysqli_query($conn, "SELECT SUM(price * quantity) as gt FROM tbl_quotation_details 
    WHERE quotation_id = '$q_id' AND vendor_id = '$v_id'");
    $gt = mysqli_fetch_assoc($gt_res)['gt'] ?? 0;

    $vendors_sql = "SELECT DISTINCT v.id, v.vendor_name, v.vendor_letterhead, v.vendor_logo 
                FROM tbl_quotation_details qd
                JOIN tbl_vendor v ON qd.vendor_id = v.id
                WHERE qd.quotation_id = '$q_id'";
    $vendors_res = mysqli_query($conn, $vendors_sql);

    // $pdf->AddPage(); // Naya page har quotation ke liye
    // $pdf->SetFont('Arial', 'B', 20);
    // $pdf->Cell(0, 50, "PROCESSING QUOTATION ID: " . $q_id, 0, 1, 'C');

    // ─── Vendor Letterhead Template ──────────────────────────────
    $vendor_lh = "../uploads/vendor_letterheads/" . $data['vendor_letterhead'];
    $vTpl = null;

    // ─── Initialize PDF ───────────────────────────────────────────



    // ════════════════════════════════════════════════════════════
    // PAGE 2+: FORM TR-30 — FIXED FORMATTING (LIVE TO LOCAL STYLE)
    // ════════════════════════════════════════════════════════════

    // --- Fetch all data needed for TR-30 HTML ---
    $tr30_res = mysqli_query($conn, "SELECT head_id, SUM(price * quantity) as total, 
    ms.school_name, ms.id as school_id, ms.school_code, ms.demand_no,ms.section
    FROM tbl_quotation q 
    JOIN tbl_quotation_details qd ON q.id = qd.quotation_id 
    JOIN tbl_manage_school as ms ON q.school_id = ms.id
    WHERE q.id = '$q_id' AND qd.vendor_id = '$v_id' GROUP BY q.id");

    $tr30_data      = mysqli_fetch_assoc($tr30_res);
    $grand_total    = $tr30_data['total'] ?? 0;
    $sel_head_id    = $tr30_data['head_id'];
    $tr30_school    = $tr30_data['school_name'] ?? 'N/A';
    $tr30_section   = $tr30_data['section'] ?? 'N/A';
    $tr30_school_demnd_no = $tr30_data['demand_no'] ?? 'N/A';
    preg_match('/\((.*?)\)/', $tr30_school_demnd_no, $match);
    $only_demand_number = $match[1] ?? $tr30_school_demnd_no;
    $tr30_ddo        = $tr30_data['school_code'] ?? 'N/A';
    $tr30_school_id = $tr30_data['school_id'] ?? 0;

    $tr30_tenure = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT t.tenure FROM tbl_budget b 
     JOIN tbl_tenure t ON t.id = b.tenure_id 
     WHERE b.school_id = '$tr30_school_id' ORDER BY b.id DESC LIMIT 1"
    ));

    $tr30_vendor = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT vendor_name, vendor_no FROM tbl_vendor WHERE id='$v_id'"
    ));
    $tr30_head   = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT head_name, code_no, head_category FROM tbl_heads WHERE id='$sel_head_id'"
    ));

    $head_sql = "SELECT head_category FROM tbl_heads WHERE id = '" . $data['head_id'] . "'";
    $head_res = mysqli_query($conn, $head_sql);
    $head_data = mysqli_fetch_assoc($head_res);
    $head_category = $head_data['head_category'];

    if ($head_category == 'physical') {
        // Physical: GST 18% (Inclusive), WHT 5.5%
        $val_goods = round($grand_total / 1.18);
        $gst       = $grand_total - $val_goods;
        $wht       = round($grand_total * 0.055);
        $net       = $grand_total - ($gst + $wht);

        $tax = "GST";
        $gst_rate = "18%";
        $wht_rate = "5.5%";
    } elseif ($head_category == 'service') {
        // Service: GST 13% (Inclusive), WHT 15%

        $gst = round($grand_total * 0.13);
        $val_goods  = $grand_total - $gst;
        $wht        = round($grand_total * 0.15);
        $net        = $grand_total - ($gst + $wht);

        $tax = "SST";
        $gst_rate = "13%";
        $wht_rate = "15%";
    } elseif ($head_category == 'tax_free') {
        // Tax Free: No GST, No WHT
        $val_goods  = $grand_total;
        $gst        = 0;
        $wht        = 0;
        $net        = $grand_total; // Sab zero toh total hi net hai

        $tax = "SST"; // Label change kar diya
        $gst_rate = "13%";
        $wht_rate = "11%";
    }


    // --- Formatting variables ---
    $vou_v   = strtoupper(htmlspecialchars($tr30_vendor['vendor_name'] ?? ''));
    $vou_h   = htmlspecialchars(($tr30_head['code_no'] ?? '') . ' - ' . ($tr30_head['head_name'] ?? 'N/A'));
    $vou_amt = number_format($grand_total);

    $vou_w = $pdf->amountInWords($grand_total);

    // ══════════════════════════════════════════════════
    // Build LEFT & CENTER rows (Local Style Array)
    // ══════════════════════════════════════════════════
    $body_rows = [];

    $mh_res2 = mysqli_query($conn, "SELECT * FROM tbl_main_head WHERE status='active'");
    while ($mh = mysqli_fetch_assoc($mh_res2)) {

        // Main head row
        $body_rows[] = [
            'l' => "<div class='mhead'>" . htmlspecialchars($mh['head']) . "</div>",
            'c' => "<div class='mcode'>" . htmlspecialchars($mh['head_code_no']) . "</div>",
        ];

        // Sub heads
        // Seedha tbl_form_30 se fetch karo
        $ch_res2 = mysqli_query($conn, "SELECT * FROM tbl_form_30 WHERE head_id='" . $mh['id'] . "'");
        $has_sel = false;

        // Pehle selected code_no get karo
        $selected_code = "";
        $sel_head_query = mysqli_query($conn, "SELECT code_no FROM tbl_heads WHERE id = '$sel_head_id'");
        if (mysqli_num_rows($sel_head_query) > 0) {
            $sel_head_data = mysqli_fetch_assoc($sel_head_query);
            $selected_code = $sel_head_data['code_no'];
        }

        while ($ch = mysqli_fetch_assoc($ch_res2)) {
            // CODE_NO se match karo
            $isSel = ($ch['code_no'] == $selected_code);
            if ($isSel) $has_sel = true;
            $b = $isSel ? "font-weight:bold;" : "";
            $body_rows[] = [
                'l' => "<div style='font-size:11px;{$b}'>" . htmlspecialchars($ch['head_name']) . "</div>",
                'c' => "<div style='font-size:11px;{$b}'>" . htmlspecialchars($ch['code_no']) . "</div>",
            ];
        }

        // Total row for this section
        $amt = $has_sel ? number_format($grand_total) : '&nbsp;';
        $body_rows[] = [
            'l' => "<div style='text-align:right;font-weight:bold;padding-right:8px;margin-top:4px;font-size:11px;'>Total</div>",
            'c' => "<div class='tbox' style='font-size:11px;'>{$amt}</div>",
        ];
    }

    // Grand Total row
    $body_rows[] = [
        'l' => "<div style='margin-top:10px;text-align:right;font-weight:bold;font-size:12px;text-transform:uppercase;padding-right:8px;'>GRAND TOTAL: -</div>",
        'c' => "<div style='border-top:1.5px solid #000;border-bottom:3px double #000;font-weight:bold;font-size:13px;padding:3px 0;margin-top:6px;text-align:center;'>" . number_format($grand_total) . "</div>",
    ];

    // ══════════════════════════════════════════════════
    // Build RIGHT column (Tax, Vendor, Voucher Info)
    // ══════════════════════════════════════════════════
    $rowspan = count($body_rows);

    // ══════════════════════════════════════════════════
    // Page 1 ki right column - sirf vendor + tax info
    // ══════════════════════════════════════════════════

    // Kitni rows page 1 pe fit hongi calculate karo
    // Har row ~14px, page 1 body ~530px available
    $rows_per_page = 55; // tune adjust karna

    $page1_rows = array_slice($body_rows, 0, $rows_per_page);
    $page2_rows = array_slice($body_rows, $rows_per_page);

    $rowspan1 = count($page1_rows);
    $rowspan2 = count($page2_rows);

    // RIGHT COLUMN - PAGE 1 (sirf tax info)
    $right_col_p1 = '
<td width="38%" rowspan="' . $rowspan1 . '" style="vertical-align:top; padding-left:4px;">
    <div style="font-weight:bold; margin-bottom:15px; font-size:11px;">
        Vendor No: <span class="blk">' . htmlspecialchars($tr30_vendor['vendor_no'] ?? '') . '</span>
    </div>
    <div style="text-align:center; font-weight:bold; margin-bottom:100%; font-size:12px;">
        ' . $vou_v . '
    </div>
    <table class="appro" style="width:100%; border-collapse:collapse; margin-bottom:15px; border:1px solid #000;">
        <tr>
            <td colspan="2" style="text-align:center; font-weight:bold; border:1px solid #000; padding:6px 4px; font-size:10px;">
                Appropriation for Expenditure
            </td>
        </tr>
        <tr>
            <td style="border:1px solid #000; padding:6px 4px; font-size:10px;">Including this Bill <br> Amount of work bills annexed</td>
            <td style="border:1px solid #000; padding:6px 4px;">&nbsp;</td>
        </tr>
        <tr>
            <td style="border:1px solid #000; padding:6px 4px; font-size:10px;">Balance Available:</td>
            <td style="border:1px solid #000; padding:6px 4px;">&nbsp;</td>
        </tr>
    </table>
<table class="tax-tbl" style="width:100%; border-collapse:collapse; font-size:11px; margin-bottom:20px;">
    <tr>
        <td style="padding:4px 2px;">Value of goods</td>
        <td style="text-align:right; padding:4px 2px;">' . number_format($val_goods) . '  a</td>
    </tr>
    <tr style="border-bottom:0.5px solid #000;">
        <td style="padding:4px 2px;">' . $tax . ' (a * ' . $gst_rate . ')</td>
        <td style="text-align:right; padding:4px 2px;">' . number_format($gst) . '  b</td>
    </tr>

       <tr>
        <td colspan="2" style="padding:8px 2px 4px 2px;">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:0px 0px; font-size:11px; width:70%;">Gross Bill Incl. Sales Tax ( a + b )</td>
                    <td style="text-align:right; padding:0px 0px; width:30%;">
                        <span class="blk">' . number_format($grand_total) . '  </span>  c
                    </td>
                </tr>
             </table>
         </td>
     </tr>
     <tr><td colspan="2" style="padding-top:12px;"></td></tr>
     <tr style="">
        <td style="padding:4px 2px;">' . $tax . ' Deduction( c * ' . $gst_rate . ')</td>
        <td style="text-align:right; padding:4px 2px;">' . number_format($gst) . '  d</td>
     </tr>
     <tr style="border-bottom:0.5px solid #000;">
        <td style="padding:4px 2px;">WHT of Income Tax (c * ' . $wht_rate . ')</td>
        <td style="text-align:right; padding:4px 2px;">' . number_format($wht) . '  e</td>
     </tr>
     <tr style="border-bottom:0.5px solid #000;">
        <td style="padding:4px 2px;">Total Deduction of Taxes ( d + e )</td>
        <td style="text-align:right; padding:4px 2px;">' . number_format($gst + $wht) . '  f</td>
     </tr>
     <!-- NET CHEQUE AMT - Fixed with top margin and proper alignment -->
     <tr>
        <td colspan="2" style="padding:12px 2px 4px 2px;">
            <table style="width:100%; border-collapse:collapse; border-top:1px solid #000; border-bottom:1px solid #000;">
                <tr>
                    <td style="padding:6px 2px; font-size:11px; width:70%;">NET CHEQUE AMT (c - f)</td>
                    <td style="text-align:right; padding:6px 2px; width:30%;">
                        <span class="blk">' . number_format($net) . '</span>  g
                    </td>
                </tr>
             </table>
         </td>
     </tr>
 </table>
</td>';

    // RIGHT COLUMN - PAGE 2 (object table)
    $right_col_p2 = '
<td width="38%" rowspan="' . $rowspan2 . '" style="vertical-align:top; padding-left:4px;">
    <table style="width:100%; border-collapse:collapse; border:1px solid #000; font-size:11px;">
        <tr>
            <td style="font-weight:bold; width:25%; border:1px solid #000; padding:6px 4px;">Vendor</td>
            <td colspan="3" style="text-align:center; font-weight:bold; border:1px solid #000; padding:6px 4px;">
                 ' . $vou_v . '
            </td>
        </tr>
        <tr style="text-align:center; font-weight:bold;">
            <td rowspan="2" style="width:15%; border:1px solid #000; padding:6px 4px;">No of sub Voucher</td>
            <td style="width:45%; border:1px solid #000; padding:6px 4px;">Object</td>
            <td rowspan="2" style="width:20%; border:1px solid #000; padding:6px 4px;">Classification</td>
            <td rowspan="2" style="width:20%; border:1px solid #000; padding:6px 4px;">Amount</td>
        </tr>
        <tr>
            <td style="text-align:center; font-size:10px; border:1px solid #000; padding:4px;">Brought Forward</td>
        </tr>
        <tr>
            <td style="height:70px; border:1px solid #000; padding:30px 4px;">&nbsp;</td>
            <td style="font-weight:bold; vertical-align:top; border:1px solid #000; padding:6px;">' . $vou_h . '</td>
            <td style="border:1px solid #000;">&nbsp;</td>
            <td style="text-align:right; vertical-align:top; border:1px solid #000; padding:6px;">' . $vou_amt . '</td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td class="blk" style="text-align:right; padding:6px; border:1px solid #000;">' . $vou_amt . '</td>
        </tr>
        <tr>
            <td colspan="4" style="font-weight:bold; padding:10px 6px; border:1px solid #000;">' . $vou_w . ' Only</td>
        </tr>
    </table>
</td>';

    // ══════════════════════════════════════════════════
    // Build Final TR rows - PAGE 1
    // ══════════════════════════════════════════════════
    $body_tr_html = '';
    foreach ($page1_rows as $i => $row) {
        $body_tr_html .= '<tr>';
        $body_tr_html .= '<td width="42%" style="vertical-align:top;">' . $row['l'] . '</td>';
        $body_tr_html .= '<td width="20%" style="vertical-align:top;text-align:center;">' . $row['c'] . '</td>';
        if ($i === 0) {
            $body_tr_html .= $right_col_p1;
        }
        $body_tr_html .= '</tr>';
    }

    // PAGE 2 rows
    $body_tr_html .= '</table><table>';
    foreach ($page2_rows as $i => $row) {
        $body_tr_html .= '<tr>';
        $body_tr_html .= '<td width="42%" style="vertical-align:top;">' . $row['l'] . '</td>';
        $body_tr_html .= '<td width="20%" style="vertical-align:top;text-align:center;">' . $row['c'] . '</td>';
        if ($i === 0) {
            $body_tr_html .= $right_col_p2;
        }
        $body_tr_html .= '</tr>';
    }
    // ══════════════════════════════════════════════════
    // Full HTML Generation
    // ══════════════════════════════════════════════════
    $tr30_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@page { size: A4 portrait; margin: 20mm 8mm 20mm 8mm; }
* { box-sizing: border-box; }
body { font-family: "Times New Roman", serif; font-size: 11px; margin: 0; padding: 0; line-height: 1.2; }
table { border-collapse: collapse; width: 100%; }
td { padding: 2px 3px; vertical-align: top; }
.blk { background-color: #000; color: #fff; font-weight: bold; padding: 2px 5px; display: inline-block; }
.mhead { font-weight: bold; text-decoration: underline; text-transform: uppercase; margin-top: 5px; font-size: 10px; }
.mcode { border-bottom: 1px solid #000; display: inline-block; width: 45px; }
.tbox { border-top: 1px solid #000; border-bottom: 1px solid #000; font-weight: bold; padding: 2px 0; text-align: center; width: 70px; margin: 2px auto; }
.info-tbl { border: 1.5px solid #000; margin-bottom: 6px; }
.info-tbl td { border: 1px solid #000; padding: 4px 6px; }
</style></head><body>

<table>
  <tr>
    <td width="20%"></td>
    <td width="60%" style="text-align:center;">
      <div style="font-weight:bold;font-size:14px;">FORM T.R. 30</div>
      <div style="font-size:10px;">(See rule 306)</div>
      <div style="font-weight:bold; font-size:12px;">Fully Vouched contingent Bill for the Month ' . date('F-Y') . '</div>
    </td>
    <td width="20%" style="text-align:right;font-weight:bold;font-size:11px;">' . htmlspecialchars($tr30_section) . '<br>' . htmlspecialchars($tr30_ddo) . '</td>
  </tr>
</table>

<table class="info-tbl">
  <tr>
    <td colspan="3">
      <table style="border:none;">
        <tr>
          <td width="20%" style="border:none;font-size:10px;vertical-align:bottom;"><strong>HEAD OF ACCOUNT</strong></td>
          <td width="60%" style="border:none;text-align:center;font-weight:bold;font-size:12px;">
            ' . htmlspecialchars($tr30_school_demnd_no) . '<br>' . htmlspecialchars($tr30_school) . '
          </td>
          <td width="20%" style="border:none;text-align:right;font-size:10px;vertical-align:bottom;">Voucher No. 01</td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td width="35%" style="font-weight:bold; font-size:11px;">DDO CODE: ' . htmlspecialchars($tr30_ddo) . '</td>
    <td style="text-align:center;font-weight:bold; font-size:11px;">Payment for ' . htmlspecialchars($tenure_name ?? '2025-26') . '</td>
    <td></td>
  </tr>
  <tr><td colspan="3" style="font-weight:bold; font-size:11px;">DEMAND NO: ' . $only_demand_number . '</td></tr>
  <tr><td colspan="3" style="font-weight:bold; font-size:11px;">DETAIL FUNCTION: ' . htmlspecialchars(($tr30_head['code_no'] ?? '') . ' - ' . ($tr30_head['head_name'] ?? '')) . '</td></tr>
  <tr>
    <td style="font-weight:bold; font-size:11px;">No. of Sub Voucher:</td>
    <td style="text-align:center;font-weight:bold; font-size:11px;">CODE NO</td>
    <td style="text-align:right;font-weight:bold; font-size:11px;">Amount Rs: <span class="blk">' . number_format($grand_total) . '</span></td>
  </tr>
</table>

<table>' . $body_tr_html . '</table>

<div style="margin-top:20px; display:flex;">
   <div>
 <div style="text-align:center;font-weight:bold;text-decoration:underline;font-size:12px;margin-bottom:8px;">CERTIFICATE</div>
    <div style="font-size:11px; line-height:1.4;">
        1. It is to certify that the amount of this bill have been checked under my supervision and found correct.<br>
        2. It is to certify that all amount of this bill had not been claimed before this.
    </div>
   </div>
   <div>


   
   </div>
</div>



</body></html>';

    // --- Render & Merge ---
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isCssFloatEnabled', true);
    $options->set('defaultFont', 'Times New Roman');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($tr30_html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $temp_tr30 = sys_get_temp_dir() . "/tr30_{$q_id}_{$v_id}.pdf";
    file_put_contents($temp_tr30, $dompdf->output());

    $pageCount = $pdf->setSourceFile($temp_tr30);
    for ($i = 1; $i <= $pageCount; $i++) {
        $tpl = $pdf->importPage($i);
        $pdf->AddPage();
        $pdf->useTemplate($tpl, 0, 0, 210);
    }
    @unlink($temp_tr30);

    //****************************************

    // ════════════════════════════════════════════════════════════
    // PAGE: SANCTION ORDER
    // ════════════════════════════════════════════════════════════
    // --- Letterhead Template Load Karo ---
    $fixed_school_lh = "../uploads/school_letterhead/letterhead school.pdf";

    if (file_exists($fixed_school_lh)) {
        $pageCount = $pdf->setSourceFile($fixed_school_lh);
        $sTpl = $pdf->importPage(1);
        $pdf->addPage('P'); // New page add ki
        // Letterhead ko background mein fit kiya
        $pdf->useTemplate($sTpl, 0, 0, 210, 297);
    } else {
        $pdf->addPage('P');
    }

    // --- Header Text Start ---
    // Letterhead ke header ke liye jagah chor kar (Y=50 se shuru kar rahay hain)
    // Aap apni requirement ke hisab se Y-axis (50) ko adjust kar saktay hain

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Times', '', 10);
    $pdf->SetXY(15, 10); // Start position after letterhead header
    // comment
    // $pdf->Cell(180, 5, "No: " . strtoupper($data['acronym']) . "." . $q_id . "/         /" . $f_year, 0, 1, 'C');

    // Note: School Name aur Govt info ab manual likhne ki zaroorat nahi agar wo Letterhead mein hain
    // Agar phir bhi likhna hai to neechay wala section rehne dein, warna delete kar dein

    $pdf->SetFont('Times', 'B', 12);
    $pdf->SetXY(35, 15);
    $pdf->MultiCell(140, 6, strtoupper($data['school_name']), 0, 'C');

    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetX(15);
    $pdf->Cell(180, 5, "EDUCATION & LITERACY DEPARTMENT", 0, 1, 'C');
    $pdf->SetX(15);
    $pdf->Cell(180, 5, "GOVERNMENT OF SINDH", 0, 1, 'C');

    $pdf->SetX(15);
    $pdf->Cell(90, 22, "Ref No: " . "$school_acronym/$doc_no_padded-SANC/$tenure_name", 0, 0, 'L');

    // --- Date Section ---
    $pdf->SetFont('Times', '', 11);
    $pdf->Ln(5);
    $current_date = (date('N') == 7) ? date('d-m-Y', strtotime('+1 day')) : date('d-m-Y');

    $pdf->Cell(180, 8, "Date: " . $current_date, 0, 1, 'R');

    $pdf->Ln(10);
    $pdf->SetFont('Times', '', 12);
    $pdf->SetX(15);
    $pdf->Cell(100, 5, "To,", 0, 1, 'L');

    // Karachi ke districts ki list (In par Accountant General aayega)
    $karachi_districts = [
        'Karachi Central',
        'Karachi East',
        'Karachi Malir',
        'Karachi South',
        'Karachi West',
        'Karachi Korangi'
    ];

    $pdf->SetFont('Times', 'B', 12);
    $pdf->SetX(15);

    if (in_array($data['district'], $karachi_districts)) {
        // Agar Karachi ka district hai
        $pdf->Cell(100, 5, "THE ACCOUNTANT GENERAL", 0, 1, 'L');
        $pdf->SetX(15);
        $pdf->Cell(100, 5, "KARACHI", 0, 1, 'L');
    } else {
        // Agar Karachi ke ilawa koi aur district hai
        $pdf->Cell(100, 5, "THE DISTRICT ACCOUNTS OFFICER", 0, 1, 'L');
        $pdf->SetX(15);
        $pdf->Cell(100, 5, strtoupper($data['district']), 0, 1, 'L');
    }

    $pdf->SetFont('Times', 'BU', 13);
    $pdf->Ln(10);
    $pdf->Cell(180, 10, "SUB: SANCTION ORDER FOR EXPENDITURE DURING C.F.Y. $f_year", 0, 1, 'C');

    // --- Sanction Text ---
    $pdf->Ln(5);
    $pdf->SetFont('Times', '', 12);
    $pdf->SetX(15);

    $sr_mapping = [
        'A03901' => 'Sr. # 4 (b) of part-I',
        'A03970' => 'Sr. # 4 (b) of part-XXII',
        'A03942' => 'Sr. # 4 (b) of part-II',
        'A09701' => 'Sr. # (a) of part-1',
        'A13201' => 'Sr. # 8(a) of part-II',
        'A13370' => 'Sr. # (a) of part-1'
    ];

    $sr_text = isset($sr_mapping[$data['code_no']]) ? $sr_mapping[$data['code_no']] : 'Sr. # (a) of part-1';

    $sanc_text = "The sanction is accorded to the incurring of an expenditure not exceeding to Rs: " . number_format($gt) . " (" . $pdf->amountInWords($gt) . " Only), under Object Code No. " . $data['code_no'] . " - " . $data['head_name'] . " in favor of " . strtoupper($data['vendor_name']) . " as required  " . $sr_text . " the second schedule annexure by delegation of power the financial rule and the power of Re-appropriation rules 2019 and amendment made from time to time.\n\nThe expenditure thus involved in chargeable to head of account " . $data['demand_no'] . " Education Department, Government of Sindh DDO CODE No. " . $data['school_code'] . " " . strtoupper($data['school_name']) . " during C.F.Y. $f_year.\n\nThe relevant original bill(s) are enclosed herewith, with request for further necessary action.";

    $pdf->MultiCell(180, 7, $sanc_text);

    // --- Signature / DDO Section ---
    $pdf->Ln(15); // MultiCell ke baad thori space di hai

    $pdf->SetFont('Times', 'B', 11);

    // --- Font Set Karein (Width calculate karne se pehle font set hona zaroori hai) ---
    $pdf->SetFont('Times', 'B', 11);

    $schoolName = strtoupper($data['school_name']);
    $ddoText = "DDO (" . $data['school_code'] . ")";

    // 1. Check karein ke DDO text bada hai ya School Name
    $widthDDO = $pdf->GetStringWidth($ddoText);
    $widthSchool = $pdf->GetStringWidth($schoolName);

    /** * Agar school name bohot bada hai (e.g. 90mm se zyada), 
     * toh hum max width 90mm rakhenge taake text wrap ho jaye.
     * Warna jitna bada text hai, उतनी hi box width hogi.
     */
    $maxAllowedWidth = 90;
    $dynamicWidth = min($maxAllowedWidth, max($widthDDO, $widthSchool));

    // 2. Right margin calculate karein (Page width 210mm - dynamic width - 10mm padding)
    $right_margin = 210 - $dynamicWidth - 10;

    // --- DDO Code Print Karein ---
    $pdf->SetX($right_margin);
    $pdf->Cell($dynamicWidth, 6, $ddoText, 0, 1, 'C');

    // --- School Name Print Karein ---
    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetX($right_margin);

    /**
     * MultiCell 'C' ke saath use karne se:
     * Agar text $dynamicWidth se chota hai, toh center rahega.
     * Agar bada hai, toh wrap ho kar bhi center hi rahega.
     */
    $pdf->MultiCell($dynamicWidth, 5, $schoolName, 0, 'C');

    //*******************************************


    // ════════════════════════════════════════════════════════════
    // PAGE: BILL / INVOICE (on vendor letterhead)
    // ════════════════════════════════════════════════════════════
    if (file_exists($vendor_lh) && !empty($data['vendor_letterhead'])) {
        $pdf->setSourceFile($vendor_lh);
        $vTpl = $pdf->importPage(1);
    }
    $pdf->addPage();
    if (isset($vTpl)) $pdf->useTemplate($vTpl, 0, 0, 210, 297);


    // 1. BILL / INVOICE FOR [HEAD NAME]
    $words = explode(" ", $data['vendor_name']);
    $initials = "";
    foreach ($words as $w) {
        $initials .= strtoupper($w[0]); // Har word ka pehla letter lega
    }
    $ref_no = $initials . "/" . $q_id . "/" . $tenure_name;

    // Title: BILL/INVOICE
    $pdf->SetXY(15, 65);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(90, 8, "Ref No: " . $ref_no, 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetX(15);
    $pdf->Cell(180, 5, "Date: " . $bill_date, 0, 1, 'R');
    // Yahan FOR aur Head Name add kar diya gaya hai
    $pdf->Ln(7);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(180, 7, "BILL / INVOICE", 0, 1, 'C');
    $pdf->Cell(180, 7, strtoupper($data['head_name']), 0, 1, 'C');

    // 2. School Name (Next line, center)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetX(15);
    $pdf->Cell(180, 6, $data['school_name'], 0, 1, 'C');
    $pdf->Ln(3);

    // 3. Date (Right align)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetX(15);
    $pdf->StyledHeader(['S.No', 'Description', 'Qty', 'Rate', 'Total'], [15, 90, 20, 25, 30]);

    mysqli_data_seek($items_res, 0);
    $s = 1;
    $bill_grand_total = 0;

    while ($row = mysqli_fetch_assoc($items_res)) {
        $subtotal = $row['price'] * $row['quantity'];
        $bill_grand_total += $subtotal;

        $x = 15; // Starting X position
        $y = $pdf->GetY();
        $lineHeight = 7;

        // Pehle check karte hain ke item name kitni lines lega
        // Ham dummy Cell use karke height calculate kar sakte hain ya fix MultiCell use karenge

        $pdf->SetX($x + 15); // Description column ki position par jao
        $pdf->MultiCell(90, $lineHeight, $row['item_name'], 1, 'L');

        $newY = $pdf->GetY(); // MultiCell ke baad cursor kahan pohoncha
        $rowHeight = $newY - $y; // Poore row ki nayi height calculate karo

        // Ab wapas piche ja kar baki cells ko nayi height ke sath fill karo
        $pdf->SetXY($x, $y);

        $pdf->Cell(15, $rowHeight, $s++, 1, 0, 'C'); // S.No
        $pdf->SetX($x + 15 + 90); // Description skip karke Qty par jao

        $pdf->Cell(20, $rowHeight, $row['quantity'], 1, 0, 'C'); // Qty
        $pdf->Cell(25, $rowHeight, number_format($row['price']), 1, 0, 'C'); // Rate
        $pdf->Cell(30, $rowHeight, number_format($subtotal), 1, 1, 'C'); // Total

        // Cursor ko agli row ke liye set karein
        $pdf->SetY($newY);
    }
    $pdf->SetX(15);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(150, 9, 'GRAND TOTAL', 1, 0, 'R');
    $pdf->Cell(30, 9, number_format($bill_grand_total), 1, 1, 'C');
    $pdf->Ln(1);
    $pdf->SetX(15);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(180, 8, "Amount in Words: " . $pdf->amountInWords($bill_grand_total) . " Only", 0, 1, 'L');

    $pdf->Ln(1);

    // --- Vendor Logo (Natural Center Alignment) ---
    $logo_path = "../uploads/vendor_logos/" . $data['vendor_logo'];

    if (!empty($data['vendor_logo']) && file_exists($logo_path)) {
        $y_pos = $pdf->GetY() + 5; // Top se 15mm padding

        // Right side par alignment ke liye:
        // Page width (210) - Logo width (35) - Right margin (15) = 160
        $x_pos = 170;

        $pdf->SafeImage($logo_path, $x_pos, $y_pos, 35);
    }

    //************************************************************************************


    // ════════════════════════════════════════════════════════════
    // PAGE: DELIVERY CHALLAN (on vendor letterhead)
    // ════════════════════════════════════════════════════════════

    if (file_exists($vendor_lh) && !empty($data['vendor_letterhead'])) {
        $pdf->setSourceFile($vendor_lh);
        $vTpl = $pdf->importPage(1);
    }
    $pdf->addPage();

    if (isset($vTpl)) $pdf->useTemplate($vTpl, 0, 0, 210, 297);

    $words = explode(" ", $data['vendor_name']);
    $initials = "";
    foreach ($words as $w) {
        $initials .= strtoupper($w[0]);
    }
    $ref_no = $initials . "/" . $q_id . "/" . $tenure_name;

    // Title: DELIVERY CHALLAN
    $pdf->SetXY(15, 68);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(90, 8, "Ref No: " . $ref_no, 0, 0, 'L');
    // Date (Right)
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetX(15);
    $pdf->Cell(180, 8, "Date: " . $delivery_date, 0, 1, 'R');

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(180, 10, "DELIVERY CHALLAN", 0, 1, 'C');
    $pdf->Cell(180, 10, strtoupper($data['head_name']), 0, 1, 'C');


    // School Name (Center) - Delivery Challan Title ke neeche
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetX(15);
    $pdf->Cell(180, 6, $data['school_name'], 0, 1, 'C');
    $pdf->Ln(5);

    // Table for Delivery Challan
    $pdf->StyledHeader(['S.No', 'Item Description', 'Quantity Delivered'], [20, 130, 30]);

    mysqli_data_seek($items_res, 0); // Pointer reset kiya taakay items dobara dikhen
    $s = 1;
    while ($row = mysqli_fetch_assoc($items_res)) {
        // Current positions aur settings
        $x = 15;
        $y = $pdf->GetY();

        $lineHeight = 5;  // Text wrap hone par lines ka gap
        $minHeight = 8;   // Ek normal row ki minimum height

        // Column Widths
        $w_sno  = 20;
        $w_item = 130;
        $w_qty  = 30;

        // 1. Pehle MultiCell se Item Name ki height calculate karein
        $pdf->SetXY($x + $w_sno, $y);
        $pdf->MultiCell($w_item, $lineHeight, $row['item_name'], 0, 'L');

        $newY = $pdf->GetY();
        $rowHeight = $newY - $y;

        // Agar text 1 line ka hai, toh minimum height set karein
        if ($rowHeight < $minHeight) {
            $rowHeight = $minHeight;
        }

        // 2. Ab wapas ja kar baqi cells aur borders draw karein
        $pdf->SetXY($x, $y);

        // Serial Number
        $pdf->Cell($w_sno, $rowHeight, $s++, 1, 0, 'C');

        // Item Name Border (Khali cell kyunki text MultiCell se likh diya hai)
        $pdf->Cell($w_item, $rowHeight, '', 1, 0, 'L');

        // Quantity (Isme '1' hai last mein taake cursor next line par jaye)
        $pdf->Cell($w_qty, $rowHeight, $row['quantity'], 1, 1, 'C');

        // 3. Agli row ke liye Y position update karein
        $pdf->SetY($y + $rowHeight);
    }

    $pdf->Ln(1);
    $logo_path = "../uploads/vendor_logos/" . $data['vendor_logo'];

    // 160 use karne se logo right side par set ho jayega (margin chor kar)
    $pdf->SafeImage($logo_path, 170, $pdf->GetY() + 10, 35);

    // ════════════════════════════════════════════════════════════
    // PAGE: SUPPLY ORDER
    // ════════════════════════════════════════════════════════════
    $fixed_school_lh = "../uploads/school_letterhead/letterhead school.pdf";

    if (file_exists($fixed_school_lh)) {
        $pageCount = $pdf->setSourceFile($fixed_school_lh);
        $sTpl = $pdf->importPage(1);
        $pdf->addPage('P', 'A4');
        $pdf->useTemplate($sTpl, 0, 0, 210, 297);
    } else {
        $pdf->addPage('P', 'A4');
    }

    // --- 2. Header Information (Positioning Adjusted for Letterhead) ---
    // Logo line hata di hai kyunke letterhead mein already hai.
    $pdf->SetTextColor(0, 0, 0);

    // School Name (Y=35 se shuru kiya hai taake letterhead ke niche aaye)
    $pdf->SetFont('Times', 'B', 13);
    $pdf->SetXY(35, 15);
    $pdf->MultiCell(140, 6, strtoupper($data['school_name']), 0, 'C');

    // Department & Government Info
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetX(15);
    $pdf->Cell(180, 6, "EDUCATION & LITERACY DEPARTMENT", 0, 1, 'C');
    $pdf->SetX(15);
    $pdf->Cell(180, 6, "GOVERNMENT OF SINDH", 0, 1, 'C');

    // --- 3. Outward No & Date (Amnay Samnay) ---
    $pdf->Ln(2);
    $currentY = $pdf->GetY();
    $pdf->SetFont('Arial', 'B', 10);

    // Outward No (Left side)
    $pdf->SetX(15);
    $pdf->Cell(90, 10, "Ref No: " . "$school_acronym/$doc_no_padded-SUP/$tenure_name", 0, 0, 'L');


    // Date (Right side - usi line par)
    $pdf->Cell(90, 10, "Date: " . $supply_order_date, 0, 1, 'R');

    // --- 4. "To" and Subject Section ---
    $pdf->Ln(3);
    $pdf->SetFont('Times', '', 12);
    $pdf->SetX(15);
    $pdf->Cell(100, 7, "To,", 0, 1, 'L');

    $pdf->SetFont('Times', 'B', 12);
    $pdf->SetX(15);
    $pdf->Cell(100, 7, "" . strtoupper($data['vendor_name']), 0, 1, 'L');

    $pdf->SetFont('Times', 'BU', 12);
    $pdf->Ln(2);
    $pdf->SetX(15);
    $pdf->Cell(180, 7, "Subject: SUPPLY ORDER OF " . strtoupper($data['head_name']), 0, 1, 'L');
    $pdf->SetFont('Times', '', 11);
    $pdf->Ln(2);
    $content = "With reference to your quotation for the Financial Year $f_year, it is hereby informed that your quoted rates have been found to be the lowest among all participating firms and have therefore been approved by the competent authority.\n\nYou are requested to supply the required items to this office strictly in accordance with the approved items list provided. Upon completion of the supply, please submit your bill in triplicate for the items supplied, duly signed and stamped, for further processing.";
    $pdf->MultiCell(180, 6, $content);
    $pdf->Ln(4);
    $pdf->StyledHeader(['SR.NO', 'PARTICULARS', 'QTY', 'RATE', 'TOTAL AMOUNT'], [20, 80, 20, 30, 30]);
    mysqli_data_seek($items_res, 0);
    $s = 1;
    $gt = 0;

    while ($row = mysqli_fetch_assoc($items_res)) {
        $t = $row['price'] * $row['quantity'];
        $gt += $t;

        $x = 15;
        $y = $pdf->GetY();

        // Line height control (isay kam rakhne se lines ke darmiyan gap kam hoga)
        $lineHeight = 5;
        $minHeight = 8; // Minimum height agar text 1 line ka ho

        // Column Widths
        $w_sno = 20;
        $w_item = 80;
        $w_qty = 20;
        $w_price = 30;
        $w_total = 30;

        // 1. Pehle MultiCell se Item Name likhen (border ke baghair)
        $pdf->SetXY($x + $w_sno, $y);
        $pdf->MultiCell($w_item, $lineHeight, $row['item_name'], 0, 'L');

        // Calculate karein ke MultiCell ne kitni height li
        $currentY = $pdf->GetY();
        $rowHeight = $currentY - $y;

        // Agar text chota hai toh minimum height apply karein
        if ($rowHeight < $minHeight) {
            $rowHeight = $minHeight;
        }

        // 2. Ab wapas upar ja kar saare boxes (borders) aur baki data fill karein
        $pdf->SetXY($x, $y);

        $pdf->Cell($w_sno, $rowHeight, $s++, 1, 0, 'C'); // S.No

        // Item Name ka khali box border ke liye (kyunki text hum pehle likh chuke hain)
        $pdf->Cell($w_item, $rowHeight, '', 1, 0, 'L');

        $pdf->Cell($w_qty, $rowHeight, $row['quantity'], 1, 0, 'C');
        $pdf->Cell($w_price, $rowHeight, number_format($row['price']), 1, 0, 'C');
        $pdf->Cell($w_total, $rowHeight, number_format($t), 1, 1, 'C');

        // Agli row ke liye Y position set karein
        $pdf->SetY($y + $rowHeight);
    }
    $pdf->SetX(15);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(150, 8, 'TOTAL', 1, 0, 'R');
    $pdf->Cell(30, 8, number_format($gt), 1, 1, 'C');

    // --- Signature / DDO Section ---
    $pdf->Ln(2); // MultiCell ke baad thori space di hai

    $pdf->SetFont('Times', 'B', 11);

    // --- Font Set Karein (Width calculate karne se pehle font set hona zaroori hai) ---
    $pdf->SetFont('Times', 'B', 11);

    $schoolName = strtoupper($data['school_name']);
    $ddoText = "DDO (" . $data['school_code'] . ")";

    // 1. Check karein ke DDO text bada hai ya School Name
    $widthDDO = $pdf->GetStringWidth($ddoText);
    $widthSchool = $pdf->GetStringWidth($schoolName);

    /** * Agar school name bohot bada hai (e.g. 90mm se zyada), 
     * toh hum max width 90mm rakhenge taake text wrap ho jaye.
     * Warna jitna bada text hai, उतनी hi box width hogi.
     */
    $maxAllowedWidth = 90;
    $dynamicWidth = min($maxAllowedWidth, max($widthDDO, $widthSchool));

    // 2. Right margin calculate karein (Page width 210mm - dynamic width - 10mm padding)
    $right_margin = 210 - $dynamicWidth - 10;

    // --- DDO Code Print Karein ---
    $pdf->SetX($right_margin);
    $pdf->Cell($dynamicWidth, 6, $ddoText, 0, 1, 'C');

    // --- School Name Print Karein ---
    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetX($right_margin);

    /**
     * MultiCell 'C' ke saath use karne se:
     * Agar text $dynamicWidth se chota hai, toh center rahega.
     * Agar bada hai, toh wrap ho kar bhi center hi rahega.
     */
    $pdf->MultiCell($dynamicWidth, 5, $schoolName, 0, 'C');

    // ════════════════════════════════════════════════════════════
    // PAGE: COMPARATIVE STATEMENT (Landscape)
    // ════════════════════════════════════════════════════════════

    $fixed_school_lh = "../uploads/school_letterhead/letterhead school.pdf";

    if (file_exists($fixed_school_lh)) {
        $pageCount = $pdf->setSourceFile($fixed_school_lh);
        $sTpl = $pdf->importPage(1);
        $pdf->AddPage('P', 'A4');
        $pdf->useTemplate($sTpl, 0, 0, 210, 297);
    } else {
        $pdf->AddPage('P', 'A4');
    }

    // 1. School Name
    $pdf->SetTextColor(0, 0, 0);

    // School Name (Y=35 se shuru kiya hai taake letterhead ke niche aaye)
    $pdf->SetFont('Times', 'B', 13);
    $pdf->SetXY(35, 15);
    $pdf->MultiCell(140, 6, strtoupper($data['school_name']), 0, 'C');

    // Department & Government Info
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetX(15);
    $pdf->Cell(180, 6, "EDUCATION & LITERACY DEPARTMENT", 0, 1, 'C');
    $pdf->SetX(15);
    $pdf->Cell(180, 6, "GOVERNMENT OF SINDH", 0, 1, 'C');

    // --- 3. Outward No & Date (Amnay Samnay) ---
    $pdf->Ln(2);
    $currentY = $pdf->GetY();
    $pdf->SetFont('Arial', 'B', 10);

    // Outward No (Left side)
    $pdf->SetX(15);
    $pdf->Cell(90, 10, "Ref No: " . "$school_acronym/$doc_no_padded-COMP/$tenure_name", 0, 0, 'L');

    // Date (Right side - usi line par)
    $pdf->Cell(90, 7, "Date: " . $comp_statement_date, 0, 1, 'R');
    $pdf->Ln(10);

    // 4. Comparative Statement
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, "Comparative Statement", 0, 1, 'C');
    $pdf->Ln(2);

    // 5. Sub titles
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, $data['code_no'] . ' ' . $data['head_name'], 0, 1, 'C');
    $pdf->Ln(5);

    // =============================================
    // TABLE DATA FETCH
    // =============================================
    $vendors = mysqli_query(
        $conn,
        "SELECT DISTINCT v.id, v.vendor_name
     FROM tbl_quotation_details qd
     JOIN tbl_vendor v ON qd.vendor_id = v.id
     WHERE qd.quotation_id = '$q_id'"
    );
    $v_list = mysqli_fetch_all($vendors, MYSQLI_ASSOC);

    $items_query = mysqli_query(
        $conn,
        "SELECT DISTINCT i.id, i.item_name
     FROM tbl_quotation_details qd
     JOIN tbl_item i ON qd.item_id = i.id
     WHERE qd.quotation_id = '$q_id'"
    );

    // =============================================
    // TABLE DIMENSIONS
    // =============================================
    $sno_w             = 10;
    $item_w            = 60;
    $vendor_w          = 35;
    $vendor_count      = count($v_list);
    $total_table_width = $sno_w + $item_w + ($vendor_count * $vendor_w);
    $page_width        = 190; // A4 - margins (10 left + 10 right)
    $margin_left       = (210 - $total_table_width) / 2;

    // =============================================
    // HEADER HEIGHT — FPDF GetStringWidth se exact calculate
    // =============================================
    $pdf->SetFont('Arial', 'B', 7.4);
    $line_height  = 5;   // har line ki height mm mein
    $header_lines = 1;   // minimum 1 line

    foreach ($v_list as $v) {
        // FPDF ka GetStringWidth use karo exact width ke liye
        $str_width = $pdf->GetStringWidth($v['vendor_name']);
        $lines_needed = ceil($str_width / ($vendor_w - 2)); // 2mm padding
        if ($lines_needed > $header_lines) {
            $header_lines = $lines_needed;
        }
    }

    $header_h = max(10, $header_lines * $line_height + 4);

    // =============================================
    // TABLE HEADER DRAW
    // =============================================
    $startY = $pdf->GetY();

    // S# cell
    $pdf->SetXY($margin_left, $startY);
    $pdf->Cell($sno_w, $header_h, 'S#', 1, 0, 'C');

    // Item Name cell
    $pdf->Cell($item_w, $header_h, 'Item Name', 1, 0, 'C');

    // Vendor header cells — har ek manually position set karo
    $col_x = $margin_left + $sno_w + $item_w;
    foreach ($v_list as $v) {
        $str_width    = $pdf->GetStringWidth($v['vendor_name']);
        $lines_needed = ceil($str_width / ($vendor_w - 2));

        $pdf->SetXY($col_x, $startY);

        if ($lines_needed <= 1) {
            // Single line — simple Cell
            $pdf->Cell($vendor_w, $header_h, $v['vendor_name'], 1, 0, 'C');
        } else {
            // Multi line — MultiCell, phir Y reset
            // MultiCell ke andar text ko vertically center karne ke liye
            // upar se padding add karo
            $total_text_h = $lines_needed * $line_height;
            $top_pad      = ($header_h - $total_text_h) / 2;

            // Border pehle draw karo
            $pdf->Rect($col_x, $startY, $vendor_w, $header_h);

            // Thoda neeche se shuru karo vertically center ke liye
            $pdf->SetXY($col_x, $startY + $top_pad);
            $pdf->MultiCell($vendor_w, $line_height, $v['vendor_name'], 0, 'C');
        }

        $col_x += $vendor_w;
    }

    // Y ko header ke bilkul neeche set karo
    $pdf->SetXY($margin_left, $startY + $header_h);

    // =============================================
    // TABLE BODY
    // =============================================
    $pdf->SetFont('Arial', '', 8);
    $sno = 1;
    while ($it = mysqli_fetch_assoc($items_query)) {
        $rowY = $pdf->GetY();
        $lineHeight = 5; // Text lines ke darmiyan gap
        $minHeight = 8;  // Kam se kam row ki height

        // 1. Pehle Item Name ki height check karein (MultiCell use karke)
        // Hum sirf height check kar rahe hain, text draw nahi kar rahe (border 0)
        $pdf->SetXY($margin_left + $sno_w, $rowY);
        $pdf->MultiCell($item_w, $lineHeight, $it['item_name'], 0, 'L');

        $newY = $pdf->GetY();
        $rowHeight = $newY - $rowY;

        // Agar text chota hai toh minimum height fix karein
        if ($rowHeight < $minHeight) {
            $rowHeight = $minHeight;
        }

        // 2. Ab S.No Draw karein
        $pdf->SetXY($margin_left, $rowY);
        $pdf->Cell($sno_w, $rowHeight, $sno++, 1, 0, 'C');

        // 3. Item Name Draw karein (Border ke liye khali box aur text dono)
        $pdf->Cell($item_w, $rowHeight, '', 1, 0, 'L'); // Border box
        $pdf->SetXY($margin_left + $sno_w, $rowY);
        // Text likhen (border 0) taake box ke andar wrap ho
        $pdf->MultiCell($item_w, $lineHeight, $it['item_name'], 0, 'L');

        // 4. Price columns — har vendor ke liye
        $col_x = $margin_left + $sno_w + $item_w;
        foreach ($v_list as $v) {
            $p_res = mysqli_query(
                $conn,
                "SELECT price FROM tbl_quotation_details 
             WHERE quotation_id = '$q_id' 
             AND vendor_id = '{$v['id']}' 
             AND item_id   = '{$it['id']}'"
            );
            $p     = mysqli_fetch_assoc($p_res);
            $price = (isset($p['price']) && $p['price'] > 0) ? number_format($p['price']) : '-';

            // Har vendor ka column usi $rowHeight ke sath draw karein
            $pdf->SetXY($col_x, $rowY);
            $pdf->Cell($vendor_w, $rowHeight, $price, 1, 0, 'C');
            $col_x += $vendor_w;
        }

        // 5. Agli row ke liye Y position set karein
        $pdf->SetY($rowY + $rowHeight);
    }

    // =============================================
    // FOOTER TEXT — table ke left se bilkul seedha
    // =============================================
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 10, "The lowest rate, as offered by " . $data['vendor_name'] . " has been duly approved", 0, 1, 'L');

    // --- Signature / DDO Section ---
    $pdf->Ln(15); // MultiCell ke baad thori space di hai

    $pdf->SetFont('Times', 'B', 11);

    // --- Font Set Karein (Width calculate karne se pehle font set hona zaroori hai) ---
    $pdf->SetFont('Times', 'B', 11);

    $schoolName = strtoupper($data['school_name']);
    $ddoText = "DDO (" . $data['school_code'] . ")";

    // 1. Check karein ke DDO text bada hai ya School Name
    $widthDDO = $pdf->GetStringWidth($ddoText);
    $widthSchool = $pdf->GetStringWidth($schoolName);

    /** * Agar school name bohot bada hai (e.g. 90mm se zyada), 
     * toh hum max width 90mm rakhenge taake text wrap ho jaye.
     * Warna jitna bada text hai, उतनी hi box width hogi.
     */
    $maxAllowedWidth = 90;
    $dynamicWidth = min($maxAllowedWidth, max($widthDDO, $widthSchool));

    // 2. Right margin calculate karein (Page width 210mm - dynamic width - 10mm padding)
    $right_margin = 210 - $dynamicWidth - 10;

    // --- DDO Code Print Karein ---
    $pdf->SetX($right_margin);
    $pdf->Cell($dynamicWidth, 6, $ddoText, 0, 1, 'C');

    // --- School Name Print Karein ---
    $pdf->SetFont('Times', 'B', 10);
    $pdf->SetX($right_margin);

    /**
     * MultiCell 'C' ke saath use karne se:
     * Agar text $dynamicWidth se chota hai, toh center rahega.
     * Agar bada hai, toh wrap ho kar bhi center hi rahega.
     */
    $pdf->MultiCell($dynamicWidth, 5, $schoolName, 0, 'C');

    //***************************************************************************************

    // ════════════════════════════════════════════════════════════
    // LOOP: HAR VENDOR KE LIYE ALAG QUOTATION PAGE
    // ════════════════════════════════════════════════════════════
    $vendor_index = 0;
    while ($vendor = mysqli_fetch_assoc($vendors_res)) {

        // Is vendor ke items fetch karo
        $items_sql = "SELECT qd.*, i.item_name 
                  FROM tbl_quotation_details qd
                  JOIN tbl_item i ON qd.item_id = i.id
                  WHERE qd.quotation_id = '$q_id' AND qd.vendor_id = '{$vendor['id']}'";
        $items_res_q = mysqli_query($conn, $items_sql);

        // Agar is vendor ka koi item nahi to skip
        if (mysqli_num_rows($items_res_q) == 0) {
            $vendor_index++;
            continue;
        }

        // Get unique quotation date for this vendor
        $current_quotation_date = $quotation_dates[$vendor_index];

        // Naya page add karo
        $pdf->addPage();

        // Vendor ka letterhead apply karo
        $vendor_lh1 = "../uploads/vendor_letterheads/" . $vendor['vendor_letterhead'];
        if (file_exists($vendor_lh1) && !empty($vendor['vendor_letterhead'])) {
            try {
                $pdf->setSourceFile($vendor_lh1);
                $vTpl = $pdf->importPage(1);
                $pdf->useTemplate($vTpl, 0, 0, 210, 297);
            } catch (Exception $e) {
                // Letterhead load na ho to simple page
            }
        }

        // ─── QUOTATION HEADER ───────────────────────────────
        $words = explode(" ", $vendor['vendor_name']);
        $initials = "";
        foreach ($words as $w) {
            $initials .= strtoupper($w[0]); // Har word ka pehla letter lega
        }
        $ref_no = $initials . "/" . $q_id . "/" . $tenure_name;

        // Title: DELIVERY CHALLAN
        $pdf->SetXY(15, 68);
        $pdf->SetFont('Arial', 'B', 10);
        // $pdf->Cell(90, 8, "Ref No: " . $ref_no, 0, 0, 'L');
        // UNIQUE DATE FOR THIS VENDOR
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetX(15);
        $pdf->Cell(180, 8, "Date: " . $current_quotation_date, 0, 1, 'R');
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(180, 10, "QUOTATION", 0, 1, 'C');

        // School Name
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetX(15);
        $pdf->Cell(180, 6, $data['school_name'], 0, 1, 'C');
        $pdf->Ln(5);

        // Table Header
        // --- TABLE CALCULATIONS ---
        $colWidths = [17, 100, 30];
        $totalWidth = array_sum($colWidths); // 147
        $startX = (210 - $totalWidth) / 2; // Page center alignment (approx 31.5)

        // --- MANUAL HEADER (Styling StyledHeader wali hi rahay gi) ---
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetX($startX); // Forcefully centering the header

        $headerText = ['S.No', 'Description', 'Rate'];
        for ($i = 0; $i < count($headerText); $i++) {
            $pdf->Cell($colWidths[$i], 9, $headerText[$i], 1, 0, 'C', true);
        }
        $pdf->Ln(); // Header ke baad line break

        // --- TABLE BODY ---
        $pdf->SetFont('Arial', '', 10);
        $sno = 1;

        while ($item = mysqli_fetch_assoc($items_res_q)) {
            // Current positions save karein
            $x = $startX;
            $y = $pdf->GetY();

            $lineHeight = 5; // Text lines ka gap
            $minHeight = 8;  // Normal row height

            // 1. Item Name ki height calculate karein
            $pdf->SetXY($x + 17, $y); // S.No ki width (17) chor kar
            $pdf->MultiCell(100, $lineHeight, $item['item_name'], 0, 'L');

            $newY = $pdf->GetY();
            $rowHeight = $newY - $y;

            // Minimum height check (agar 1 line ka text ho)
            if ($rowHeight < $minHeight) {
                $rowHeight = $minHeight;
                $newY = $y + $minHeight;
            }

            // 2. Wapas piche ja kar S.No aur Borders draw karein
            $pdf->SetXY($x, $y);
            $pdf->Cell(17, $rowHeight, $sno++, 1, 0, 'C'); // S.No

            // Item Name ka khali box border ke liye
            $pdf->Cell(100, $rowHeight, '', 1, 0, 'L');

            // 3. Price/Rate wala cell
            $pdf->Cell(30, $rowHeight, number_format($item['price']), 1, 1, 'C');

            // 4. Cursor ko next row ke liye set karein
            $pdf->SetY($newY);
        }

        $pdf->Ln(5);

        $logo_path_q = "../uploads/vendor_logos/" . $vendor['vendor_logo'];

        if (!empty($vendor['vendor_logo']) && file_exists($logo_path_q)) {
            // Fixed Size: Ab size hamesha 35mm rahega
            $fixed_w_q = 35;

            // Right end calculation: 
            // Page width (210) - Logo Width (35) - Right Margin (10)
            // Margin 10mm rakhne se logo sahi balance mein right side par ayega
            $x_pos_q = 210 - $fixed_w_q - 6;

            // Fixed Y: Randomness khatam kar di, ab position predictable hogi
            $y_pos_q = $pdf->GetY() + 10;

            $pdf->SafeImage($logo_path_q, $x_pos_q, $y_pos_q, $fixed_w_q);
        }

        $vendor_index++;
    }
}
// ════════════════════════════════════════════════════════════
// OUTPUT
// ════════════════════════════════════════════════════════════
if (ob_get_length()) ob_end_clean();
$pdf->Output('I', 'Bulk_Documents_' . $tenure_val . '.pdf');
exit();
