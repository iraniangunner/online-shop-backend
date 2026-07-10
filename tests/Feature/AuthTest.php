<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'کاربر تست',
            'email' => 'test@example.com',
            'phone' => '09121111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonPath('user.role', User::ROLE_CUSTOMER);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'exists@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'کاربر جدید',
            'email' => 'exists@example.com',
            'phone' => '09122222222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_fails_when_passwords_do_not_match(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'کاربر تست',
            'email' => 'test2@example.com',
            'phone' => '09123333333',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_is_hashed_correctly_and_not_double_hashed(): void
    {
        $this->postJson('/api/register', [
            'name' => 'کاربر تست',
            'email' => 'hash-test@example.com',
            'phone' => '09124444444',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'hash-test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_active_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'login-test@example.com',
            'password' => 'secret123',
        ]);

        // login() ما یه درخواست HTTP به /oauth/token خودِ همین اپ می‌زنه
        // (Password Grant). توی تست، سرور واقعی در حال اجرا نیست، پس این
        // درخواست رو fake می‌کنیم تا انگار Passport واقعی جواب داده.
        Http::fake([
            '*/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'fake-access-token',
                'refresh_token' => 'fake-refresh-token',
            ], 200),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login-test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('access_token', 'fake-access-token')
            ->assertJsonPath('user.email', 'login-test@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrong-pass@example.com',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrong-pass@example.com',
            'password' => 'incorrect-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_login_fails_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever123',
        ]);

        $response->assertStatus(401);
    }
}
