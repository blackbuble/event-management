<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhatsAppPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WhatsAppQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, WhatsAppPackageSeeder::class]);
    }

    private function organizer(int $quota = 0): User
    {
        $user = User::factory()->create([
            'phone' => fake()->unique()->numerify('+62812#########'),
            'whatsapp_quota' => $quota,
        ]);
        $user->assignRole('organizer');

        return $user;
    }

    private function attendee(): User
    {
        $user = User::factory()->create(['phone' => fake()->unique()->numerify('+62898#########')]);
        $user->assignRole('attendee');

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('whatsapp.index'))->assertRedirect(route('login'));
        $this->post(route('whatsapp.topup'), [])->assertRedirect(route('login'));
    }

    public function test_attendee_cannot_access_whatsapp_quota(): void
    {
        $this->actingAs($this->attendee())->get(route('whatsapp.index'))->assertForbidden();
        $this->actingAs($this->attendee())
            ->post(route('whatsapp.topup'), ['package' => 'starter', 'payment_method' => 'qris'])
            ->assertForbidden();
    }

    public function test_organizer_can_view_quota_dashboard(): void
    {
        $this->actingAs($this->organizer(quota: 12))
            ->get(route('whatsapp.index'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Dashboard/WhatsApp')
                    ->where('balance', 12)
                    ->has('packages', 3)
                    ->has('payment_methods', 5)
            );
    }

    public function test_organizer_can_top_up_quota(): void
    {
        $organizer = $this->organizer(quota: 0);

        $this->actingAs($organizer)
            ->post(route('whatsapp.topup'), ['package' => 'growth', 'payment_method' => 'bank_transfer'])
            ->assertRedirect();

        $this->assertSame(500, $organizer->fresh()->whatsapp_quota);
        $this->assertDatabaseHas('whatsapp_topups', [
            'user_id' => $organizer->id,
            'package' => 'growth',
            'quota' => 500,
            'payment_method' => 'bank_transfer',
            'status' => 'paid',
        ]);
    }

    public function test_top_up_validation_rejects_unknown_package_and_method(): void
    {
        $this->actingAs($this->organizer())
            ->post(route('whatsapp.topup'), ['package' => 'nope', 'payment_method' => 'crypto'])
            ->assertSessionHasErrors(['package', 'payment_method']);
    }
}
