<?php
/**
 * Generates waste records report in PDF or Excel format
 */
require_once __DIR__ . '/auth.php';
requireLogin();

require_once __DIR__ . '/config.php';

// Get parameters
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$format = $_GET['format'] ?? 'pdf';

// Build query
$whereConditions = [];
$params = [];
$types = '';

if ($fromDate && $toDate) {
    $whereConditions[] = 'record_date BETWEEN ? AND ?';
    $params[] = $fromDate;
    $params[] = $toDate;
    $types .= 'ss';
} elseif ($fromDate) {
    $whereConditions[] = 'record_date >= ?';
    $params[] = $fromDate;
    $types .= 's';
} elseif ($toDate) {
    $whereConditions[] = 'record_date <= ?';
    $params[] = $toDate;
    $types .= 's';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Fetch records
$query = "
    SELECT 
        wr.*,
        pj.job_number
    FROM waste_records wr
    LEFT JOIN production_jobs pj ON pj.id = wr.production_job_id
    $whereClause
    ORDER BY wr.record_date DESC
";

$records = [];
if ($connect && !$connect->connect_error) {
    $stmt = $connect->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
}

// If no records found, redirect back with error
if (empty($records)) {
    $_SESSION['error_message'] = 'No waste records found for the selected date range.';
    header('Location: ../waste-records.php');
    exit();
}

// Handle real Excel (.xlsx) format — Office Open XML, compatible with
// Microsoft Excel 2007 through the latest versions (365 / 2021 / 2024).
if ($format === 'excel') {
    $columns = ['Job Number', 'Waste Type', 'Quantity', 'Reason', 'Employee', 'Record Date'];
    $colLetter = function ($i) { return chr(65 + $i); };

    $totalQty = 0;
    $dataXml = '';
    foreach ($records as $i => $record) {
        $totalQty += (int) $record['quantity'];
        $rowNum = $i + 2;
        $values = [
            $record['job_number'] ?? '—',
            $record['waste_type'],
            (int) $record['quantity'],
            $record['reason'] ?? '',
            $record['employee'] ?? '',
            date('Y-m-d', strtotime($record['record_date']))
        ];
        $dataXml .= '<row r="' . $rowNum . '">';
        foreach ($values as $c => $value) {
            $ref = $colLetter($c) . $rowNum;
            if ($c === 2) {
                $dataXml .= '<c r="' . $ref . '" s="2"><v>' . (int) $value . '</v></c>';
            } else {
                $dataXml .= '<c r="' . $ref . '" t="inlineStr" s="2"><is><t xml:space="preserve">' . htmlspecialchars((string) $value, ENT_XML1, 'UTF-8') . '</t></is></c>';
            }
        }
        $dataXml .= '</row>';
    }

    // Header row (green fill, white bold text)
    $headerXml = '<row r="1">';
    foreach ($columns as $c => $label) {
        $headerXml .= '<c r="' . $colLetter($c) . '1" t="inlineStr" s="1"><is><t xml:space="preserve">' . $label . '</t></is></c>';
    }
    $headerXml .= '</row>';

    // Blank spacer row then TOTAL row
    $totalRow = count($records) + 3;
    $totalXml = '<row r="' . $totalRow . '">'
        . '<c r="A' . $totalRow . '" t="inlineStr" s="3"><is><t>TOTAL</t></is></c>'
        . '<c r="B' . $totalRow . '" s="3"></c>'
        . '<c r="C' . $totalRow . '" s="3"><v>' . $totalQty . '</v></c>'
        . '<c r="D' . $totalRow . '" s="3"></c>'
        . '<c r="E' . $totalRow . '" s="3"></c>'
        . '<c r="F' . $totalRow . '" s="3"></c>'
        . '</row>';

    $sheetData = $headerXml
        . $dataXml
        . '<row r="' . ($totalRow - 1) . '"></row>'
        . $totalXml;

    $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<cols>'
        . '<col min="1" max="1" width="16" customWidth="1"/>'
        . '<col min="2" max="2" width="20" customWidth="1"/>'
        . '<col min="3" max="3" width="12" customWidth="1"/>'
        . '<col min="4" max="4" width="26" customWidth="1"/>'
        . '<col min="5" max="5" width="20" customWidth="1"/>'
        . '<col min="6" max="6" width="14" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . $sheetData . '</sheetData>'
        . '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF4CAF50"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FFDDDDDD"/></left><right style="thin"><color rgb="FFDDDDDD"/></right><top style="thin"><color rgb="FFDDDDDD"/></top><bottom style="thin"><color rgb="FFDDDDDD"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Waste Records" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $zip = new ZipArchive();
    $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    if ($zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
        $_SESSION['error_message'] = 'Failed to create Excel report.';
        header('Location: ../waste-records.php');
        exit();
    }
    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $relsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="waste_records_report_' . date('Y-m-d') . '.xlsx"');
    header('Content-Length: ' . filesize($tempFile));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    readfile($tempFile);
    unlink($tempFile);
    exit();
}

// Handle PDF format — generates a downloadable, monochrome (black & white)
// PDF for office use, built with the FPDF library.
if ($format === 'pdf') {
    require_once __DIR__ . '/../fpdf/fpdf.php';

    // ---- Report metadata --------------------------------------------------
    $generatedBy  = trim($_SESSION['user_name'] ?? '');
    $totalQty     = 0;
    foreach ($records as $record) {
        $totalQty += (int) $record['quantity'];
    }

    if ($fromDate && $toDate) {
        $periodLabel = date('M j, Y', strtotime($fromDate)) . ' - ' . date('M j, Y', strtotime($toDate));
    } elseif ($fromDate) {
        $periodLabel = 'From ' . date('M j, Y', strtotime($fromDate));
    } elseif ($toDate) {
        $periodLabel = 'Up to ' . date('M j, Y', strtotime($toDate));
    } else {
        $periodLabel = 'All Records';
    }

    // ---- PDF document class -------------------------------------------------
    class WrReportPdf extends FPDF
    {
        protected $docTitle  = '';
        protected $period    = '';
        protected $madeBy    = '';

        public function setMeta(string $title, string $period, string $by): void
        {
            $this->docTitle  = $title;
            $this->period    = $period;
            $this->madeBy    = $by;
        }

        protected function _arc(float $cx, float $cy, float $r, float $fromDeg, float $toDeg): void
        {
            $span = $toDeg - $fromDeg;
            $n    = max(8, (int) ceil(abs($span) / 1.5));
            $step = $span / $n;
            $p1x  = $cx + $r * cos(deg2rad($fromDeg));
            $p1y  = $cy + $r * sin(deg2rad($fromDeg));
            for ($i = 1; $i <= $n; $i++) {
                $ang = $fromDeg + $step * $i;
                $p2x = $cx + $r * cos(deg2rad($ang));
                $p2y = $cy + $r * sin(deg2rad($ang));
                $this->Line($p1x, $p1y, $p2x, $p2y);
                $p1x = $p2x;
                $p1y = $p2y;
            }
        }

        protected function _circle(float $cx, float $cy, float $r): void
        {
            $this->_arc($cx, $cy, $r, 0, 360);
        }

        public function logoSymbol(float $x, float $y, float $size): void
        {
            $s  = $size / 100.0;
            $px = fn (float $u) => $x + $u * $s;
            $py = fn (float $v) => $y + $v * $s;

            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.7);

            // Outer ring (circle cx=50 cy=50 r=45, stroke-width 3)
            $this->SetLineWidth(3 * $s);
            $this->_circle($px(50), $py(50), 45 * $s);

            // Paper / sheet outline (M38 26h18l6 6v14H38z, stroke-width 2.5)
            $this->SetLineWidth(2.5 * $s);
            $this->Line($px(38), $py(26), $px(56), $py(26));
            $this->Line($px(56), $py(26), $px(62), $py(32));
            $this->Line($px(62), $py(32), $px(62), $py(46));
            $this->Line($px(62), $py(46), $px(38), $py(46));
            $this->Line($px(38), $py(46), $px(38), $py(26));

            // Folded corner flap (M56 26v6h6)
            $this->Line($px(56), $py(26), $px(56), $py(32));
            $this->Line($px(56), $py(32), $px(62), $py(32));

            // Text lines (M43 34h14 M43 39h14 M43 44h8, stroke-width 2)
            $this->SetLineWidth(2 * $s);
            $this->Line($px(43), $py(34), $px(57), $py(34));
            $this->Line($px(43), $py(39), $px(57), $py(39));
            $this->Line($px(43), $py(44), $px(51), $py(44));

            // Warehouse body (rect x=27 y=46 w=46 h=28 rx=5, stroke-width 3)
            $this->SetLineWidth(3 * $s);
            $x1 = $px(27); $y1 = $py(46);
            $x2 = $px(73); $y2 = $py(74);
            $r  = 5 * $s;
            $this->_arc($x1 + $r, $y1 + $r, $r, 180, 270);
            $this->_arc($x2 - $r, $y1 + $r, $r, 270, 360);
            $this->_arc($x2 - $r, $y2 - $r, $r, 0, 90);
            $this->_arc($x1 + $r, $y2 - $r, $r, 90, 180);
            $this->Line($x1 + $r, $y1, $x2 - $r, $y1);
            $this->Line($x2, $y1 + $r, $x2, $y2 - $r);
            $this->Line($x1 + $r, $y2, $x2 - $r, $y2);
            $this->Line($x1, $y1 + $r, $x1, $y2 - $r);

            // Shelf line (M32 52h36, stroke-width 2.5)
            $this->SetLineWidth(2.5 * $s);
            $this->Line($px(32), $py(52), $px(68), $py(52));

            // Feet (M33 74v4h34v-4)
            $this->Line($px(33), $py(74), $px(33), $py(78));
            $this->Line($px(33), $py(78), $px(67), $py(78));
            $this->Line($px(67), $py(78), $px(67), $py(74));

            // Two small filled dots (cx=37 cy=68 r=1.5, cx=43 cy=68 r=1.5)
            $this->SetFillColor(0, 0, 0);
            $d = 1.5 * $s;
            $this->Rect($px(37) - $d, $py(68) - $d, $d * 2, $d * 2, 'F');
            $this->Rect($px(43) - $d, $py(68) - $d, $d * 2, $d * 2, 'F');
            $this->SetLineWidth(0.7);
        }

        public function header(): void
        {
            $this->SetFont('helvetica', 'B', 13);
            $this->SetTextColor(0, 0, 0);
            $this->logoSymbol(15, 9, 17);
            $this->SetXY(35, 10.5);
            $this->Cell(62, 6, 'Print Inventory Control System', 0, 0, 'L');
            $this->SetFont('helvetica', '', 8.5);
            $this->SetXY(35, 17);
            $this->Cell(62, 4, 'Production & Materials Management', 0, 0, 'L');

            $this->SetFont('helvetica', 'B', 12);
            $this->SetXY(97, 10.5);
            $this->Cell(98, 6, strtoupper($this->docTitle), 0, 0, 'R');
            $this->SetFont('helvetica', '', 8.5);
            $this->SetXY(97, 17);
            $this->Cell(98, 4, $this->period, 0, 0, 'R');

            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.7);
            $this->Line(15, 29, 195, 29);
            $this->SetY(33);
        }

        public function footer(): void
        {
            $this->SetY(-16);
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.3);
            $this->Line(15, $this->GetY(), 195, $this->GetY());

            $this->SetY(-14);
            $this->SetFont('helvetica', '', 7.5);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(90, 4, 'Print Inventory Control System', 0, 0, 'L');
            $this->Cell(90, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
        }
    }

    // ---- Build the report ---------------------------------------------------
    $pdf = new WrReportPdf();
    $pdf->setMeta('Waste Records Report', $periodLabel, $generatedBy);
    $pdf->AliasNbPages();
    $pdf->SetTitle('Waste Records Report', true);
    $pdf->SetAuthor($generatedBy !== '' ? $generatedBy : 'PICS', true);
    $pdf->SetMargins(15, 33, 15);
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AddPage();

    // Summary line (Generated By | Total Records | Total Waste Quantity)
    $summaryY = $pdf->GetY();
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetXY(15, $summaryY);
    $pdf->Cell(60, 4, 'GENERATED BY', 0, 0, 'C');
    $pdf->Cell(60, 4, 'TOTAL RECORDS', 0, 0, 'C');
    $pdf->Cell(60, 4, 'TOTAL WASTE QUANTITY', 0, 0, 'C');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(15, $summaryY + 5);
    $pdf->Cell(60, 6, $generatedBy !== '' ? $generatedBy : 'System', 0, 0, 'C');
    $pdf->Cell(60, 6, (string) count($records), 0, 0, 'C');
    $pdf->Cell(60, 6, number_format($totalQty), 0, 0, 'C');
    $pdf->SetY($summaryY + 13);

    // ---- Table --------------------------------------------------------------
    $colW   = [8, 24, 28, 18, 52, 24, 26];
    $heads  = ['#', 'Job Number', 'Waste Type', 'Quantity', 'Reason / Notes', 'Employee', 'Date'];
    $aligns = ['C', 'L', 'L', 'R', 'L', 'L', 'C'];

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetX(15);
    foreach ($heads as $i => $h) {
        $pdf->Cell($colW[$i], 8.5, $h, 1, 0, $aligns[$i], false);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8.5);

    $idx  = 1;
    $fill = false;
    foreach ($records as $record) {
        $reason = $record['reason'] ?? '—';
        $jobNum = $record['job_number'] ?? '—';
        $waste  = $record['waste_type'];
        $qty    = number_format((int) $record['quantity']);
        $emp    = $record['employee'] ?? '—';
        $date   = date('M j, Y', strtotime($record['record_date']));

        $lines = max(1, (int) ceil($pdf->GetStringWidth($reason) / (($colW[4] - 4) / 1.12)));
        $rowH  = max(7, ($lines * 4.3) + 1.4);

        if ($pdf->GetY() + $rowH > 270) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetX(15);
            foreach ($heads as $i => $h) {
                $pdf->Cell($colW[$i], 8.5, $h, 1, 0, $aligns[$i], false);
            }
            $pdf->Ln();
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 8.5);
        }

        $rowTop = $pdf->GetY();
        $x = 15;
        $cells = [
            ['w' => $colW[0], 't' => (string) $idx, 'a' => 'C'],
            ['w' => $colW[1], 't' => $jobNum, 'a' => 'L'],
            ['w' => $colW[2], 't' => $waste, 'a' => 'L'],
            ['w' => $colW[3], 't' => $qty, 'a' => 'R'],
            ['w' => $colW[4], 't' => $reason, 'a' => 'L', 'multi' => true],
            ['w' => $colW[5], 't' => $emp, 'a' => 'L'],
            ['w' => $colW[6], 't' => $date, 'a' => 'C'],
        ];

        foreach ($cells as $cell) {
            $shade = $fill ? 238 : 255;
            $pdf->SetFillColor($shade, $shade, $shade);
            $pdf->Rect($x, $rowTop, $cell['w'], $rowH, $fill ? 'DF' : 'D');
            if (!empty($cell['multi'])) {
                $pdf->SetFont('helvetica', '', 8.5);
                $pdf->SetXY($x + 1.5, $rowTop + 1.3);
                $pdf->MultiCell($cell['w'] - 3, 4.3, $cell['t'], 0, 'L');
            } else {
                $pdf->SetFont('helvetica', '', 8.5);
                $pdf->SetXY($x + 1.2, $rowTop + (($rowH - 4.5) / 2));
                $pdf->Cell($cell['w'] - 2.4, 4.5, $cell['t'], 0, 0, $cell['a']);
            }
            $x += $cell['w'];
        }
        $pdf->SetY($rowTop + $rowH);
        $fill = !$fill;
        $idx++;
    }

    // Total row
    $rowTop = $pdf->GetY();
    $rowH   = 8;
    $x      = 15;
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetFillColor(210, 210, 210);
    $parts = [
        ['w' => $colW[0] + $colW[1], 't' => 'TOTAL QUANTITY', 'a' => 'L'],
        ['w' => $colW[2], 't' => '', 'a' => 'L'],
        ['w' => $colW[3], 't' => number_format($totalQty), 'a' => 'R'],
        ['w' => $colW[4] + $colW[5] + $colW[6], 't' => '', 'a' => 'L'],
    ];
    foreach ($parts as $p) {
        $pdf->Rect($x, $rowTop, $p['w'], $rowH, 'DF');
        $pdf->SetXY($x + 1.2, $rowTop + (($rowH - 4.5) / 2));
        $pdf->Cell($p['w'] - 2.4, 4.5, $p['t'], 0, 0, $p['a']);
        $x += $p['w'];
    }
    $pdf->SetY($rowTop + $rowH);

    // Download the PDF
    $pdf->Output('D', 'waste_records_report_' . date('Y-m-d') . '.pdf');
    exit();
}
