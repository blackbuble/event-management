<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService(new UserRepository(new User()));
    }

    /**
     * Test WB-01: Mass Assignment Prevention
     */
    public function test_internal_fields_cannot_be_mass_assigned()
    {
        $userData = [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'phone' => '+628123456789',
            'otp' => '666666', // Internal field
            'social_id' => '12345', // Internal field
        ];

        $user = User::create($userData);

        // otp and social_id should NOT be filled if they are not in $fillable
        $this->assertNull($user->otp);
        $this->assertNull($user->social_id);
    }

    /**
     * Test Phone Normalization Logic
     */
    public function test_phone_normalization_handles_various_formats()
    {
        // Use reflection to test private method normalizeIdentity
        $reflection = new \ReflectionClass(AuthService::class);
        $method = $reflection->getMethod('normalizeIdentity');
        $method->setAccessible(true);

        $this->assertEquals('+628123456789', $method->invoke($this->authService, '08123456789'));
        $this->assertEquals('+628123456789', $method->invoke($this->authService, '+08123456789'));
        $this->assertEquals('+628123456789', $method->invoke($this->authService, ' 62812-3456-789 '));
    }

    /**
     * Test WB-03: Social Hijacking Prevention
     */
    public function test_cannot_link_existing_email_to_different_social_id()
    {
        $email = 'user@example.com';
        User::factory()->create([
            'email' => $email,
            'social_id' => 'original_id',
            'social_type' => 'google'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('sudah terhubung dengan sosial media lain');

        $this->authService->socialLogin([
            'name' => 'Hacker',
            'email' => $email,
            'social_id' => 'attacker_id', // Different ID
            'social_type' => 'google'
        ]);
    }

    /**
     * Test OTP Atomicity - Mocking or state checking
     */
    public function test_otp_is_cleared_after_successful_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'otp' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10)
        ]);

        $this->authService->loginWithOtp('test@example.com', '123456');

        $user->refresh();
        $this->assertNull($user->otp);
        $this->assertNull($user->otp_expires_at);
    }
}
