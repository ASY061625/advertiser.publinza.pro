<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\System\Models\ExportJob;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A file somebody asked for has finished.
 *
 * The link is to the app, not to storage: the download route checks who is
 * asking and how old the export is, and a signed URL straight to the object
 * would outlive both checks.
 */
class ExportReadyNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $exportId,
        private readonly string $subject,
        private readonly string $format,
        private readonly int $rows,
    ) {
        parent::__construct();
    }

    public static function for(ExportJob $export, string $subject): self
    {
        return new self(
            $export->id,
            $subject,
            strtoupper(pathinfo((string) $export->file_path, PATHINFO_EXTENSION) ?: 'csv'),
            $export->row_count ?? 0,
        );
    }

    public function type(): NotificationType
    {
        return NotificationType::ExportReady;
    }

    public function title(): string
    {
        return "Your {$this->subject} export is ready";
    }

    public function body(): string
    {
        $rows = $this->rows === 1 ? '1 row' : number_format($this->rows).' rows';

        return "{$rows} as {$this->format}. The link works for 24 hours.";
    }

    public function href(): string
    {
        return "/exports/{$this->exportId}/download";
    }

    protected function action(): string
    {
        return 'Download';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line("The {$this->subject} you exported are ready to download.")
            ->action('Download', $this->url($this->href()))
            ->line('The link works for 24 hours. After that, export it again — it takes a few seconds.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return ['export_id' => $this->exportId, 'format' => $this->format, 'rows' => $this->rows];
    }
}
