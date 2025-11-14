<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantSelectionTest extends TestCase
{
    public function test_user_can_list_their_tenants()
    {
        $user = User::factory()->create();

        $tenant = Tenant::create(['name' => 'ACME']);

        DB::table('tenant_user')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'is_owner' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('tenants.index'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['tenants' => [['id', 'name']]]);
        $this->assertCount(1, $response->json('tenants'));
    }

    public function test_user_can_select_tenant_and_tenancy_initializes_on_next_request()
    {
        $user = User::factory()->create();

        $tenant = Tenant::create(['name' => 'Acme 2']);

        DB::table('tenant_user')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_owner' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Select tenant
        $selectResponse = $this->actingAs($user)->postJson(route('tenants.select'), [
            'tenant_id' => $tenant->id,
        ]);

        $selectResponse->assertStatus(200)->assertJson(['status' => 'ok']);

        // Session should contain tenant_id
        $this->assertEquals(session('tenant_id'), $tenant->id);

        // Next request should have tenancy initialized by middleware
        $checkResponse = $this->actingAs($user)->getJson(route('tenancy.check'));

        $checkResponse->assertStatus(200);
        $this->assertTrue($checkResponse->json('initialized'));
        $this->assertEquals($tenant->id, $checkResponse->json('tenant_id'));
    }
}
