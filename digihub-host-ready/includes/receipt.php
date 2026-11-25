<?php
// DigiHub - Simple PDF receipt generator (no external libraries)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function generateReceipt($purchaseId, $product, $buyerEmail, $buyerName, $amount, $payment = []) {
    $receiptDir = PATH_RECEIPTS;
    if (!is_dir($receiptDir)) {
        mkdir($receiptDir, 0775, true);
    }

    $fileName = 'receipt_' . $purchaseId . '.pdf';
    $filePath = $receiptDir . $fileName;
    $relativePath = 'uploads/receipts/' . $fileName;

    $pdf = new SimplePDF();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 13, SITE_NAME . ' - Payment Receipt', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, 'Receipt #: DH-' . str_pad((string)$purchaseId, 6, '0', STR_PAD_LEFT), 0, 1);
    $pdf->Cell(0, 7, 'Date: ' . date('M j, Y H:i'), 0, 1);
    $pdf->Cell(0, 7, 'Currency: ' . CURRENCY, 0, 1);
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Product Details', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    foreach ([
        'Title' => $product['title'],
        'Category' => $product['category'],
        'Type' => $product['product_type'],
    ] as $label => $value) {
        $pdf->Cell(90, 7, $label . ':', 0, 0);
        $pdf->Cell(0, 7, (string)$value, 0, 1);
    }
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Buyer Details', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    foreach (['Name' => $buyerName, 'Email' => $buyerEmail] as $label => $value) {
        $pdf->Cell(90, 7, $label . ':', 0, 0);
        $pdf->Cell(0, 7, (string)$value, 0, 1);
    }
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Payment', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $details = [
        'Amount' => formatMoney($amount),
        'Method' => 'MTN Mobile Money (Simulated)',
        'Transaction' => $payment['transaction_ref'] ?? ('DH-' . str_pad((string)$purchaseId, 6, '0', STR_PAD_LEFT)),
        'Provider ref' => $payment['provider_reference'] ?? 'Not supplied',
        'Status' => 'Verified - Completed',
    ];
    foreach ($details as $label => $value) {
        $pdf->Cell(90, 7, $label . ':', 0, 0);
        $pdf->Cell(0, 7, (string)$value, 0, 1);
    }
    $pdf->Ln(12);

    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 7, 'Thank you for using ' . SITE_NAME . '!', 0, 1, 'C');

    $pdf->Output($filePath, 'F');
    return $relativePath;
}

/** Minimal but valid PDF writer with dynamically calculated xref offsets. */
class SimplePDF {
    private array $pages = [];
    private string $current = '';
    private float $x = 55;
    private float $y = 60;
    private float $fontSize = 12;
    private float $pageWidth = 595;
    private float $pageHeight = 792;
    private float $marginLeft = 55;

    public function AddPage(): void {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }
        $this->current = '';
        $this->x = $this->marginLeft;
        $this->y = 60;
    }

    public function SetFont($family, $style = '', $size = 12): void {
        $this->fontSize = (float)$size;
    }

    public function Cell($w, $h, $text, $border = 0, $ln = 0, $align = ''): void {
        if ($this->y > $this->pageHeight - 55) {
            $this->AddPage();
        }

        $text = (string)$text;
        $text = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8')
            : utf8_decode($text);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        $x = $this->x;
        if ($align === 'C') {
            $x = max(20, ($this->pageWidth - (strlen($text) * $this->fontSize * 0.5)) / 2);
        } elseif ($align === 'R') {
            $x = max(20, $this->pageWidth - $this->marginLeft - (strlen($text) * $this->fontSize * 0.5));
        }

        $pdfY = $this->pageHeight - $this->y;
        $this->current .= sprintf("BT /F1 %.2f Tf %.2f %.2f Td (%s) Tj ET\n", $this->fontSize, $x, $pdfY, $escaped);

        if ($ln === 1) {
            $this->y += (float)$h;
            $this->x = $this->marginLeft;
        } elseif ($ln === 2) {
            $this->x += (float)$w;
            $this->y += (float)$h;
        } else {
            // Advance past this cell so label and value columns never overlap.
            $this->x += (float)$w;
        }
    }

    public function Ln($h = null): void {
        $this->y += $h === null ? $this->fontSize : (float)$h;
        $this->x = $this->marginLeft;
    }

    public function Output($name, $dest = 'F'): void {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }
        if (empty($this->pages)) {
            $this->pages[] = '';
        }

        $kids = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $objectNumber = 4;
        foreach ($this->pages as $content) {
            $kids[] = $objectNumber . ' 0 R';
            $objects[$objectNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 792] '
                . '/Resources << /Font << /F1 3 0 R >> >> /Contents ' . ($objectNumber + 1) . ' 0 R >>';
            $objects[$objectNumber + 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
            $objectNumber += 2;
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer' . "\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

        if ($dest === 'F') {
            file_put_contents($name, $pdf);
        } else {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($name) . '"');
            echo $pdf;
        }
    }
}
