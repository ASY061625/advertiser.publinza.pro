<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\System\Models\ExportJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** How long a finished export stays downloadable. */
    public const LINK_TTL_HOURS = 24;

    /**
     * Hands over a finished export, if it is yours and still fresh.
     *
     * Three checks, in this order: it belongs to the caller, it finished, and
     * it has not expired. The ownership check is first because the other two
     * would otherwise tell somebody whether an export id exists.
     */
    public function download(Request $request, ExportJob $export): StreamedResponse
    {
        abort_if($export->user_id !== $request->user()->id, 404);
        abort_unless($export->status === 'ready' && $export->file_path !== null, 404);

        abort_if(
            $export->completed_at === null || $export->completed_at->addHours(self::LINK_TTL_HOURS)->isPast(),
            410,
            'This download link has expired. Export it again — it only takes a few seconds.',
        );

        abort_unless(Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->download($export->file_path, basename($export->file_path));
    }
}
