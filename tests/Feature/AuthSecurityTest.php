<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test BB-01: OTP Brute Force Attack
     */
    public function test_otp_is_invalidated_after_five_failed_attempts()
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        // Speed up bcrypt for testing
        config(['auth.providers.users.model' => User::class]);
        
        $phone = '+628123456789';
        $user = User::factory()->create(['phone' => $phone]);

        // Mock a valid but hashed OTP in DB
        $user->otp = Hash::make('123456');
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        // Perform 5 wrong attempts
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->withSession(['auth_identity' => $phone])
                ->post('/otp/login', [
                    'otp' => '000000',
                ]);
            
            $response->assertStatus(302);
            $response->assertSessionHasErrors('otp');
        }

        // The 6th attempt should fail because the OTP field was nulled
        $user->refresh();
        $this->assertNull($user->otp, "OTP field should be null after 5 failed attempts");
        
        // Attempt with correct OTP should now fail because it's already nulled
        $response = $this->withSession(['auth_identity' => $phone])
            ->from('/otp/login') // Ensure referral is set so back() works correctly
            ->post('/otp/login', ['otp' => '123456']);
            
        $response->assertStatus(302);
        // Check for any errors if 'otp' is missing
        if (!$response->getSession()->has('errors')) {
            $this->fail("Session has no errors. Status: " . $response->status());
        }
        $response->assertSessionHasErrors('otp');
    }

    /**
     * Test BB-03: Identity Switching Prevention
     */
    public function test_cannot_switch_identity_during_otp_verification()
    {
        $victimPhone = '+628111111111';
        $attackerPhone = '+628999999999';
        
        $victim = User::factory()->create(['phone' => $victimPhone]);
        $attacker = User::factory()->create(['phone' => $attackerPhone]);

        $victim->otp = Hash::make('111111');
        $victim->otp_expires_at = now()->addMinutes(10);
        $victim->save();

        $attacker->otp = Hash::make('999999');
        $attacker->otp_expires_at = now()->addMinutes(10);
        $attacker->save();

        $response = $this->withSession(['auth_identity' => $attackerPhone])
            ->from('/otp/login')
            ->post('/otp/login', [
                'identity' => $victimPhone,
                'otp' => '111111',
            ]);

        $this->assertFalse(\Illuminate\Support\Facades\Auth::check(), "Attacker should not be logged in");
        $response->assertSessionHasErrors('otp');
    }

    /**
     * Test BB-02: Identity-based Rate Limiting
     */
    public function test_rate_limiting_is_applied_per_identity()
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $identity = 'spam-target@example.com';
        RateLimiter::clear('auth_gate_' . \Illuminate\Support\Str::slug($identity));
        
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identity' => $identity]);
        }

        $response = $this->post('/login', ['identity' => $identity]);
        
        $response->assertStatus(302);
        $response->assertSessionHasErrors('identity');
    }

    /**
     * Test BB-04: Secure OTP Page Access
     */
    public function test_otp_page_requires_active_auth_session()
    {
        $response = $this->get('/otp/login');
        $response->assertRedirect('/login');
    }
}
