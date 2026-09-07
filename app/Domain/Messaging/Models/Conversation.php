<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\Enums\ConversationStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Search\Contracts\SearchableIndex;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

/**
 * A thread. It may hang off a website, a specific post, or neither — general
 * support has both nullable.
 *
 * Every thread is advertiser ↔ Publinza; there is no third party, because
 * Publinza owns every site in the catalog. What varies is the subject.
 *
 * @property int $user_id
 * @property int|null $website_id
 * @property int|null $post_id
 * @property string $subject
 * @property ConversationStatus $status
 * @property Carbon|null $last_message_at
 * @property Carbon|null $muted_at
 */
class Conversation extends Model implements SearchableIndex
{
    use Searchable;

    protected $fillable = ['user_id', 'website_id', 'post_id', 'subject', 'last_message_at', 'status', 'muted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'muted_at' => 'datetime',
        ];
    }

    /**
     * What the global palette matches a thread on.
     *
     * Subject and the site it is about — not the messages. A thread's body can
     * run to fifty replies, and an index carrying all of them would match every
     * conversation that ever mentioned a word. The subject is what people
     * remember a thread by.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing('website:id,domain');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'subject' => $this->subject,
            'domain' => $this->website?->domain,
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function makeAllSearchableUsing($query)
    {
        return $query->with('website:id,domain');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    /**
     * The last reply, for a one-line excerpt.
     *
     * A HasOne with ofMany rather than `messages()->last()`, so a list of
     * threads is one extra query rather than one per thread.
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function isMuted(): bool
    {
        return $this->muted_at !== null;
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }
}
