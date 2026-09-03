<?php

declare(strict_types=1);

namespace App\Support\Export;

/**
 * A paginated table as a PDF, written without a PDF library.
 *
 * The document this produces is a table of periods and numbers, which is the
 * shape a minimal writer handles well: one built-in font, absolute text
 * positions, and horizontal rules. dompdf would bring an HTML and CSS engine
 * to lay out six columns.
 *
 * Deliberately limited, and the limits are the reason it is safe: Helvetica
 * only, WinAnsi only, no images, no wrapping. Text that would overflow its
 * column is truncated with an ellipsis rather than drawn over its neighbour.
 */
final class PdfTableWriter
{
    /** A4 landscape, in PDF points. */
    private const WIDTH = 842.0;

    private const HEIGHT = 595.0;

    private const MARGIN = 40.0;

    private const ROW_HEIGHT = 18.0;

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<float>  $widths  Column widths as fractions of the table width.
     */
    public function write(string $path, string $title, string $subtitle, array $headers, array $rows, array $widths): void
    {
        $pages = $this->paginate($title, $subtitle, $headers, $rows, $widths);

        file_put_contents($path, $this->document($pages));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<float>  $widths
     * @return list<string> One content stream per page.
     */
    private function paginate(string $title, string $subtitle, array $headers, array $rows, array $widths): array
    {
        $tableWidth = self::WIDTH - self::MARGIN * 2;
        $columns = [];
        $x = self::MARGIN;

        foreach ($widths as $fraction) {
            $columns[] = ['x' => $x, 'w' => $tableWidth * $fraction];
            $x += $tableWidth * $fraction;
        }

        $pages = [];
        $index = 0;
        $total = max(1, (int) ceil(count($rows) / $this->rowsPerPage()));

        while ($index < count($rows) || $pages === []) {
            $stream = '';
            $y = self::HEIGHT - self::MARGIN;

            $stream .= $this->text($title, self::MARGIN, $y, 16, bold: true);
            $y -= 20;
            $stream .= $this->text($subtitle, self::MARGIN, $y, 9, grey: true);
            $y -= 24;

            $stream .= $this->headerRow($headers, $columns, $y);
            $y -= self::ROW_HEIGHT;

            for ($printed = 0; $printed < $this->rowsPerPage() && $index < count($rows); $printed++, $index++) {
                $stream .= $this->bodyRow($rows[$index], $columns, $y);
                $y -= self::ROW_HEIGHT;
            }

            $stream .= $this->text(
                sprintf('Page %d of %d', count($pages) + 1, $total),
                self::WIDTH - self::MARGIN - 60,
                self::MARGIN - 12,
                8,
                grey: true,
            );

            $pages[] = $stream;
        }

        return $pages;
    }

    private function rowsPerPage(): int
    {
        return (int) floor((self::HEIGHT - self::MARGIN * 2 - 64) / self::ROW_HEIGHT);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array{x: float, w: float}>  $columns
     */
    private function headerRow(array $headers, array $columns, float $y): string
    {
        $stream = '';

        foreach ($headers as $i => $header) {
            $column = $columns[$i] ?? null;
            if ($column === null) {
                continue;
            }

            $stream .= $this->text($this->fit($header, $column['w'], 9), $column['x'], $y, 9, bold: true);
        }

        // The rule under the header, and the only line on the page: rows are
        // separated by space, which is quieter and reads faster than a grid.
        $stream .= sprintf(
            "0.8 w 0.796 0.835 0.882 RG %.2f %.2f m %.2f %.2f l S\n",
            self::MARGIN,
            $y - 5,
            self::WIDTH - self::MARGIN,
            $y - 5,
        );

        return $stream;
    }

    /**
     * @param  list<string>  $row
     * @param  list<array{x: float, w: float}>  $columns
     */
    private function bodyRow(array $row, array $columns, float $y): string
    {
        $stream = '';

        foreach (array_values($row) as $i => $value) {
            $column = $columns[$i] ?? null;
            if ($column === null) {
                continue;
            }

            $stream .= $this->text($this->fit($value, $column['w'], 9), $column['x'], $y, 9);
        }

        return $stream;
    }

    /**
     * Helvetica at 9pt averages about 0.5em per character. Approximate, and
     * deliberately so: the alternative is shipping the font's width tables to
     * decide where to put an ellipsis.
     */
    private function fit(string $text, float $width, float $size): string
    {
        $max = (int) floor(($width - 6) / ($size * 0.5));

        return mb_strlen($text) <= $max ? $text : mb_substr($text, 0, max(1, $max - 1)).'…';
    }

    private function text(string $value, float $x, float $y, float $size, bool $bold = false, bool $grey = false): string
    {
        return sprintf(
            "BT /%s %.1f Tf %s %.2f %.2f Td (%s) Tj ET\n",
            $bold ? 'F2' : 'F1',
            $size,
            $grey ? '0.392 0.455 0.545 rg' : '0.043 0.106 0.200 rg',
            $x,
            $y,
            $this->escape($value),
        );
    }

    /**
     * PDF text strings are WinAnsi here, so anything outside it is
     * transliterated rather than emitted as bytes the viewer would misread.
     */
    private function escape(string $value): string
    {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);

        if ($encoded === false) {
            $encoded = (string) preg_replace('/[^\x20-\x7E]/', '?', $value);
        }

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $encoded);
    }

    /**
     * @param  list<string>  $pages
     */
    private function document(array $pages): string
    {
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
                self::WIDTH,
                self::HEIGHT,
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

        $pdf .= "trailer\n<< /Size ".$count." /Root 1 0 R >>\nstartxref\n".$xrefAt."\n%%EOF";

        return $pdf;
    }
}
