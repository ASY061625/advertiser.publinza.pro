<?php

declare(strict_types=1);

namespace App\Domain\Billing\Support;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Trading\Models\Order;
use App\Support\Export\PdfCanvas;

/**
 * An invoice as a PDF.
 *
 * A different shape from PdfTableWriter's paginated grid: a masthead, two
 * parties side by side, the placements as lines, a totals block and a payment
 * stamp. They share PdfCanvas, which knows the file format, and nothing else.
 *
 * Everything on the page comes from the invoice's own snapshot rather than from
 * the live profile. An invoice has to keep saying what it said when it was
 * issued — a company that moves does not get to rewrite last quarter's
 * receipts, and a tax number corrected today does not retroactively change what
 * was charged.
 */
final class InvoicePdf
{
    private const MARGIN = 48.0;

    /** Nothing is drawn below this — the footer lives there. */
    private const FLOOR = 96.0;

    /** Subtotal, tax, total and the payment stamp. */
    private const TOTALS_HEIGHT = 92.0;

    /** --brand-blue, --ink-900, --ink-500, --ink-300, --surface-sunken. */
    private const BRAND = [0.114, 0.306, 0.847];

    private const INK = [0.043, 0.106, 0.200];

    private const MUTED = [0.392, 0.455, 0.545];

    private const RULE = [0.804, 0.839, 0.878];

    private const SUNKEN = [0.965, 0.973, 0.980];

    private const TEAL = [0.078, 0.722, 0.651];

    public function render(Invoice $invoice, ?Order $order): string
    {
        $canvas = PdfCanvas::portrait();
        $right = $canvas->width - self::MARGIN;

        $y = $this->masthead($canvas, $invoice, $right);
        $y = $this->parties($canvas, $invoice, $y, $right);
        $y = $this->lines($canvas, $invoice, $order, $y, $right);

        // The totals block needs room below the last line. An order of twenty
        // placements fills the page, and a total printed over the footer is an
        // invoice somebody has to ring up about.
        if ($y < self::FLOOR + self::TOTALS_HEIGHT) {
            $this->footer($canvas);
            $canvas->newPage();
            $y = $this->continued($canvas, $invoice, $right);
        }

        $this->totals($canvas, $invoice, $y, $right);
        $this->footer($canvas);

        return $canvas->render();
    }

    /** The masthead a second and subsequent page gets. */
    private function continued(PdfCanvas $canvas, Invoice $invoice, float $right): float
    {
        $top = $canvas->height - self::MARGIN;

        $canvas->rect(self::MARGIN, $top - 4, $right - self::MARGIN, 4, self::BRAND);
        $canvas->text('PUBLINZA', self::MARGIN, $top - 28, 14, true, self::INK);
        $canvas->textRight($invoice->number.' (continued)', $right, $top - 28, 10, false, self::MUTED);

        return $top - 56;
    }

    private function masthead(PdfCanvas $canvas, Invoice $invoice, float $right): float
    {
        $top = $canvas->height - self::MARGIN;

        // A brand bar rather than a logo file: this writer draws no images, and
        // a solid rule in the brand blue reads as deliberate at any zoom.
        $canvas->rect(self::MARGIN, $top - 4, $right - self::MARGIN, 4, self::BRAND);

        $canvas->text('PUBLINZA', self::MARGIN, $top - 32, 20, true, self::INK);
        $canvas->text('Guest post placements', self::MARGIN, $top - 46, 9, false, self::MUTED);

        $canvas->textRight('INVOICE', $right, $top - 30, 16, true, self::MUTED);
        $canvas->textRight($invoice->number, $right, $top - 46, 11, true, self::INK);

        $rows = [
            ['Issued', $invoice->issued_at?->format('j M Y') ?? '—'],
            ['Status', ucfirst($invoice->status)],
        ];

        if ($invoice->period_start !== null && $invoice->period_end !== null) {
            $rows[] = ['Period', $invoice->period_start->format('j M').' – '.$invoice->period_end->format('j M Y')];
        }

        $y = $top - 64;

        foreach ($rows as [$label, $value]) {
            $canvas->textRight($label, $right - 90, $y, 9, false, self::MUTED);
            $canvas->textRight($value, $right, $y, 9, false, self::INK);
            $y -= 13;
        }

        return min($y, $top - 96);
    }

    private function parties(PdfCanvas $canvas, Invoice $invoice, float $y, float $right): float
    {
        $billing = $invoice->billing_details ?? [];
        $column = ($right - self::MARGIN) / 2;

        $canvas->text('FROM', self::MARGIN, $y, 8, true, self::MUTED);
        $canvas->text('BILLED TO', self::MARGIN + $column, $y, 8, true, self::MUTED);

        $from = $this->textLines([
            (string) config('publinza.company.name', 'Publinza'),
            (string) config('publinza.company.address', ''),
            (string) config('publinza.company.vat', ''),
            (string) config('publinza.company.email', ''),
        ]);

        $to = $this->textLines([
            $this->string($billing, 'company') ?: $this->string($billing, 'name'),
            $this->string($billing, 'name'),
            // An address is typed with newlines in it and has to keep them: a
            // PDF text operator draws one line, so "4 Kingsway\nLondon WC2B"
            // would otherwise come out as one run-on line on the envelope.
            $this->string($billing, 'address'),
            $this->string($billing, 'country'),
            ($vat = $this->string($billing, 'vat_no')) === null ? null : "VAT {$vat}",
            $this->string($billing, 'email'),
        ]);

        // Two columns of the same block, so the taller one sets the next y.
        $line = $y - 14;
        $depth = max(count($from), count($to));

        foreach ($from as $i => $text) {
            $canvas->text(PdfCanvas::fit($text, $column - 12, 9), self::MARGIN, $line - $i * 12, 9, $i === 0, self::INK);
        }

        foreach ($to as $i => $text) {
            $canvas->text(
                PdfCanvas::fit($text, $column - 12, 9),
                self::MARGIN + $column,
                $line - $i * 12,
                9,
                $i === 0,
                self::INK,
            );
        }

        return $line - $depth * 12 - 18;
    }

    /**
     * The placements, one per line.
     *
     * The domain is the line item: an invoice that says "guest post × 6" is
     * unusable for anyone reconciling it against what they actually bought.
     */
    private function lines(PdfCanvas $canvas, Invoice $invoice, ?Order $order, float $y, float $right): float
    {
        $currency = $invoice->currency;

        $canvas->rect(self::MARGIN, $y - 6, $right - self::MARGIN, 20, self::SUNKEN);
        $canvas->text('DESCRIPTION', self::MARGIN + 8, $y, 8, true, self::MUTED);
        $canvas->textRight('AMOUNT', $right - 8, $y, 8, true, self::MUTED);

        $y -= 26;

        foreach ($this->items($invoice, $order, $currency) as [$label, $sublabel, $amount]) {
            $height = $sublabel === null ? 22.0 : 33.0;

            // Break before drawing, not after: a row half over the page edge is
            // worse than a page that ends one row early.
            if ($y - $height < self::FLOOR) {
                $this->footer($canvas);
                $canvas->newPage();
                $y = $this->continued($canvas, $invoice, $right);

                $canvas->rect(self::MARGIN, $y - 6, $right - self::MARGIN, 20, self::SUNKEN);
                $canvas->text('DESCRIPTION', self::MARGIN + 8, $y, 8, true, self::MUTED);
                $canvas->textRight('AMOUNT', $right - 8, $y, 8, true, self::MUTED);
                $y -= 26;
            }

            $canvas->text(PdfCanvas::fit($label, $right - self::MARGIN - 130, 9.5), self::MARGIN + 8, $y, 9.5, false, self::INK);
            $canvas->textRight($amount->format(), $right - 8, $y, 9.5, false, self::INK);

            if ($sublabel !== null) {
                $y -= 11;
                $canvas->text(PdfCanvas::fit($sublabel, $right - self::MARGIN - 130, 8), self::MARGIN + 8, $y, 8, false, self::MUTED);
            }

            $y -= 8;
            $canvas->line(self::MARGIN, $y, $right, $y, self::RULE, 0.5);
            $y -= 14;
        }

        return $y;
    }

    /**
     * @return list<array{0: string, 1: string|null, 2: Money}>
     */
    private function items(Invoice $invoice, ?Order $order, string $currency): array
    {
        if ($order !== null && $order->posts->isNotEmpty()) {
            return $order->posts->map(fn ($post): array => [
                $post->website?->domain ?? 'Site removed',
                $post->anchor_text === null ? null : 'Anchor: '.$post->anchor_text,
                new Money($post->price_cents, $currency),
            ])->values()->all();
        }

        // An invoice with no order behind it — a manual or statement invoice.
        // One line naming what it is beats an empty table.
        return [[
            'Guest post placements',
            $invoice->number,
            new Money($invoice->subtotal_cents, $currency),
        ]];
    }

    private function totals(PdfCanvas $canvas, Invoice $invoice, float $y, float $right): void
    {
        $currency = $invoice->currency;
        $left = $right - 220;

        $rows = [['Subtotal', new Money($invoice->subtotal_cents, $currency), false]];

        /*
         * Tax is stated even when it is zero, and says why.
         *
         * Most of these invoices are reverse-charge to a VAT-registered
         * business in another member state, and "VAT 0.00" with no explanation
         * is the line an accountant queries. Naming the mechanism answers it on
         * the page.
         */
        $taxLabel = $invoice->tax_cents === 0
            ? 'VAT (reverse charge)'
            : 'VAT';

        $rows[] = [$taxLabel, new Money($invoice->tax_cents, $currency), false];
        $rows[] = ['Total', new Money($invoice->total_cents, $currency), true];

        foreach ($rows as [$label, $amount, $strong]) {
            if ($strong) {
                $y -= 4;
                $canvas->line($left, $y + 12, $right, $y + 12, self::RULE, 0.8);
            }

            $canvas->text($label, $left, $y, $strong ? 11 : 9.5, $strong, $strong ? self::INK : self::MUTED);
            $canvas->textRight($amount->format(), $right, $y, $strong ? 11 : 9.5, $strong, self::INK);
            $y -= $strong ? 20 : 15;
        }

        if ($invoice->tax_cents === 0) {
            $canvas->text(
                'VAT reverse charge — Article 196, Council Directive 2006/112/EC.',
                self::MARGIN,
                $y + 8,
                8,
                false,
                self::MUTED,
            );
        }

        // The stamp. A person scanning a stack of these is looking for one
        // thing, and it is this.
        $paid = $invoice->status === 'paid';
        $label = $paid ? 'PAID '.($invoice->paid_at?->format('j M Y') ?? '') : mb_strtoupper($invoice->status);

        $canvas->rect($left, $y - 22, $right - $left, 24, $paid ? [0.925, 0.992, 0.976] : self::SUNKEN);
        $canvas->text(trim($label), $left + 10, $y - 14, 10, true, $paid ? self::TEAL : self::MUTED);
    }

    private function footer(PdfCanvas $canvas): void
    {
        $canvas->line(self::MARGIN, 72, $canvas->width - self::MARGIN, 72, self::RULE, 0.5);

        $canvas->text(
            'Balance held with Publinza is spent only when a placement goes live and its link is verified.',
            self::MARGIN,
            58,
            8,
            false,
            self::MUTED,
        );

        $canvas->text(
            (string) config('publinza.company.email', ''),
            self::MARGIN,
            46,
            8,
            false,
            self::MUTED,
        );
    }

    /**
     * Flattens a block of fields into printable lines, splitting anything that
     * was typed with newlines in it and dropping what is empty.
     *
     * @param  list<string|null>  $fields
     * @return list<string>
     */
    private function textLines(array $fields): array
    {
        $lines = [];

        foreach ($fields as $field) {
            foreach (preg_split('/\R/', (string) $field) ?: [] as $line) {
                $line = trim($line);

                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $billing
     */
    private function string(array $billing, string $key): ?string
    {
        $value = $billing[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
