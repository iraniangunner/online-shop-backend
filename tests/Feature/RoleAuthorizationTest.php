<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/api/admin/branches');

        $response->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_routes(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Passport::actingAs($customer);

        $response = $this->getJson('/api/admin/branches');

        $response->assertStatus(403);
    }

    public function test_specialist_cannot_access_admin_routes(): void
    {
        /** @var User $specialist */
        $specialist = User::factory()->specialistRole()->create();
        Passport::actingAs($specialist);

        $response = $this->getJson('/api/admin/branches');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        Branch::factory()->count(2)->create();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        Passport::actingAs($admin);

        $response = $this->getJson('/api/admin/branches');

        $response->assertStatus(200);
    }

    public function test_customer_cannot_access_specialist_routes(): void
    {
        /** @var User $customer */
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Passport::actingAs($customer);

        $response = $this->getJson('/api/specialist/appointments');

        $response->assertStatus(403);
    }

    public function test_admin_cannot_access_specialist_routes(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        Passport::actingAs($admin);

        $response = $this->getJson('/api/specialist/appointments');

        $response->assertStatus(403);
    }

    public function test_specialist_cannot_access_customer_booking_routes(): void
    {
        /** @var User $specialist */
        $specialist = User::factory()->specialistRole()->create();
        Passport::actingAs($specialist);

        $response = $this->getJson('/api/appointments');

        $response->assertStatus(403);
    }

    public function test_guest_can_access_public_routes_without_auth(): void
    {
        Branch::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/branches');

        $response->assertStatus(200);
    }
}
