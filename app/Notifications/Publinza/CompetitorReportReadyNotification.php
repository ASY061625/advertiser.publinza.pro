<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;

/** A competitor benchmark finished refreshing. */
class CompetitorReportReadyNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $projectId,
        private readonly string $projectName,
        private readonly int $competitors,
    ) {
        parent::__construct();
    }

    public function type(): NotificationType
    {
        return NotificationType::CompetitorReportReady;
    }

    public function title(): string
    {
        return "Competitor report ready for {$this->projectName}";
    }

    public function body(): string
    {
        $rivals = $this->competitors === 1 ? '1 rival domain' : "{$this->competitors} rival domains";

        return "Fresh metrics for {$rivals}, benchmarked against your site.";
    }

    public function href(): string
    {
        return "/projects/{$this->projectId}?tab=competitors";
    }

    protected function action(): string
    {
        return 'See the report';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'project_id' => $this->projectId,
            'project' => $this->projectName,
            'competitors' => $this->competitors,
        ];
    }
}
