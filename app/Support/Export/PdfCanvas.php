<?php

declare(strict_types=1);

namespace App\Support\Export;

/**
 * The low-level half of a PDF: pages, objects, the xref table, and the four
 * drawing operators anything here needs.
 *
 * Extracted from PdfTableWriter when a second document — the invoice — needed
 * the same object machinery and a different layout. The split is the useful
 * one: this file knows the file format and nothing about what is being drawn,
 * and its callers know their layout and nothing about xref offsets.
 *
 * Deliberately limited, and the limits are what make it safe: the two built-in
 * Helvetica faces, WinAnsi text only, no images, no wrapping.
 */
final class PdfCanvas
{
    /** @var list<string> */
    private array $pages = [];

    private string $current = '';

    public function __construct(
        public readonly float $width = 595.0,
        public readonly float $height = 842.0,
    ) {}

    /** A4 portrait — invoices, letters. */
    public static function portrait(): self
    {
        return new self(595.0, 842.0);
    }

    /** A4 landscape — wide tables. */
    public static function landscape(): self
    {
        return new self(842.0, 595.0);
    }

    /**
     * Draws text with its baseline at (x, y), measured from the bottom left.
     *
     * @param  array{float, float, float}|null  $rgb  Ink colour, 0–1 per channel.
     */
    public function text(string $value, float $x, float $y, float $size, bool $bold = false, ?array $rgb = null): self
    {
        [$r, $g, $b] = $rgb ?? [0.043, 0.106, 0.200];

        $this->current .= sprintf(
            "BT /%s %.1f Tf %.3f %.3f %.3f rg %.2f %.2f Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1',
            $size,
            $r,
            $g,
            $b,
            $x,
            $y,
            self::escape($value),
        );

        return $this;
    }

    /**
     * The same text, ending at x rather than starting there.
     *
     * @param  array{float, float, float}|null  $rgb
     */
    public function textRight(string $value, float $right, float $y, float $size, bool $bold = false, ?array $rgb = null): self
    {
        return $this->text($value, $right - self::widthOf($value, $size, $bold), $y, $size, $bold, $rgb);
    }

    /**
     * @param  array{float, float, float}  $rgb
     */
    public function rect(float $x, float $y, float $width, float $height, array $rgb): self
    {
        $this->current .= sprintf(
            "%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $y,
            $width,
            $height,
        );

        return $this;
    }

    /**
     * @param  array{float, float, float}  $rgb
     */
    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $thickness = 0.6): self
    {
        $this->current .= sprintf(
            "%.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $thickness,
            $x1,
            $y1,
            $x2,
            $y2,
        );

        return $this;
    }

    /** Ends the page being drawn and starts a new one. */
    public function newPage(): self
    {
        $this->pages[] = $this->current;
        $this->current = '';

        return $this;
    }

    /**
     * Adds a page whose content stream was built elsewhere.
     *
     * PdfTableWriter composes whole pages as strings before it knows how many
     * there are, so it hands them over rather than drawing through this object.
     */
    public function addPage(string $stream): self
    {
        $this->pages[] = $stream;

        return $this;
    }

    /**
     * Truncates text to what fits in `width` at `size`, with an ellipsis.
     *
     * Helvetica is proportional, so this is an approximation — but it is a
     * conservative one, and the failure mode is a slightly short line rather
     * than one drawn across its neighbour.
     */
    public static function fit(string $text, float $width, float $size, bool $bold = false): string
    {
        if (self::widthOf($text, $size, $bold) <= $width) {
            return $text;
        }

        $max = max(1, (int) floor($width / ($size * ($bold ? 0.58 : 0.52))) - 1);

        return mb_substr($text, 0, $max).'…';
    }

    /** Roughly how wide a string renders. Average advance widths, not metrics. */
    public static function widthOf(string $text, float $size, bool $bold = false): float
    {
        return mb_strlen($text) * $size * ($bold ? 0.58 : 0.52);
    }

    /**
     * PDF text strings are WinAnsi here, so anything outside it is
     * transliterated rather than emitted as bytes the viewer would misread.
     */
    public static function escape(string $value): string
    {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);

        if ($encoded === false) {
            $encoded = (string) preg_replace('/[^\x20-\x7E]/', '?', $value);
        }

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $encoded);
    }

    /** Serialises everything drawn so far into a complete PDF file. */
    public function render(): string
    {
        $pages = $this->pages;

        if ($this->current !== '') {
            $pages[] = $this->current;
        }

        if ($pages === []) {
            $pages[] = '';
        }

        $objects = [];
        $pageCount = count($pages);

        // 1 catalog, 2 pages tree, 3 + 4 fonts, then a page and a content
        // stream per page.
        $pageIds = [];

        for ($i = 0; $i < $pageCount; $i++) {
            $pageIds[] = 5 + $i * 2;
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Count '.$pageCount.' /Kids ['
            .implode(' ', array_map(static fn (int $id): string => $id.' 0 R', $pageIds)).'] >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($pages as $i => $stream) {
            $pageId = 5 + $i * 2;
            $contentId = $pageId + 1;

            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0f %.0f] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->width,
                $this->height,
                $contentId,
            );

            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefAt = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 ".$count."\n0000000000 65535 f \n";

        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        return $pdf."trailer\n<< /Size ".$count." /Root 1 0 R >>\nstartxref\n".$xrefAt."\n%%EOF";
    }
}
