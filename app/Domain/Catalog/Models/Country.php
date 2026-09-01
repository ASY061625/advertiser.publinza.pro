<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An ISO 3166-1 alpha-2 country. */
class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    protected $fillable = ['code', 'name', 'region'];

    /**
     * @return HasMany<Website, $this>
     */
    public function websites(): HasMany
    {
        return $this->hasMany(Website::class, 'country_id');
    }
}
