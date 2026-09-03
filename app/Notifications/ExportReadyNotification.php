<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\System\Models\ExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your export is ready", with the link that fetches it.
 *
 * The link is to the app, not to storage: the file is private and the download
 * route checks who is asking and how old the export is. A signed URL straight
 * to the object would outlive both checks.
 *
 * Queued, so sending it is its own unit of work. Sent inline it would run
 * inside the export job, and a mail server having a bad minute would fail a
 * job whose file was already written and stored.
 */
class ExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ExportJob $export,
        private readonly string $projectName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'export.ready',
            'export_id' => $this->export->id,
            'project' => $this->projectName,
            'format' => pathinfo((string) $this->export->file_path, PATHINFO_EXTENSION),
            'rows' => $this->export->row_count,
            'url' => $this->url(),
            'expires_at' => $this->export->completed_at?->addDay()?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your {$this->projectName} statistics export is ready")
            ->line("The statistics you exported from {$this->projectName} are ready to download.")
            ->action('Download', $this->url())
            ->line('The link works for 24 hours. After that, export it again — it takes a few seconds.');
    }

    private function url(): string
    {
        return url("/exports/{$this->export->id}/download");
    }
}
