<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // درخواست‌های واقعی به کاوه‌نگار رو fake می‌کنیم
        Http::fake([
            '*kavenegar.com*' => Http::response([
                'return' => ['status' => 200, 'message' => 'تایید شد'],
                'entries' => [['messageid' => 123, 'status' => 1]],
            ], 200),
        ]);
    }

    public function test_send_otp_creates_a_code_for_valid_mobile(): void
    {
        $response = $this->postJson('/api/send-otp', ['mobile' => '09121111111']);

        $response->assertStatus(200);

        $this->assertDatabaseHas('otp_codes', [
            'mobile' => '09121111111',
            'used' => false,
        ]);
    }

    public function test_send_otp_rejects_invalid_mobile_format(): void
    {
        $response = $this->postJson('/api/send-otp', ['mobile' => '123456']);

        $response->assertStatus(422);
    }

    public function test_send_otp_is_rate_limited_within_two_minutes(): void
    {
        $this->postJson('/api/send-otp', ['mobile' => '09121111111'])->assertStatus(200);

        // بلافاصله دوباره درخواست بده - باید رد بشه
        $response = $this->postJson('/api/send-otp', ['mobile' => '09121111111']);

        $response->assertStatus(429);
    }

    public function test_verify_otp_with_correct_code_logs_in_new_user(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'fake-token',
                'refresh_token' => 'fake-refresh',
            ], 200),
            '*kavenegar.com*' => Http::response(['return' => ['status' => 200]], 200),
        ]);

        OtpCode::create([
            'mobile' => '09129999999',
            'code' => '123456',
            'used' => false,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/verify-otp', [
            'mobile' => '09129999999',
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('is_new_user', true)
            ->assertJsonPath('access_token', 'fake-token');

        $this->assertDatabaseHas('users', [
            'phone' => '09129999999',
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    public function test_verify_otp_with_wrong_code_fails(): void
    {
        OtpCode::create([
            'mobile' => '09128888888',
            'code' => '111111',
            'used' => false,
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/verify-otp', [
            'mobile' => '09128888888',
            'code' => '999999',
        ]);

        $response->assertStatus(401);
    }

    public function test_verify_otp_with_expired_code_fails(): void
    {
        OtpCode::create([
            'mobile' => '09127777777',
            'code' => '222222',
            'used' => false,
            'expires_at' => now()->subMinutes(1), // منقضی شده
        ]);

        $response = $this->postJson('/api/verify-otp', [
            'mobile' => '09127777777',
            'code' => '222222',
        ]);

        $response->assertStatus(401);
    }

    public function test_otp_login_does_not_corrupt_existing_password(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'fake-token',
                'refresh_token' => 'fake-refresh',
            ], 200),
            '*kavenegar.com*' => Http::response(['return' => ['status' => 200]], 200),
        ]);

        // کاربری که از قبل با ایمیل/پسورد ثبت‌نام کرده
        $user = User::factory()->create([
            'phone' => '09126666666',
            'password' => 'my-real-password',
        ]);

        OtpCode::create([
            'mobile' => '09126666666',
            'code' => '333333',
            'used' => false,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/verify-otp', [
            'mobile' => '09126666666',
            'code' => '333333',
        ])->assertStatus(200);

        // پسورد واقعی نباید عوض شده باشه - این همون باگی بود که قبلاً پیدا کردیم
        $user->refresh();
        $this->assertTrue(Hash::check('my-real-password', $user->password));
    }
}