<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Support\InvoicePdf;
use App\Domain\Trading\Models\Order;
use Illuminate\Support\Facades\Storage;

/**
 * An invoice's PDF, made on demand and cached on disk.
 *
 * On demand rather than at issue time, because the first version of an invoice
 * is rarely the one anybody downloads — billing details get corrected in the
 * hour after an order — and a file written at issue time would be the stale
 * one. Once written it is kept: the bytes must not change under somebody who
 * downloaded it yesterday.
 */
final class RenderInvoice
{
    private const DISK = 'local';

    public function __construct(private readonly InvoicePdf $pdf) {}

    /**
     * @return array{path: string, filename: string}
     */
    public function handle(Invoice $invoice): array
    {
        $filename = $invoice->number.'.pdf';
        $path = "invoices/{$invoice->user_id}/{$filename}";
        $disk = Storage::disk(self::DISK);

        if ($invoice->pdf_path !== null && $disk->exists($invoice->pdf_path)) {
            return ['path' => $invoice->pdf_path, 'filename' => $filename];
        }

        $order = $invoice->order_id === null
            ? null
            : Order::query()->with(['posts.website:id,domain'])->find($invoice->order_id);

        $disk->put($path, $this->pdf->render($invoice, $order));

        $invoice->update(['pdf_path' => $path]);

        return ['path' => $path, 'filename' => $filename];
    }
}
