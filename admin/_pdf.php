<?php

declare(strict_types=1);

final class SimplePdf
{
    private array $pages = [];
    private string $content = '';
    private float $y = 0;
    private int $pageNumber = 0;

    public function newPage(string $subtitle = ''): void
    {
        if ($this->content !== '') $this->pages[] = $this->content;
        $this->content = '';
        $this->pageNumber++;
        $this->rect(0, 792, 595, 50, '0.063 0.286 0.710');
        $this->text(36, 812, 18, 'Bhatnagar Sabha Ghaziabad - Registrations', true, '1 1 1');
        if ($subtitle !== '') $this->text(36, 796, 8, $subtitle, false, '0.88 0.92 1');
        $this->y = 770;
    }

    public function ensureSpace(float $height, string $subtitle = ''): void
    {
        if ($this->content === '' || $this->y - $height < 48) $this->newPage($subtitle);
    }

    public function heading(string $text): void
    {
        $this->ensureSpace(28);
        $this->rect(30, $this->y - 17, 535, 25, '0.93 0.96 1');
        $this->text(38, $this->y - 9, 12, $text, true, '0.063 0.141 0.239');
        $this->y -= 34;
    }

    public function field(string $label, string $value): void
    {
        $value = trim($value) !== '' ? trim($value) : '-';
        $lines = $this->wrap($value, 88);
        $height = 15 + max(0, count($lines) - 1) * 12;
        $this->ensureSpace($height);
        $this->text(38, $this->y, 8, strtoupper($label), true, '0.38 0.43 0.52');
        $lineY = $this->y;
        foreach ($lines as $index => $line) {
            $this->text(155, $lineY - ($index * 12), 9, $line, false, '0.063 0.141 0.239');
        }
        $this->y -= $height;
    }

    public function separator(): void
    {
        $this->content .= "0.86 0.89 0.94 RG 30 {$this->y} m 565 {$this->y} l S\n";
        $this->y -= 18;
    }

    public function output(): string
    {
        if ($this->content !== '') $this->pages[] = $this->content;
        $pageCount = count($this->pages);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($this->pages as $index => $pageContent) {
            $pageNumber = $index + 1;
            $pageContent .= "BT /F1 8 Tf 0.38 0.43 0.52 rg 278 24 Td (Page {$pageNumber} of {$pageCount}) Tj ET\n";
            $contentId = 5 + ($index * 2);
            $pageId = $contentId + 1;
            $objects[$contentId] = '<< /Length ' . strlen($pageContent) . ">>\nstream\n" . $pageContent . "endstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $kids[] = $pageId . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function text(float $x, float $y, int $size, string $text, bool $bold = false, string $color = '0 0 0'): void
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
        $escaped = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $encoded);
        $font = $bold ? 'F2' : 'F1';
        $this->content .= "BT /{$font} {$size} Tf {$color} rg {$x} {$y} Td ({$escaped}) Tj ET\n";
    }

    private function rect(float $x, float $y, float $width, float $height, string $color): void
    {
        $this->content .= "{$color} rg {$x} {$y} {$width} {$height} re f\n";
    }

    private function wrap(string $value, int $length): array
    {
        return explode("\n", wordwrap(str_replace(["\r\n", "\r"], "\n", $value), $length, "\n", true));
    }
}
