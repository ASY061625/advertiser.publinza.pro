<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\ProjectFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * The bulk actions behind the selection bar.
 *
 * Every one of them re-resolves the selected ids against the signed-in
 * advertiser before touching anything. The client sends a list of ids; a list
 * of ids from a browser is a request, not an authorisation.
 */
final class BulkPostActions
{
    public function __construct(private readonly CancelPost $cancel) {}

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Post>
     */
    public function resolve(User $user, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Post::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * Cancels every selected post that is still cancellable.
     *
     * Posts past the cancellable window are skipped rather than failing the
     * batch: someone who selected forty rows and got one refusal would have no
     * idea which one, and would have to start again.
     *
     * @param  list<int>  $ids
     * @return array{cancelled: int, skipped: int}
     */
    public function cancelMany(User $user, array $ids, string $reason): array
    {
        $posts = $this->resolve($user, $ids);
        $cancelled = 0;

        foreach ($posts as $post) {
            if (! $post->status->isPrePosted()) {
                continue;
            }

            // Each post is its own transaction. One post that cannot be
            // cancelled must not roll back the thirty-nine that were.
            DB::transaction(fn () => $this->cancel->handle($post, $reason));
            $cancelled++;
        }

        return ['cancelled' => $cancelled, 'skipped' => $posts->count() - $cancelled];
    }

    /**
     * Moves posts into a folder, or out of one when $folder is null.
     *
     * A folder belongs to a project, so a post can only move into a folder of
     * the project it is already in — otherwise the move would silently
     * reassign the post to another project's brief.
     *
     * @param  list<int>  $ids
     * @return array{moved: int, skipped: int}
     */
    public function moveToFolder(User $user, array $ids, ?int $folderId): array
    {
        $posts = $this->resolve($user, $ids);

        $folder = $folderId === null ? null : ProjectFolder::query()
            ->whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->find($folderId);

        if ($folderId !== null && $folder === null) {
            throw new RuntimeException('That folder does not exist on your account.');
        }

        $movable = $posts->filter(
            fn (Post $post): bool => $folder === null || $post->project_id === $folder->project_id,
        );

        if ($movable->isNotEmpty()) {
            Post::query()
                ->whereIn('id', $movable->pluck('id'))
                ->update(['folder_id' => $folder?->id]);
        }

        return ['moved' => $movable->count(), 'skipped' => $posts->count() - $movable->count()];
    }

    /**
     * The selected posts' latest articles, as one zip.
     *
     * Built to a temp file and streamed: a zip cannot be produced incrementally
     * the way a CSV can, and holding a hundred articles in memory to send them
     * is how this falls over on the account that needs it most.
     *
     * @param  list<int>  $ids
     */
    public function downloadArticles(User $user, array $ids): StreamedResponse
    {
        $posts = $this->resolve($user, $ids)->load('articles');
        $path = tempnam(sys_get_temp_dir(), 'publinza-articles-');

        if ($path === false) {
            throw new RuntimeException('Could not open a temporary file for the archive.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the archive.');
        }

        $written = 0;

        foreach ($posts as $post) {
            $article = $post->articles->last();

            if ($article === null) {
                continue;
            }

            $zip->addFromString($this->articleName($post, $article->title), $this->articleBody($article));
            $written++;
        }

        if ($written === 0) {
            // An empty zip looks like a broken download. Say so instead.
            $zip->addFromString(
                'README.txt',
                "None of the selected posts has an article yet.\n"
                ."Articles appear once the writer submits a draft.\n",
            );
        }

        $zip->close();

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, sprintf('publinza-articles-%s.zip', now()->format('Y-m-d')), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** A filename that is safe on every filesystem and still identifies the post. */
    private function articleName(Post $post, string $title): string
    {
        $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $title) ?? '';
        $slug = trim(mb_substr($slug, 0, 60), '-');

        return sprintf('%d-%s.html', $post->id, $slug === '' ? 'article' : strtolower($slug));
    }

    private function articleBody(mixed $article): string
    {
        if (is_string($article->body_html) && $article->body_html !== '') {
            return $article->body_html;
        }

        // Some articles are uploaded rather than written in the app.
        if (is_string($article->file_path) && $article->file_path !== '') {
            $disk = Storage::disk(config('filesystems.default'));

            if ($disk->exists($article->file_path)) {
                return (string) $disk->get($article->file_path);
            }
        }

        return '';
    }
}
