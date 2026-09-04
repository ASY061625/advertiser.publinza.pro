<?php

declare(strict_types=1);

namespace App\Domain\Posts\Models;

use App\Casts\MoneyCast;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Support\PostStatusContext;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectFolder;
use App\Domain\Trading\Enums\ContentMode;
use App\Domain\Trading\Models\Order;
use App\Exceptions\InvalidStatusTransition;
use App\Models\User;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The unit of work: one article or link going live on one website.
 *
 * Status is governed by PostStatus::allowedTransitions(). Every change writes a
 * post_status_history row — see PostObserver, which enforces both.
 *
 * @property PostStatus $status
 * @property int $price_cents
 * @property Carbon|null $published_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $frozen_until
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'project_id',
        'folder_id',
        'website_id',
        'status',
        'anchor_text',
        'target_url',
        'content_mode',
        'article_id',
        'price_cents',
        'frozen_until',
        'published_url',
        'published_at',
        'deadline_at',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'content_mode' => ContentMode::class,
            'price_cents' => 'integer',
            'price' => MoneyCast::class,
            'frozen_until' => 'datetime',
            'published_at' => 'datetime',
            'deadline_at' => 'datetime',
        ];
    }

    public function price(): Money
    {
        return new Money($this->price_cents);
    }

    /**
     * The sanctioned way to move a post along the lifecycle.
     *
     * Wraps the save in a transaction so the status change and its history row
     * commit together or not at all. The observer still validates and records
     * a plain `$post->save()`, but only this path is atomic.
     *
     * @param  array<string, mixed>  $attributes  Extra columns to set in the same write.
     */
    public function transitionTo(PostStatus $to, ?string $note = null, array $attributes = []): self
    {
        return DB::transaction(function () use ($to, $note, $attributes): self {
            PostStatusContext::withNote($note);

            $this->fill($attributes);
            $this->status = $to;
            $this->save();

            return $this;
        });
    }

    public function canTransitionTo(PostStatus $to): bool
    {
        return $this->status->canTransitionTo($to);
    }

    /** Throws unless the move is legal — useful for guarding a UI action. */
    public function assertCanTransitionTo(PostStatus $to): void
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidStatusTransition($this->status, $to, $this->getKey());
        }
    }

    // ---------------------------------------------------------- relationships

    /**
     * @return BelongsTo<User, $this>
     */
    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ProjectFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProjectFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The current revision. */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Every revision ever submitted for this post.
     *
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->orderBy('version');
    }

    /**
     * @return HasMany<PostStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(PostStatusHistory::class)->oldest('created_at');
    }

    /**
     * Threads about this post. Plural because a post can accumulate more than
     * one — a brief question and a later revision request are not one thread.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class)->latest('last_message_at');
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
