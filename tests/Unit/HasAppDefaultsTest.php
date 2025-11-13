<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class HasAppDefaultsTest extends TestCase
{
    public function test_key_type_and_incrementing(): void
    {
        $user = new User;

        $this->assertSame('string', $user->getKeyType());
        $this->assertFalse($user->isIncrementing());
        $this->assertSame('id', $user->getRouteKeyName());
    }

    public function test_traits_present(): void
    {
        $traits = class_uses_recursive(User::class);

        $this->assertTrue(in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, $traits, true));
        $this->assertTrue(in_array(\Illuminate\Database\Eloquent\Concerns\HasUlids::class, $traits, true));
    }
}
