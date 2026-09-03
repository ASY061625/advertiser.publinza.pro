<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DTOs\DateRange;
use App\Domain\Projects\Models\Project;
use App\Support\Export\PdfTableWriter;
use App\Support\Export\XlsxWriter;
use InvalidArgumentException;

/**
 * The statistics table, as a file.
 *
 * One set of rows behind all three formats. A CSV that says one thing and a PDF
 * that says another is the classic export bug, and it happens the moment each
 * format builds its own query.
 */
final class BuildStatisticsExport
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    private const HEADERS = ['Period', 'Posts ordered', 'Posts published', 'Links live', 'Spend', 'Average price'];

    public function __construct(
        private readonly GetProjectStatistics $statistics,
        private readonly XlsxWriter $xlsx,
        private readonly PdfTableWriter $pdf,
    ) {}

    /**
     * Writes the file and returns its path and row count.
     *
     * @return array{path: string, rows: int}
     */
    public function handle(
        Project $project,
        DateRange $range,
        string $granularity,
        ?int $folderId,
        string $format,
        string $directory,
    ): array {
        if (! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException("Unknown export format [{$format}].");
        }

        $data = $this->statistics->handle($project, $range, $granularity, $folderId);
        /** @var list<array<string, mixed>> $series */
        $series = $data['series'];

        $path = rtrim($directory, '/').'/'.$this->filename($project, $range, $format);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        match ($format) {
            'csv' => $this->csv($path, $series),
            'xlsx' => $this->xlsxFile($path, $project, $series),
            default => $this->pdfFile($path, $project, $range, $series),
        };

        return ['path' => $path, 'rows' => count($series)];
    }

    /**
     * @param  list<array<string, mixed>>  $series
     */
    private function csv(string $path, array $series): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new InvalidArgumentException("Could not write to {$path}.");
        }

        // A BOM, so Excel opens a UTF-8 CSV as UTF-8 rather than as Latin-1 and
        // turning every pound sign into a pair of glyphs.
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, self::HEADERS);

        foreach ($series as $row) {
            fputcsv($handle, $this->rawRow($row));
        }

        fclose($handle);
    }

    /**
     * @param  list<array<string, mixed>>  $series
     */
    private function xlsxFile(string $path, Project $project, array $series): void
    {
        $this->xlsx->write(
            $path,
            mb_substr($project->name, 0, 31),
            self::HEADERS,
            array_map(fn (array $row): array => $this->rawRow($row), $series),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $series
     */
    private function pdfFile(string $path, Project $project, DateRange $range, array $series): void
    {
        $this->pdf->write(
            $path,
            $project->name.' — statistics',
            sprintf('%s to %s', $range->from->toFormattedDateString(), $range->to->toFormattedDateString()),
            self::HEADERS,
            array_map(fn (array $row): array => $this->displayRow($row), $series),
            [0.22, 0.16, 0.16, 0.14, 0.16, 0.16],
        );
    }

    /**
     * Money as a decimal number, for the formats something will do arithmetic
     * in. A currency-formatted string in a spreadsheet cannot be summed.
     *
     * @param  array<string, mixed>  $row
     * @return list<string|int|float|null>
     */
    private function rawRow(array $row): array
    {
        return [
            (string) $row['label'],
            (int) $row['ordered'],
            (int) $row['publishedCount'],
            (int) $row['liveLinks'],
            round((int) $row['spendCents'] / 100, 2),
            $row['averageCents'] === null ? null : round((int) $row['averageCents'] / 100, 2),
        ];
    }

    /**
     * Money as people read it, for the format nothing computes from.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function displayRow(array $row): array
    {
        return [
            (string) $row['label'],
            (string) $row['ordered'],
            (string) $row['publishedCount'],
            (string) $row['liveLinks'],
            '$'.number_format((int) $row['spendCents'] / 100, 2),
            $row['averageCents'] === null ? '—' : '$'.number_format((int) $row['averageCents'] / 100, 2),
        ];
    }

    private function filename(Project $project, DateRange $range, string $format): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', $project->name), '-') ?: 'project';

        return sprintf(
            '%s-statistics-%s-%s-%s.%s',
            mb_strtolower($slug),
            $range->from->toDateString(),
            $range->to->toDateString(),
            bin2hex(random_bytes(8)),
            $format,
        );
    }
}
