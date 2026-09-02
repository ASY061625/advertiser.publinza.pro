<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\DTOs\PostFilters;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The current view as a CSV, streamed.
 *
 * Streamed and chunked rather than built in memory: an advertiser with ten
 * thousand posts should get a file, not a 500. The rows are the rows on screen
 * — the same filters, the same order — because an export that quietly returned
 * something else would be worse than no export at all.
 */
final class ExportPostsCsv
{
    private const HEADERS = [
        'Post ID', 'Website', 'DR', 'Monthly traffic', 'Project', 'Folder',
        'Anchor text', 'Target URL', 'Status', 'Price', 'Content mode',
        'Created', 'Published', 'Deadline', 'Published URL',
    ];

    public function __construct(private readonly ListPosts $list) {}

    /**
     * @param  list<int>|null  $onlyIds  Restrict to a selection; null exports everything matching.
     */
    public function handle(User $user, PostFilters $filters, ?array $onlyIds = null): StreamedResponse
    {
        $query = $this->list->filtered($user, $filters)
            ->with(['website:id,domain', 'website.latestMetric', 'project:id,name', 'folder:id,name'])
            ->when($onlyIds !== null, fn (Builder $q) => $q->whereIn('posts.id', $onlyIds ?? []))
            ->reorder('posts.id');

        $filename = sprintf('publinza-posts-%s.csv', now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');

            // A BOM, so Excel opens a UTF-8 domain or anchor as written rather
            // than as mojibake. Everything else reads past it.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADERS);

            $query->chunkById(500, function ($posts) use ($out): void {
                foreach ($posts as $post) {
                    fputcsv($out, $this->row($post));
                }
            }, 'posts.id', 'id');

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return list<string>
     */
    private function row(Post $post): array
    {
        return [
            (string) $post->id,
            $post->website?->domain ?? '',
            (string) ($post->website?->latestMetric?->ahrefs_dr ?? ''),
            (string) ($post->website?->latestMetric?->monthly_traffic ?? ''),
            $post->project?->name ?? '',
            $post->folder?->name ?? '',
            $this->safe($post->anchor_text),
            $this->safe($post->target_url),
            $post->status->label(),
            number_format($post->price_cents / 100, 2, '.', ''),
            $post->content_mode->label(),
            $post->created_at?->toDateString() ?? '',
            $post->published_at?->toDateString() ?? '',
            $post->deadline_at?->toDateString() ?? '',
            $this->safe($post->published_url),
        ];
    }

    /**
     * Neutralises spreadsheet formula injection.
     *
     * A cell starting =, +, - or @ is executed as a formula when the file is
     * opened. Anchor text and target URLs are advertiser-supplied and end up in
     * a colleague's spreadsheet, so a leading formula character gets a quote in
     * front of it — visible as text, inert as a formula.
     */
    private function safe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }
}
