<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $this->admin = $admin;
        Passport::actingAs($this->admin);
    }

    // ---------- شعبه ----------

    public function test_admin_can_create_a_branch(): void
    {
        $response = $this->postJson('/api/admin/branches', [
            'name' => 'شعبه‌ی جدید',
            'city' => 'تهران',
            'address' => 'خیابان ولیعصر',
            'phone' => '02100000000',
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('branches', [
            'name' => 'شعبه‌ی جدید',
            'city' => 'تهران',
        ]);
    }

    public function test_creating_branch_without_name_fails_validation(): void
    {
        $response = $this->postJson('/api/admin/branches', [
            'city' => 'تهران',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_update_a_branch(): void
    {
        $branch = Branch::factory()->create(['name' => 'اسم قدیمی']);

        $response = $this->putJson("/api/admin/branches/{$branch->id}", [
            'name' => 'اسم جدید',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'اسم جدید']);
    }

    public function test_deleting_branch_deactivates_instead_of_hard_delete(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);

        $response = $this->deleteJson("/api/admin/branches/{$branch->id}");

        $response->assertStatus(200);

        // چون appointment های قدیمی ممکنه به شعبه وصل باشن، حذف واقعی نمی‌کنیم
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'is_active' => false]);
    }

    // ---------- خدمت ----------

    public function test_admin_can_create_a_service_with_branches(): void
    {
        $category = ServiceCategory::factory()->create();
        $branch = Branch::factory()->create();

        $response = $this->postJson('/api/admin/services', [
            'service_category_id' => $category->id,
            'name' => 'لیزر موهای زائد',
            'duration_minutes' => 45,
            'price' => 800000,
            'branch_ids' => [$branch->id],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('services', ['name' => 'لیزر موهای زائد', 'price' => 800000]);

        $service = Service::where('name', 'لیزر موهای زائد')->first();
        $this->assertTrue($service->branches->contains($branch->id));
    }

    public function test_creating_service_without_branch_ids_fails(): void
    {
        $category = ServiceCategory::factory()->create();

        $response = $this->postJson('/api/admin/services', [
            'service_category_id' => $category->id,
            'name' => 'یه خدمت',
            'duration_minutes' => 30,
            'price' => 100000,
            // branch_ids عمداً نیومده
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['branch_ids']);
    }

    public function test_creating_service_with_invalid_category_fails(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->postJson('/api/admin/services', [
            'service_category_id' => 99999, // وجود نداره
            'name' => 'یه خدمت',
            'duration_minutes' => 30,
            'price' => 100000,
            'branch_ids' => [$branch->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_category_id']);
    }

    public function test_admin_can_soft_delete_a_service(): void
    {
        $service = Service::factory()->create();

        $response = $this->deleteJson("/api/admin/services/{$service->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('services', ['id' => $service->id]);
    }

    // ---------- متخصص ----------

    public function test_admin_can_create_specialist_with_login_account(): void
    {
        $branch = Branch::factory()->create();
        $service = Service::factory()->create();

        $response = $this->postJson('/api/admin/specialists', [
            'full_name' => 'دکتر جدید',
            'phone' => '09121234567',
            'branch_ids' => [$branch->id],
            'service_ids' => [$service->id],
            'create_account' => true,
            'email' => 'newdoc@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('specialists', ['full_name' => 'دکتر جدید']);

        $this->assertDatabaseHas('users', [
            'email' => 'newdoc@example.com',
            'role' => User::ROLE_SPECIALIST,
        ]);
    }

    public function test_admin_can_create_specialist_without_login_account(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->postJson('/api/admin/specialists', [
            'full_name' => 'دکتر بدون حساب',
            'branch_ids' => [$branch->id],
            'create_account' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('specialists', ['full_name' => 'دکتر بدون حساب']);
        $this->assertDatabaseMissing('users', ['name' => 'دکتر بدون حساب']);
    }

    public function test_creating_specialist_account_with_duplicate_email_fails(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $branch = Branch::factory()->create();

        $response = $this->postJson('/api/admin/specialists', [
            'full_name' => 'دکتر جدید',
            'branch_ids' => [$branch->id],
            'create_account' => true,
            'email' => 'taken@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_update_specialist_services(): void
    {
        $branch = Branch::factory()->create();
        $specialist = Specialist::factory()->create();
        $specialist->branches()->attach($branch->id);

        $service1 = Service::factory()->create();
        $service2 = Service::factory()->create();
        $specialist->services()->attach($service1->id, ['branch_id' => $branch->id]);

        $response = $this->putJson("/api/admin/specialists/{$specialist->id}", [
            'branch_ids' => [$branch->id],
            'service_ids' => [$service2->id], // فقط service2 - service1 باید حذف بشه
        ]);

        $response->assertStatus(200);

        $specialist->refresh();
        $this->assertFalse($specialist->services->contains($service1->id));
        $this->assertTrue($specialist->services->contains($service2->id));
    }
}
