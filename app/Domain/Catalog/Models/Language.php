<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An ISO 639-1 language a site publishes in. */
class Language extends Model
{
    use HasFactory;

    protected $table = 'languages';

    protected $fillable = ['code', 'name', 'native_name'];

    /**
     * @return HasMany<Website, $this>
     */
    public function websites(): HasMany
    {
        return $this->hasMany(Website::class, 'primary_language_id');
    }
}
