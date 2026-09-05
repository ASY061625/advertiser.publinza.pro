<?php

declare(strict_types=1);

namespace App\Domain\Trading\Actions;

use App\Domain\Catalog\Models\Website;
use App\Domain\Projects\Models\LandingPage;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Enums\ServiceType;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\CartItem;
use App\Models\User;
use App\Support\ArticleText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The add-post wizard's answers, as a cart line.
 *
 * This is the whole point of the wizard converging rather than forking. The
 * catalog enters the purchase from the site side and the wizard enters it from
 * the post side, but both end at a `cart_items` row — so there is one thing to
 * price, one thing to check out, one set of warnings and one order path. A
 * second table for "posts being composed" would have needed its own copy of all
 * of that, and the two copies would have disagreed within a month.
 *
 * Everything here is re-checked against the advertiser's own records. The
 * wizard state is client-held for four steps and autosaved to a schemaless
 * draft, so by the time it arrives none of its ids can be taken on trust.
 */
final class AddPostToCart
{
    /** Uploads are a document or an image, and not a large one. */
    public const MAX_UPLOAD_KB = 5120;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, ?UploadedFile $article = null, ?UploadedFile $image = null): CartItem
    {
        $project = $this->ownedProject($user, $data['project_id'] ?? null);
        $website = Website::query()->with('prices')->find($data['website_id'] ?? null);

        if ($website === null || ! $website->is_active) {
            throw new RuntimeException('That website is no longer available.');
        }

        $service = ServiceType::from((string) $data['service_type']);
        $price = $website->priceFor($service);

        if ($price === null) {
            throw new RuntimeException("{$website->domain} does not offer that service.");
        }

        $mode = ContentMode::from((string) $data['content_mode']);
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
        $page = $this->savedPage($project, $data['landing_page_id'] ?? null);

        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'website_id' => $website->id,
            'project_id' => $project?->id,
            'folder_id' => $this->ownedFolderId($project, $data['folder_id'] ?? null),
            'service_type' => $service,
            'content_mode' => $mode,
            // A saved page wins over the typed fields, and is read from the
            // database rather than from the form — so a saved label cannot
            // arrive with somebody else's URL attached to it.
            'anchor_text' => $page['anchor'] ?? $this->text($data['anchor_text'] ?? null, 190),
            'target_url' => $page['url'] ?? $this->text($data['target_url'] ?? null, 2048),
            'express' => (bool) ($data['express'] ?? false),
            // What this line is quoted at. It is not what gets charged — the
            // live price is, here as everywhere else. See CartPricer.
            'unit_price_cents' => $price->price_cents,
            // Only ever one of the two. A line the publisher writes carries a
            // brief; a line the advertiser writes carries an article. Storing
            // both would leave the checkout unable to say which was meant.
            'brief' => $mode->incursWritingFee() ? $this->brief($data, $website) : null,
        ]);

        if (! $mode->incursWritingFee()) {
            $this->attachArticle($item, $user, $data, $article);
        }

        if ($image !== null) {
            $item->update(['image_path' => $image->store("articles/{$user->id}", 'local')]);
        }

        return $item->fresh() ?? $item;
    }

    /**
     * What the publisher is being asked for.
     *
     * The target length is clamped to what the site actually offers rather than
     * trusted from the form: a request for 400 words against an 800-word
     * minimum is a placement that comes back rejected, and the wizard is the
     * last place that can stop it cheaply.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function brief(array $data, Website $website): array
    {
        $tiers = $website->word_count_tiers ?? [];
        $requested = (int) ($data['target_words'] ?? 0);

        return [
            'brief' => $this->text($data['brief'] ?? null, 5000),
            'keywords' => $this->text($data['keywords'] ?? null, 500),
            'tone' => $this->text($data['tone'] ?? null, 190),
            'target_words' => match (true) {
                $tiers !== [] && in_array($requested, $tiers, true) => $requested,
                $requested >= $website->min_words => $requested,
                default => $website->min_words,
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attachArticle(CartItem $item, User $user, array $data, ?UploadedFile $article): void
    {
        if ($article !== null) {
            $path = $article->store("articles/{$user->id}", 'local');
            $text = ArticleText::fromFile($article);

            $item->update([
                'article_title' => $this->text($data['title'] ?? null, 190)
                    ?? pathinfo($article->getClientOriginalName(), PATHINFO_FILENAME),
                'article_body_html' => $text,
                'article_word_count' => ArticleText::countWords($text),
                'article_file_path' => $path,
            ]);

            return;
        }

        $body = (string) ($data['body'] ?? '');

        // An empty body is not an error here. "I will write it later" is a
        // legitimate answer — the checkout already leaves such a line as a
        // draft post rather than refusing the order.
        if (trim($body) === '') {
            return;
        }

        $item->update([
            'article_title' => $this->text($data['title'] ?? null, 190),
            'article_body_html' => $body,
            'article_word_count' => ArticleText::countWords($body),
        ]);
    }

    /** Deletes whatever this line put on disk. Used when a line is abandoned. */
    public static function forget(CartItem $item): void
    {
        foreach ([$item->article_file_path, $item->image_path] as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private function ownedProject(User $user, mixed $projectId): ?Project
    {
        if (! is_numeric($projectId)) {
            return null;
        }

        return Project::query()->where('user_id', $user->id)->find((int) $projectId);
    }

    private function ownedFolderId(?Project $project, mixed $folderId): ?int
    {
        if ($project === null || ! is_numeric($folderId)) {
            return null;
        }

        return ProjectFolder::query()
            ->where('project_id', $project->id)
            ->whereKey((int) $folderId)
            ->value('id');
    }

    /**
     * The advertiser's own saved anchor/URL pair, if they picked one.
     *
     * @return array{anchor: string, url: string}|null
     */
    private function savedPage(?Project $project, mixed $landingPageId): ?array
    {
        if ($project === null || ! is_numeric($landingPageId)) {
            return null;
        }

        $page = LandingPage::query()
            ->where('project_id', $project->id)
            ->find((int) $landingPageId);

        return $page === null ? null : ['anchor' => $page->anchor_text, 'url' => $page->url];
    }

    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $max);
    }
}
