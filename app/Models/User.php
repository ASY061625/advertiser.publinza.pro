<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentMethod;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Catalog\Models\Blacklist;
use App\Domain\Catalog\Models\Favorite;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\Wishlist;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\TrustedDevice;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Models\SavedView;
use App\Domain\Projects\Models\Project;
use App\Domain\Trading\Models\Cart;
use App\Domain\Trading\Models\Order;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * An advertiser. Staff accounts live in App\Domain\Admin\Models\Admin on a
 * separate guard and a separate table.
 *
 * These stay docblock annotations rather than declared properties: a real
 * property on an Eloquent model shadows __get, so the attribute would never be
 * read from the model's attribute bag at all.
 *
 * @property UserStatus $status
 * @property array<string, mixed>|null $grid_preferences
 * @property bool $sidebar_collapsed
 * @property string|null $display_name
 * @property string|null $avatar_path
 * @property string $date_format
 * @property string $number_format
 * @property string|null $pending_email
 * @property string|null $pending_email_token
 * @property Carbon|null $pending_email_sent_at
 * @property Carbon|null $notifications_paused_until
 * @property Carbon|null $deletion_requested_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'name',
        'company',
        'country',
        'vat_no',
        'phone',
        'timezone',
        'locale',
        'status',
        'referrer_source',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'sidebar_collapsed' => 'boolean',
            'pending_email_sent_at' => 'datetime',
            'notifications_paused_until' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'changelog_read_at' => 'datetime',
            'grid_preferences' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * @return HasMany<SavedView, $this>
     */
    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class)->orderBy('name');
    }

    /**
     * What to call them on screen.
     *
     * Falls back to the first word of the legal name rather than the whole of
     * it: "Dana" reads as a greeting, "Dana Okafor Consulting Ltd" does not.
     */
    public function displayName(): string
    {
        $chosen = trim((string) $this->display_name);

        if ($chosen !== '') {
            return $chosen;
        }

        return explode(' ', trim($this->name))[0] ?: $this->name;
    }

    /** Null until one is uploaded; the Avatar component draws initials. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null ? null : '/profile/avatar/'.md5($this->avatar_path);
    }

    /** Are non-essential notifications muted right now? */
    public function notificationsArePaused(): bool
    {
        return $this->notifications_paused_until !== null
            && $this->notifications_paused_until->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** Two-factor is only on once a code from the authenticator has been proven. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** Null until the first successful sign-in, which is how onboarding routes. */
    public function hasSignedInBefore(): bool
    {
        return $this->last_login_at !== null;
    }

    // ------------------------------------------------------------ notifications

    /** Our copy, and a link on the advertiser subdomain rather than APP_URL. */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    // ---------------------------------------------------------- relationships

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * @return HasOne<Cart, $this>
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<PaymentMethod, $this>
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * @return HasMany<Wishlist, $this>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Sites this advertiser has starred.
     *
     * @return BelongsToMany<Website, $this>
     */
    public function favoriteWebsites(): BelongsToMany
    {
        return $this->belongsToMany(Website::class, 'favorites')->withTimestamps();
    }

    /**
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * @return HasMany<Blacklist, $this>
     */
    public function blacklists(): HasMany
    {
        return $this->hasMany(Blacklist::class);
    }

    /**
     * @return HasMany<TrustedDevice, $this>
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
