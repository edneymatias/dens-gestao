<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SuperAdminAccessTest extends TestCase
{
    /** @test */
    public function super_admin_is_granted_all_abilities_via_gate_before(): void
    {
        $user = User::factory()->create(['is_superadmin' => true]);

        $this->actingAs($user);

        $this->assertTrue(Gate::allows('some-random-ability'));
    }

    /** @test */
    public function normal_user_is_not_granted_unset_abilities(): void
    {
        $user = User::factory()->create(['is_superadmin' => false]);

        $this->actingAs($user);

        $this->assertFalse(Gate::allows('some-random-ability'));
    }
}
