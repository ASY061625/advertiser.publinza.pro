<?php

declare(strict_types=1);

namespace App\Support\Export;

use RuntimeException;
use ZipArchive;

/**
 * A single-sheet .xlsx, written without a spreadsheet library.
 *
 * An .xlsx is a ZIP of a handful of XML parts, and PHP ships ZipArchive. For a
 * table of periods and numbers that is a hundred lines and no dependency —
 * against PhpSpreadsheet, which is twenty megabytes and a memory profile, to
 * produce the same six columns.
 *
 * Numbers are written as numbers, not strings: an export whose money column
 * arrives in Excel as text is a spreadsheet nobody can sum, which is the one
 * thing people open it to do.
 */
final class XlsxWriter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function write(string $path, string $sheetName, array $headers, array $rows): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create the workbook at {$path}.");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($headers, $rows));

        $zip->close();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function sheet(array $headers, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>';

        $xml .= $this->row(1, $headers, header: true);

        foreach ($rows as $index => $row) {
            $xml .= $this->row($index + 2, $row);
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  list<string|int|float|null>  $values
     */
    private function row(int $number, array $values, bool $header = false): string
    {
        $xml = '<row r="'.$number.'">';

        foreach (array_values($values) as $column => $value) {
            $ref = $this->columnName($column).$number;

            if ($value === null || $value === '') {
                continue;
            }

            if (! $header && is_numeric($value) && ! is_string($value)) {
                $xml .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';

                continue;
            }

            // Inline strings rather than a shared-strings part: one fewer file
            // to keep in step, and these tables do not repeat enough for the
            // shared table to pay for itself.
            $xml .= '<c r="'.$ref.'" t="inlineStr"'.($header ? ' s="1"' : '').'>'
                .'<is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
        }

        return $xml.'</row>';
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private function columnName(int $index): string
    {
        $name = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $name = chr(65 + $i % 26).$name;
        }

        return $name;
    }

    private function escape(string $value): string
    {
        // Control characters are not legal in XML 1.0 at all, so they are
        // dropped rather than encoded — an escaped one still breaks the file.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);

        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape(mb_substr($sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /** Two cell formats: plain, and the bold one the header row wears. */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }
}
