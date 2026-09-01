<?php

declare(strict_types=1);

namespace App\Domain\System\Models;

use Illuminate\Database\Eloquent\Model;

/** A typed key/value the admin panel can edit without a deploy. */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    public function typedValue(): string|int|bool|float|null
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'float' => (float) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            default => $this->value,
        };
    }
}
