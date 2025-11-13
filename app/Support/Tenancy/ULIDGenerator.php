<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

class ULIDGenerator implements UniqueIdentifierGenerator
{
    public static function generate($resource): string
    {
        // Use Laravel's Str helper to produce a ULID string
        return (string) Str::ulid();
    }
}
