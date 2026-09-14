<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['password' => Hash::make('secret-pass')]);
        $user->assignRole('admin');

        return $user;
    }

    private function organizer(): User
    {
        $user = User::factory()->create(['phone' => fake()->unique()->numerify('+62812#########')]);
        $user->assignRole('organizer');

        return $user;
    }

    public function test_admin_can_impersonate_an_organizer(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $organizer), ['password' => 'secret-pass'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($organizer);
        $this->assertSame($admin->id, session('impersonator_id'));
    }

    public function test_impersonating_incomplete_organizer_lands_on_dashboard(): void
    {
        $admin = $this->admin();
        $organizer = User::factory()->create(['phone' => null]);
        $organizer->assignRole('organizer');

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $organizer), ['password' => 'secret-pass'])
            ->assertRedirect(route('dashboard'));

        // Profile gating is bypassed while impersonating.
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_impersonation_attempts_are_logged(): void
    {
        Log::spy();

        $admin = $this->admin();
        $organizer = $this->organizer();
        $otherAdmin = $this->admin();

        $this->actingAs($admin)->post(route('admin.impersonate', $organizer), ['password' => 'secret-pass']);
        $this->post(route('impersonation.stop'));

        Log::shouldHaveReceived('info')->withArgs(fn ($message) => $message === 'impersonation.attempt')->atLeast()->once();
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => $message === 'impersonation.started')->atLeast()->once();
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => $message === 'impersonation.stopped')->atLeast()->once();

        $this->actingAs($admin)->post(route('admin.impersonate', $otherAdmin), ['password' => 'secret-pass']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'impersonation.blocked_admin')->atLeast()->once();
    }

    public function test_admin_cannot_impersonate_another_admin(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $otherAdmin), ['password' => 'secret-pass'])
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_impersonation_can_be_stopped(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();

        $this->actingAs($admin)->post(route('admin.impersonate', $organizer), ['password' => 'secret-pass']);
        $this->assertAuthenticatedAs($organizer);

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_impersonation_requires_correct_admin_password(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $organizer), ['password' => 'wrong-pass'])
            ->assertSessionHasErrors('password');

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $organizer = $this->organizer();
        $target = $this->organizer();

        $this->actingAs($organizer)
            ->post(route('admin.impersonate', $target))
            ->assertRedirect(route('admin.login'));
    }
}
