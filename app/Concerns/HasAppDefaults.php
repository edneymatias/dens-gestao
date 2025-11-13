<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

trait HasAppDefaults
{
    use HasUlids, SoftDeletes;

    /**
     * Optional default casts for models that want them.
     */
    protected array $appDefaultCasts = [
        'id' => 'string',
    ];

    /**
     * Ensure Eloquent treats the primary key as a string.
     * Overrides the Model::getKeyType() behavior without redeclaring $keyType.
     */
    public function getKeyType(): string
    {
        return 'string';
    }

    /**
     * Ensure Eloquent does not treat the primary key as incrementing.
     * Overrides the Model::isIncrementing() behavior without redeclaring $incrementing.
     */
    public function isIncrementing(): bool
    {
        return false;
    }

    /**
     * Default route key name (ULID).
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
