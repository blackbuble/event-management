<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
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

    private function organizer(string $name = 'Organizer'): User
    {
        $user = User::factory()->create(['name' => $name, 'phone' => fake()->unique()->numerify('+62812#########')]);
        $user->assignRole('organizer');

        return $user;
    }

    private function attendee(string $name = 'Attendee'): User
    {
        $user = User::factory()->create(['name' => $name, 'phone' => fake()->unique()->numerify('+62898#########')]);
        $user->assignRole('attendee');

        return $user;
    }

    public function test_users_can_be_searched(): void
    {
        $this->admin();
        $this->organizer('Alice Wonder');
        $this->attendee('Bob Builder');

        $this->actingAs(User::role('admin')->first())
            ->get(route('admin.users', ['search' => 'Alice']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('users', 1)->where('users.0.name', 'Alice Wonder'));
    }

    public function test_users_can_be_filtered_by_role(): void
    {
        $admin = $this->admin();
        $this->organizer('Organizer One');
        $this->attendee('Attendee One');

        $this->actingAs($admin)
            ->get(route('admin.users', ['role' => 'organizer']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('users', 1)->where('users.0.roles.0', 'organizer'));
    }

    public function test_users_can_be_filtered_by_status(): void
    {
        $admin = $this->admin();
        $suspended = $this->organizer('Suspended One');
        $suspended->forceFill(['suspended_at' => now()])->save();
        $this->attendee('Active One');

        $this->actingAs($admin)
            ->get(route('admin.users', ['status' => 'suspended']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('users', 1)->where('users.0.suspended', true));
    }

    public function test_users_list_is_paginated(): void
    {
        $admin = $this->admin();
        User::factory()->count(20)->create();

        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('users', 15)
                    ->where('pagination.last_page', 2)
                    ->where('pagination.total', 21)
            );

        $this->actingAs($admin)
            ->get(route('admin.users', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pagination.current_page', 2));
    }

    public function test_admin_can_suspend_and_activate_a_user(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $organizer))
            ->assertRedirect();

        $this->assertNotNull($organizer->fresh()->suspended_at);

        // Suspended users are logged out on their next request.
        $this->actingAs($organizer->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->post(route('admin.users.activate', $organizer))
            ->assertRedirect();

        $this->assertNull($organizer->fresh()->suspended_at);
    }

    public function test_admin_cannot_suspend_or_delete_self_or_admins(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.suspend', $admin))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.suspend', $otherAdmin))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $otherAdmin))->assertForbidden();
    }

    public function test_admin_can_delete_a_user(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $organizer))
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $organizer->id]);
    }

    public function test_suspended_organizer_cannot_be_impersonated(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();
        $organizer->forceFill(['suspended_at' => now()])->save();

        $this->actingAs($admin)
            ->post(route('admin.impersonate', $organizer), ['password' => 'secret-pass'])
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($admin);
    }
}
