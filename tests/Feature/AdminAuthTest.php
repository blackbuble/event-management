<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(string $password = 'secret-pass'): User
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function organizer(): User
    {
        $user = User::factory()->create([
            'phone' => fake()->unique()->numerify('+62812#########'),
            'password' => Hash::make('secret-pass'),
        ]);
        $user->assignRole('organizer');

        return $user;
    }

    public function test_admin_login_page_is_public(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Login'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->admin('correct-pass');

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-pass',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_non_admin_cannot_start_admin_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-pass'),
        ]);
        $user->assignRole('organizer');

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'secret-pass',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_must_pass_otp_after_password(): void
    {
        $admin = $this->admin('correct-pass');

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'correct-pass',
        ])->assertRedirect(route('admin.otp'));

        // Still not logged in until OTP is verified.
        $this->assertGuest();

        // Simulate the issued OTP.
        $admin->forceFill([
            'otp' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->post(route('admin.otp.verify'), ['otp' => '123456'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_otp_does_not_authenticate(): void
    {
        $admin = $this->admin('correct-pass');

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'correct-pass',
        ]);

        $admin->forceFill([
            'otp' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->post(route('admin.otp.verify'), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_otp_can_be_resent_via_email(): void
    {
        $admin = $this->admin('correct-pass');

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'correct-pass',
        ]);

        $this->post(route('admin.otp.resend'), ['channel' => 'email'])
            ->assertSessionHas('message');
    }

    public function test_otp_resend_via_whatsapp_requires_a_phone(): void
    {
        $admin = $this->admin('correct-pass');

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'correct-pass',
        ]);

        $this->post(route('admin.otp.resend'), ['channel' => 'whatsapp'])
            ->assertSessionHas('error');
    }

    public function test_otp_can_be_resent_via_whatsapp_when_phone_present(): void
    {
        $admin = $this->admin('correct-pass');
        $admin->forceFill(['phone' => '+628123456789'])->save();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'correct-pass',
        ]);

        $this->post(route('admin.otp.resend'), ['channel' => 'whatsapp'])
            ->assertSessionHas('message');
    }

    public function test_admin_email_with_dash_can_complete_otp(): void
    {
        // Regression: identity normalization used to strip '-' from emails,
        // resolving "admin@event-management.test" to a different address.
        $admin = User::factory()->create([
            'email' => 'admin@event-management.test',
            'password' => Hash::make('correct-pass'),
        ]);
        $admin->assignRole('admin');

        // A decoy account that the mangled email used to resolve to.
        User::factory()->create(['email' => 'admin@eventmanagement.test']);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@event-management.test',
            'password' => 'correct-pass',
        ])->assertRedirect(route('admin.otp'));

        $admin->forceFill([
            'otp' => Hash::make('654321'),
            'otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->post(route('admin.otp.verify'), ['otp' => '654321'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_panel_requires_admin_role(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($this->organizer())
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
    }
}
