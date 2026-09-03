<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Analytics\Actions\BuildStatisticsExport;
use App\Domain\Analytics\DTOs\DateRange;
use App\Domain\Projects\Models\Project;
use App\Domain\System\Models\ExportJob;
use App\Notifications\ExportReadyNotification;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Builds a statistics export off the request thread.
 *
 * A year of daily rows as a PDF is not something to make somebody watch a
 * spinner for, and it is certainly not something to do inside a web worker's
 * timeout. The row tracks the job so the tab can say where it got to, and the
 * advertiser is notified when the file is there.
 */
class BuildStatisticsExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly ExportJob $export) {}

    public function handle(BuildStatisticsExport $build): void
    {
        $this->export->update(['status' => 'processing']);

        try {
            $filters = $this->export->filters ?? [];
            $project = Project::query()->findOrFail((int) ($filters['project_id'] ?? 0));

            // Built somewhere that is emphatically not where it is stored.
            // These were the same directory once: the file was written, copied
            // onto itself, and then deleted by the cleanup — every export came
            // out empty and nothing said so.
            $scratch = storage_path('app/scratch/exports');

            $result = $build->handle(
                $project,
                $this->range($filters),
                (string) ($filters['granularity'] ?? 'day'),
                isset($filters['folder_id']) ? (int) $filters['folder_id'] : null,
                (string) ($filters['format'] ?? 'csv'),
                $scratch,
            );

            $stored = 'exports/'.basename($result['path']);
            $handle = fopen($result['path'], 'rb');

            // Streamed rather than read into memory: a year of daily rows is
            // small, but nothing here guarantees that stays true.
            Storage::disk('local')->put($stored, $handle);

            if (is_resource($handle)) {
                fclose($handle);
            }

            @unlink($result['path']);

            $this->export->update([
                'status' => 'ready',
                'file_path' => $stored,
                'row_count' => $result['rows'],
                'completed_at' => now(),
            ]);

            // Its own try/catch: the file is written and the row says ready.
            // A notification that could not be sent is worth logging and worth
            // retrying, but it is not a reason to tell somebody their export
            // failed when it is sitting on disk.
            try {
                $this->export->user?->notify(new ExportReadyNotification($this->export, $project->name));
            } catch (Throwable $e) {
                Log::warning('Export built but could not be announced.', [
                    'export_id' => $this->export->id,
                    'reason' => $e->getMessage(),
                ]);
            }
        } catch (Throwable $e) {
            // The row carries the failure so the tab can say so, rather than
            // leaving an export queued forever with nothing to show for it.
            $this->export->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function range(array $filters): DateRange
    {
        return new DateRange(
            (string) ($filters['range'] ?? 'custom'),
            CarbonImmutable::parse((string) $filters['from'])->startOfDay(),
            CarbonImmutable::parse((string) $filters['to'])->endOfDay(),
            (string) ($filters['range_label'] ?? 'Custom range'),
        );
    }
}
