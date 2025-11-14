<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LandlordFilamentTenantFlowTest extends TestCase
{
    public function test_select_tenant_and_create_customer_in_tenant_db(): void
    {
        $user = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($user);

        // Create a tenant record in central DB
        $tenant = Tenant::create(['id' => (string) \Illuminate\Support\Str::ulid(), 'name' => 'ACME', 'db_name' => 'tenant_acme']);

        // Use the selection endpoint to set session tenant_id
        $this->postJson(route('tenants.select'), ['tenant_id' => $tenant->id])
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // After selection, initialize tenancy (middleware normally does this on next request)
        tenancy()->initialize($tenant);

        // Ensure the tenant customers table exists for the test (create via Schema on tenant connection)
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        // Create a customer via the tenant-scoped demo endpoint
        $response = $this->postJson(route('tenant.customers.store'), ['name' => 'John Doe']);
        $response->assertStatus(201)->assertJsonFragment(['name' => 'John Doe']);

        // Ensure the Customer exists via the Eloquent model (uses default/tenant connection when tenancy initialized)
        $this->assertTrue(\App\Models\Customer::where('name', 'John Doe')->exists());
    }
}
